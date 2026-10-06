<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View { return view('pages.contact'); }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        if (filled($request->input('website'))) return back()->with('success', 'Mesajul tău a fost trimis. Îți mulțumim!');
        ContactMessage::create([...$request->safe()->only(['name', 'email', 'subject', 'message']), 'privacy_accepted_at' => now()]);
        return back()->with('success', 'Mesajul tău a fost trimis. Îți mulțumim!');
    }
}
