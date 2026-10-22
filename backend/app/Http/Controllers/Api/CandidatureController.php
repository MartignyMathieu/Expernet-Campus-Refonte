<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CandidatureRequest;
use App\Http\Requests\ContactRequest;
use App\Models\Candidature;
use App\Models\Formation;
use App\Models\MessageContact;

class CandidatureController extends Controller
{
    public function store(CandidatureRequest $request)
    {
        $donnees = $request->validated();

        $candidature = Candidature::create([
            'formation_id' => Formation::where('slug', $donnees['formation'])->value('id'),
            'prenom' => $donnees['prenom'],
            'nom' => $donnees['nom'],
            'email' => $donnees['email'],
            'telephone' => $donnees['telephone'] ?? null,
            'message' => $donnees['message'] ?? null,
            'consentement_rgpd_at' => now(),
        ]);

        return response()->json(['message' => 'Candidature enregistrée. Notre équipe vous recontacte sous 72 h.', 'id' => $candidature->id], 201);
    }

    public function contact(ContactRequest $request)
    {
        $donnees = $request->validated();
        MessageContact::create([
            'nom' => $donnees['nom'],
            'email' => $donnees['email'],
            'sujet' => $donnees['sujet'],
            'message' => $donnees['message'],
            'consentement_rgpd_at' => now(),
        ]);

        return response()->json(['message' => 'Message envoyé.'], 201);
    }

    /** Back-office : liste des candidatures (admin). */
    public function index()
    {
        return Candidature::with('formation:id,intitule,slug')->latest()->paginate(20);
    }
}
