<?php

namespace Tests\Feature;

use App\Models\Competence;
use App\Models\Emargement;
use App\Models\Formation;
use App\Models\SessionExamen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

class AssiduiteEtExamensTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->construireScenario();
    }

    public function test_taux_d_assiduite_et_alerte_sous_le_seuil(): void
    {
        // 5 séances passées clôturées : l'étudiant présent 5/5, l'autre 3/5 (60 % < 80 %).
        foreach (range(1, 5) as $i) {
            $s = $this->creerSeance(today()->subDays($i));
            $s->forceFill(['cloturee_at' => now()])->save();
            Emargement::create(['seance_id' => $s->id, 'user_id' => $this->etudiant->id, 'statut' => 'present', 'signe_at' => now()]);
            Emargement::create(['seance_id' => $s->id, 'user_id' => $this->autreEtudiant->id, 'statut' => $i <= 3 ? 'present' : 'absent']);
        }

        $r = $this->actingAs($this->formateur)->getJson("/api/promotions/{$this->promotion->id}/assiduite")->assertOk();

        $this->assertEquals(80.0, $r->json('taux_moyen'));
        $this->assertCount(1, $r->json('alertes'));
        $this->assertSame($this->autreEtudiant->id, $r->json('alertes.0.id'));
        $this->assertEquals(60.0, $r->json('alertes.0.taux'));
    }

    public function test_les_seances_non_cloturees_ne_comptent_pas(): void
    {
        $r = $this->actingAs($this->formateur)->getJson("/api/promotions/{$this->promotion->id}/assiduite")->assertOk();
        $this->assertNull($r->json('taux_moyen'));
        $this->assertSame([], $r->json('alertes'));
    }

    private function sessionExamen(): SessionExamen
    {
        return SessionExamen::create(['promotion_id' => $this->promotion->id, 'intitule' => 'AT1', 'debut' => now()->addWeek(), 'fin' => now()->addWeek()->addHours(3)]);
    }

    public function test_composition_du_jury_exactement_un_president(): void
    {
        $session = $this->sessionExamen();
        $url = "/api/examens/{$session->id}/jury";

        $this->actingAs($this->admin)->putJson($url, ['membres' => [
            ['user_id' => $this->formateur->id, 'fonction' => 'membre'], ['user_id' => $this->autreFormateur->id, 'fonction' => 'membre'],
        ]])->assertStatus(422);

        $this->actingAs($this->admin)->putJson($url, ['membres' => [
            ['user_id' => $this->formateur->id, 'fonction' => 'president'], ['user_id' => $this->etudiant->id, 'fonction' => 'membre'],
        ]])->assertStatus(422)->assertJsonValidationErrors('membres.1.user_id');

        $this->actingAs($this->admin)->putJson($url, ['membres' => [
            ['user_id' => $this->formateur->id, 'fonction' => 'president'], ['user_id' => $this->autreFormateur->id, 'fonction' => 'membre'],
        ]])->assertOk()->assertJsonCount(2, 'jury');
    }

    public function test_seul_un_membre_du_jury_saisit_la_grille_de_competences(): void
    {
        $session = $this->sessionExamen();
        $session->jury()->attach($this->formateur->id, ['fonction' => 'president']);
        $competence = Competence::create(['formation_id' => $this->promotion->formation_id, 'code' => 'AT1', 'libelle' => 'Développer une application sécurisée']);
        $payload = ['etudiant_id' => $this->etudiant->id, 'competence_id' => $competence->id, 'niveau' => 'acquis'];

        $this->actingAs($this->autreFormateur)->postJson("/api/examens/{$session->id}/evaluations", $payload)->assertForbidden();
        $this->actingAs($this->formateur)->postJson("/api/examens/{$session->id}/evaluations", $payload)->assertOk();
        $this->actingAs($this->formateur)->postJson("/api/examens/{$session->id}/evaluations", ['niveau' => 'maitrise'] + $payload)->assertOk();

        $this->assertDatabaseCount('evaluations_competence', 1);
        $this->assertDatabaseHas('evaluations_competence', ['niveau' => 'maitrise']);
        $this->actingAs($this->formateur)->getJson("/api/examens/{$session->id}/grille")->assertOk()->assertJsonCount(1, 'competences')->assertJsonCount(2, 'etudiants');
    }

    public function test_un_etudiant_hors_promotion_ne_peut_pas_etre_evalue(): void
    {
        $session = $this->sessionExamen();
        $session->jury()->attach($this->formateur->id, ['fonction' => 'president']);
        $competence = Competence::create(['formation_id' => $this->promotion->formation_id, 'code' => 'AT1', 'libelle' => 'X']);

        $this->actingAs($this->formateur)->postJson("/api/examens/{$session->id}/evaluations", [
            'etudiant_id' => $this->etudiantHorsPromo->id, 'competence_id' => $competence->id, 'niveau' => 'acquis',
        ])->assertStatus(422)->assertJsonValidationErrors('etudiant_id');
    }
}
