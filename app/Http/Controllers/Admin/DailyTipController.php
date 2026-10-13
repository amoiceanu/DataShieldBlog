<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyTip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyTipController extends Controller
{
    public function index(): View
    {
        return view('admin.daily-tips.index', [
            'tips' => DailyTip::latest('updated_at')->paginate(15),
            'stats' => [
                'total' => DailyTip::count(),
                'published' => DailyTip::published()->count(),
                'drafts' => DailyTip::where('status', 'draft')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.daily-tips.form', ['dailyTip' => new DailyTip(['status' => 'draft'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        DailyTip::create($this->validatedData($request));

        return redirect()->route('admin.sfaturi.index')->with('success', 'Sfatul a fost creat.');
    }

    public function edit(DailyTip $dailyTip): View
    {
        return view('admin.daily-tips.form', compact('dailyTip'));
    }

    public function update(Request $request, DailyTip $dailyTip): RedirectResponse
    {
        $dailyTip->update($this->validatedData($request, $dailyTip));

        return redirect()->route('admin.sfaturi.index')->with('success', 'Sfatul a fost actualizat.');
    }

    public function destroy(DailyTip $dailyTip): RedirectResponse
    {
        $dailyTip->delete();

        return back()->with('success', 'Sfatul a fost șters.');
    }

    private function validatedData(Request $request, ?DailyTip $dailyTip = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = $dailyTip?->slug ?? (Str::slug($data['title']) ?: 'sfat');
        $data['slug'] = Str::limit($data['slug'], 240, '');
        if (! $dailyTip && DailyTip::where('slug', $data['slug'])->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(8));
        }
        validator(['slug' => $data['slug']], ['slug' => ['required', 'max:255', Rule::unique('daily_tips', 'slug')->ignore($dailyTip?->id)]])->validate();
        $data['published_at'] = $data['status'] === 'published'
            ? ($data['published_at'] ?? $dailyTip?->published_at?->toDateString() ?? today()->toDateString())
            : ($data['published_at'] ?? null);

        return $data;
    }
}
