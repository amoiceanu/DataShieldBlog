<?php

namespace App\Http\Controllers;

use App\Models\ExposureReport;
use App\Services\Exposure\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExposureAdminController extends Controller
{
    private function authorizeAdmin(Request $r): void
    {
        abort_unless($r->user()?->is_exposure_admin, 403);
    }

    public function index(Request $request, Settings $settings)
    {
        $this->authorizeAdmin($request);

        return response()->view('admin.exposure', ['settings' => $settings->all(), 'settingsService' => $settings, 'usage' => DB::table('exposure_usage')->where('day', '>=', today()->subDays(29)->toDateString())->orderByDesc('day')->limit(60)->get()])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['enabled' => 'boolean', 'menu' => 'boolean', 'breaches' => 'boolean', 'mentions' => 'boolean', 'ip_limit' => 'required|integer|min:1|max:1000', 'session_limit' => 'required|integer|min:1|max:100', 'address_limit' => 'required|integer|min:1|max:20', 'daily_limit' => 'required|integer|min:1|max:100000', 'max_results' => 'required|integer|min:1|max:20', 'retention_hours' => 'required|integer|min:1|max:72', 'provider_interval' => 'required|integer|min:1|max:120']);
        foreach (['enabled', 'menu', 'breaches', 'mentions'] as $key) {
            $data[$key] = $request->boolean($key);
        }
        DB::table('exposure_settings')->updateOrInsert(['id' => 1], ['options' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
        // Apply a shorter retention from creation, without extending any existing deadline.
        ExposureReport::select(['id', 'created_at', 'expires_at'])->chunkById(100, function ($reports) use ($data) {
            foreach ($reports as $report) {
                $deadline = $report->created_at->copy()->addHours((int) $data['retention_hours']);
                if ($report->expires_at->greaterThan($deadline)) {
                    $report->update(['expires_at' => $deadline]);
                }
            }
        });

        return redirect()->route('exposure.admin')->with('exposure_notice', 'Setările au fost salvate.');
    }
}
