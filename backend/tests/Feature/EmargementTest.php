<?php

namespace Tests\Feature;

use App\Models\Emargement;
use App\Services\EmargementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

/** Fonctionnalité critique : émargement numérique (ouverture, signature, clôture, preuve). */
class EmargementTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->construireScenario();
    }

    private function ouvrir(): string
    {
        return $this->actingAs($this->formateur)
            ->postJson("/api/seances/{$this->seance->id}/emargement/ouvrir")
            ->assertOk()->json('code');
    }

    private function signer($etudiant, string $code, ?string $signature = null)
    {
        return $this->actingAs($etudiant)->postJson("/api/seances/{$this->seance->id}/emargement/signer", [
            'code' => $code, 'signature' => $signature ?? $this->signaturePng(),
        ]);
    }

    public function test_le_formateur_ouvre_l_emargement_et_obtient_un_code_a_6_chiffres(): void
    {
        $code = $this->ouvrir();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->seance->refresh();
        $this->assertTrue($this->seance->fenetreOuverte());
        $this->assertNotSame($code, $this->seance->code_emargement_hash, 'Le code doit être stocké haché, jamais en clair.');
    }

    public function test_un_autre_formateur_ne_peut_pas_ouvrir_la_seance(): void
    {
        $this->actingAs($this->autreFormateur)->postJson("/api/seances/{$this->seance->id}/emargement/ouvrir")
            ->assertStatus(422)->assertJsonPath('code', 'NOT_SEANCE_OWNER');
    }

    public function test_l_emargement_ne_s_ouvre_que_le_jour_de_la_seance(): void
    {
        $demain = $this->creerSeance(today()->addDay());

        $this->actingAs($this->formateur)->postJson("/api/seances/{$demain->id}/emargement/ouvrir")
            ->assertStatus(422)->assertJsonPath('code', 'NOT_SEANCE_DAY');
    }

    public function test_un_etudiant_ne_peut_pas_ouvrir_l_emargement(): void
    {
        $this->actingAs($this->etudiant)->postJson("/api/seances/{$this->seance->id}/emargement/ouvrir")->assertForbidden();
    }

    public function test_l_etudiant_inscrit_signe_avec_le_bon_code_et_obtient_une_preuve(): void
    {
        $code = $this->ouvrir();

        $reponse = $this->signer($this->etudiant, $code)->assertCreated()
            ->assertJsonPath('statut', 'present')
            ->assertJsonStructure(['statut', 'signe_at', 'empreinte']);

        $emargement = Emargement::firstOrFail();
        $this->assertSame($this->etudiant->id, $emargement->user_id);
        $this->assertSame(64, strlen($reponse->json('empreinte')));
        Storage::disk('local')->assertExists($emargement->signature_path);
        $this->assertNotNull($emargement->ip);
    }

    public function test_un_mauvais_code_est_refuse(): void
    {
        $code = $this->ouvrir();
        $faux = $code === '123456' ? '654321' : '123456';

        $this->signer($this->etudiant, $faux)->assertStatus(422)->assertJsonPath('code', 'BAD_CODE');
        $this->assertDatabaseCount('emargements', 0);
    }

    public function test_impossible_de_signer_si_la_fenetre_n_est_pas_ouverte(): void
    {
        $this->signer($this->etudiant, '123456')->assertStatus(422)->assertJsonPath('code', 'WINDOW_CLOSED');
    }

    public function test_impossible_de_signer_apres_expiration_de_la_fenetre(): void
    {
        $code = $this->ouvrir();
        $this->travel(config('expernet.emargement.duree_minutes') + 1)->minutes();

        $this->signer($this->etudiant, $code)->assertStatus(422)->assertJsonPath('code', 'WINDOW_CLOSED');
    }

    public function test_un_etudiant_d_une_autre_promotion_ne_peut_pas_signer(): void
    {
        $code = $this->ouvrir();

        $this->signer($this->etudiantHorsPromo, $code)->assertStatus(422)->assertJsonPath('code', 'NOT_ENROLLED');
    }

    public function test_on_ne_peut_pas_signer_deux_fois(): void
    {
        $code = $this->ouvrir();
        $this->signer($this->etudiant, $code)->assertCreated();

        $this->signer($this->etudiant, $code)->assertStatus(422)->assertJsonPath('code', 'ALREADY_SIGNED');
        $this->assertDatabaseCount('emargements', 1);
    }

    public function test_une_signature_qui_n_est_pas_un_png_est_refusee(): void
    {
        $code = $this->ouvrir();

        $this->signer($this->etudiant, $code, 'data:image/png;base64,'.base64_encode('<?php echo 1; ?>'))
            ->assertStatus(422)->assertJsonPath('code', 'BAD_SIGNATURE');
    }

    public function test_la_signature_est_validee_par_le_form_request(): void
    {
        $this->ouvrir();

        $this->actingAs($this->etudiant)->postJson("/api/seances/{$this->seance->id}/emargement/signer", ['code' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['code', 'signature']);
    }

    public function test_la_cloture_marque_les_non_signataires_absents_et_ferme_la_fenetre(): void
    {
        $code = $this->ouvrir();
        $this->signer($this->etudiant, $code)->assertCreated();

        $this->actingAs($this->formateur)->postJson("/api/seances/{$this->seance->id}/emargement/cloturer")
            ->assertOk()->assertJsonPath('absents', 1);

        $this->assertDatabaseHas('emargements', ['user_id' => $this->autreEtudiant->id, 'statut' => 'absent']);
        $this->signer($this->autreEtudiant, $code)->assertStatus(422)->assertJsonPath('code', 'WINDOW_CLOSED');
        $this->actingAs($this->formateur)->postJson("/api/seances/{$this->seance->id}/emargement/ouvrir")
            ->assertStatus(422)->assertJsonPath('code', 'SEANCE_CLOSED');
    }

    public function test_l_integrite_est_verifiable_et_toute_alteration_est_detectee(): void
    {
        $code = $this->ouvrir();
        $this->signer($this->etudiant, $code)->assertCreated();
        $emargement = Emargement::firstOrFail();

        $this->actingAs($this->etudiant)->getJson("/api/emargements/{$emargement->id}/verification")
            ->assertOk()->assertJsonPath('integre', true);

        // Falsification de l'horodatage en base : l'empreinte ne correspond plus.
        $emargement->forceFill(['signe_at' => $emargement->signe_at->subHour()])->save();
        $this->assertFalse(app(EmargementService::class)->verifier($emargement->fresh()));
    }

    public function test_la_feuille_est_reservee_au_formateur_de_la_seance(): void
    {
        $this->actingAs($this->formateur)->getJson("/api/seances/{$this->seance->id}/emargement")->assertOk()->assertJsonCount(2, 'lignes');
        $this->actingAs($this->autreFormateur)->getJson("/api/seances/{$this->seance->id}/emargement")->assertForbidden();
    }

    public function test_l_attestation_pdf_est_generee_pour_l_etudiant_lui_meme(): void
    {
        $code = $this->ouvrir();
        $this->signer($this->etudiant, $code)->assertCreated();

        $reponse = $this->actingAs($this->etudiant)->get("/api/promotions/{$this->promotion->id}/attestation");
        $reponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());

        // Un étudiant ne peut pas télécharger l'attestation d'un autre.
        $this->actingAs($this->etudiant)->get("/api/promotions/{$this->promotion->id}/etudiants/{$this->autreEtudiant->id}/attestation")->assertForbidden();
    }
}
