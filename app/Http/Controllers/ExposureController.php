<?php

namespace App\Http\Controllers;

use App\Jobs\RunExposureCheck;
use App\Models\ExposureChallenge;
use App\Models\ExposureReport;
use App\Services\Exposure\EmailVerification;
use App\Services\Exposure\Privacy;
use App\Services\Exposure\Settings;
use App\Services\Exposure\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ExposureController extends Controller
{
    public function __construct(private Settings $settings, private Privacy $privacy) {}

    public function index()
    {
        return view('exposure.index', ['settings' => $this->settings->all(), 'settingsService' => $this->settings]);
    }

    private function limit(Request $request, ?string $address = null): void
    {
        $s = $this->settings->all();
        $keys = [['ip:'.$request->ip(), $s['ip_limit']], ['session:'.$this->privacy->owner($request), $s['session_limit']]];
        if ($address) {
            $keys[] = ['address:'.$address, $s['address_limit']];
        }
        foreach ($keys as [$key,$max]) {
            abort_if(RateLimiter::tooManyAttempts('exposure:'.$this->privacy->digest($key), $max), 429, 'Limita temporară a fost atinsă. Încearcă mai târziu.');
        }
        foreach ($keys as [$key]) {
            RateLimiter::hit('exposure:'.$this->privacy->digest($key), 3600);
        }
    }

    public function store(Request $request, EmailVerification $verification, Usage $usage)
    {
        abort_unless(config('exposure.hmac_key'), 503);
        $s = $this->settings->all();
        $v = Validator::make($request->only('module', 'match_type', 'subject', 'location', 'own_data'), [
            'module' => 'required|in:breaches,mentions', 'match_type' => 'required|in:email,name', 'subject' => 'required|string|max:150', 'location' => ['nullable', 'string', 'max:100', 'regex:/^[\p{L}\p{N} .,-]*$/u'], 'own_data' => 'accepted',
        ], ['own_data.accepted' => 'Confirmă că verifici propriile date.', 'subject.required' => 'Completează datele pentru verificare.', 'subject.max' => 'Datele introduse sunt prea lungi.', 'location.regex' => 'Folosește doar numele localității sau țării.']);
        if ($v->fails()) {
            return redirect()->to(route('exposure.index').($request->input('module') === 'mentions' ? '#mentions' : '#breaches'))->withErrors($v);
        }
        $data = $v->validated();
        abort_unless($s[$data['module']], 404);
        if ($data['module'] === 'breaches' && $data['match_type'] !== 'email') {
            abort(422);
        }
        $value = trim($data['subject']);
        $isEmail = $data['match_type'] === 'email';
        if ($isEmail && (! filter_var($value, FILTER_VALIDATE_EMAIL) || str_contains($value, '"') || str_contains($value, '\\'))) {
            return redirect()->to(route('exposure.index').($request->input('module') === 'mentions' ? '#mentions' : '#breaches'))->withErrors(['subject' => 'Introdu o adresă de e-mail validă.']);
        }
        if (! $isEmail && (! preg_match('/^[\p{L}\p{M} .\x{2019}\x{0027}-]+$/u', $value) || mb_strlen($value) < 3)) {
            return redirect()->to(route('exposure.index').($request->input('module') === 'mentions' ? '#mentions' : '#breaches'))->withErrors(['subject' => 'Introdu numele complet, fără operatori de căutare.']);
        }
        if (count(preg_split('/\s+/u', trim($value.' '.($data['location'] ?? '')))) > 50) {
            return redirect()->to(route('exposure.index').($request->input('module') === 'mentions' ? '#mentions' : '#breaches'))->withErrors(['subject' => 'Folosește maximum 50 de cuvinte pentru căutare.']);
        }
        $address = $isEmail ? $this->privacy->address($value) : null;
        $this->limit($request, $address);
        if (! $this->settings->configured($data['module'])) {
            return redirect()->to(route('exposure.index').($request->input('module') === 'mentions' ? '#mentions' : '#breaches'))->with('exposure_notice', 'Furnizorul selectat este momentan indisponibil. Verificarea nu a fost efectuată.');
        }
        $verified = $isEmail && ($request->session()->get('exposure_verified.'.$address, 0) > now()->timestamp);
        if ($isEmail && ! $verified && ! $this->settings->mailReady()) {
            return redirect()->to(route('exposure.index').($request->input('module') === 'mentions' ? '#mentions' : '#breaches'))->with('exposure_notice', 'Trimiterea confirmării nu este configurată. Verificarea nu a fost efectuată.');
        }
        $report = ExposureReport::create(['owner' => $this->privacy->owner($request), 'address_hmac' => $address, 'module' => $data['module'], 'match_type' => $data['match_type'], 'subject' => ['value' => $value, 'location' => $isEmail ? '' : ($data['location'] ?? '')], 'state' => ($isEmail && ! $verified) ? 'awaiting_verification' : 'queued', 'expires_at' => now()->addHours($s['retention_hours'])]);
        $usage->count($report->module, 'checks');
        if ($report->state === 'awaiting_verification') {
            $verification->send($report);
        } else {
            $this->dispatch($report);
        }

        return redirect()->route('exposure.report', $report->id);
    }

    private function dispatch(ExposureReport $report): void
    {
        RunExposureCheck::dispatch($report->id)->onConnection('database')->onQueue(config('exposure.queue'));
    }

    private function owned(Request $request, string $id): ExposureReport
    {
        $r = ExposureReport::findOrFail($id);
        abort_unless(hash_equals($r->owner, $this->privacy->owner($request)) && $r->expires_at->isFuture(), 404);
        abort_unless($this->settings->all()[$r->module], 404);

        return $r;
    }

    public function show(Request $request, string $id)
    {
        return view('exposure.report', ['report' => $this->owned($request, $id)]);
    }

    public function status(Request $request, string $id)
    {
        $r = $this->owned($request, $id);

        return response()->json(['state' => $r->state]);
    }

    public function resend(Request $request, string $id, EmailVerification $verification)
    {
        $r = $this->owned($request, $id);
        abort_unless($r->state === 'awaiting_verification', 409);
        $this->limit($request, $r->address_hmac);
        if ($this->settings->mailReady() && $this->settings->configured($r->module)) {
            ExposureChallenge::where('report_id', $r->id)->delete();
            $verification->send($r);
        }

        return back()->with('exposure_notice', 'Dacă solicitarea poate fi procesată, vei primi un link de confirmare. Verifică și folderul spam.');
    }

    public function verificationLink(Request $request, string $token)
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return redirect()->route('exposure.index')->with('exposure_notice', 'Linkul nu poate fi folosit. Solicită o verificare nouă.');
        }
        $request->session()->put('exposure_confirmation', hash('sha256', $token));

        return redirect()->route('exposure.confirmation');
    }

    public function confirmation()
    {
        return view('exposure.confirm');
    }

    public function confirm(Request $request)
    {
        $hash = $request->session()->pull('exposure_confirmation', '');
        $owner = $this->privacy->owner($request);
        $report = DB::transaction(function () use ($hash, $owner) {
            $c = ExposureChallenge::where('token_hash', $hash)->lockForUpdate()->first();
            if (! $c || $c->used_at || $c->expires_at->isPast()) {
                return null;
            }
            $r = ExposureReport::whereKey($c->report_id)->lockForUpdate()->first();
            if (! $r || ! hash_equals($owner, $r->owner) || $r->expires_at->isPast() || $r->state !== 'awaiting_verification' || ! $this->settings->all()[$r->module]) {
                return null;
            }
            $c->update(['used_at' => now()]);
            $r->update(['state' => 'queued']);

            return $r;
        });
        if (! $report) {
            return redirect()->route('exposure.index')->with('exposure_notice', 'Linkul este invalid, expirat, utilizat sau deschis într-un alt browser. Solicită o verificare nouă.');
        }
        $request->session()->put('exposure_verified.'.$report->address_hmac, now()->addHour()->timestamp);
        $this->dispatch($report);

        return redirect()->route('exposure.report', $report->id);
    }

    public function classify(Request $request, string $id, int $index)
    {
        $r = $this->owned($request, $id);
        abort_unless($r->match_type === 'name' && $r->state === 'complete', 409);
        $value = $request->input('classification');
        abort_unless(in_array($value, ['yes', 'no', 'unsure'], true), 422);
        DB::transaction(function () use ($r, $index, $value) {
            $report = ExposureReport::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $items = $report->results ?? [];
            abort_unless(isset($items[$index]), 404);
            $items[$index]['classification'] = $value;
            $report->update(['results' => $items]);
        });

        return back();
    }

    public function destroy(Request $request, string $id)
    {
        $this->owned($request, $id)->delete();

        return redirect()->route('exposure.index')->with('exposure_notice', 'Raportul a fost șters din aplicație. Copiile de siguranță se elimină conform ciclului lor de retenție; ștergerea nu se extinde la furnizori.');
    }
}
