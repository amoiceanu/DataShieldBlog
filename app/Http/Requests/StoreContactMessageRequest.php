<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180'], 'subject' => ['required', 'string', 'max:180'], 'message' => ['required', 'string', 'min:10', 'max:5000'], 'privacy_accepted' => ['accepted'], 'website' => ['nullable', 'string', 'max:255']];
    }

    public function attributes(): array
    {
        return ['privacy_accepted' => 'acordul privind prelucrarea datelor', 'website' => 'website'];
    }
}
