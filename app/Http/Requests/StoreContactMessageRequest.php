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

    public function messages(): array
    {
        return [
            'name.required' => 'Te rugăm să completezi numele.',
            'name.string' => 'Numele introdus nu este valid.',
            'name.max' => 'Numele este prea lung.',
            'email.required' => 'Te rugăm să completezi adresa de e-mail.',
            'email.email' => 'Introdu o adresă de e-mail validă.',
            'email.max' => 'Adresa de e-mail este prea lungă.',
            'subject.required' => 'Te rugăm să completezi subiectul.',
            'subject.string' => 'Subiectul introdus nu este valid.',
            'subject.max' => 'Subiectul este prea lung.',
            'message.required' => 'Te rugăm să scrii un mesaj.',
            'message.string' => 'Mesajul introdus nu este valid.',
            'message.min' => 'Mesajul pare prea scurt. Te rugăm să adaugi puțin mai mult context.',
            'message.max' => 'Mesajul este prea lung.',
            'privacy_accepted.accepted' => 'Trebuie să fii de acord cu prelucrarea datelor pentru a trimite mesajul.',
        ];
    }
}
