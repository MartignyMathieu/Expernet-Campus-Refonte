<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CandidatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'formation' => ['required', 'string', 'exists:formations,slug'],
            'prenom' => ['required', 'string', 'min:2', 'max:80', "regex:/^[\\pL\\s'\\-]+$/u"],
            'nom' => ['required', 'string', 'min:2', 'max:80', "regex:/^[\\pL\\s'\\-]+$/u"],
            'email' => ['required', 'email:rfc', 'max:255'],
            'telephone' => ['nullable', 'string', 'regex:/^(\\+262|\\+33|0)[1-9](\\s?\\d{2}){4}$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consentement_rgpd' => ['accepted'],
            'site_web' => ['prohibited'],   // pot de miel anti-robots : champ invisible qui doit rester vide
        ];
    }

    public function messages(): array
    {
        return [
            'consentement_rgpd.accepted' => 'Vous devez accepter le traitement de vos données pour candidater.',
            'telephone.regex' => 'Numéro attendu au format 0262 12 34 56 ou +262 692 12 34 56.',
            'prenom.regex' => 'Le prénom ne doit contenir que des lettres.',
            'nom.regex' => 'Le nom ne doit contenir que des lettres.',
        ];
    }
}
