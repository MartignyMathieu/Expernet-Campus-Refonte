<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

/** Notes (saisie et consultation) et espace documentaire cloisonné par promotion. */
class NotesDocumentsTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->construireScenario();
    }

    public function test_un_formateur_ne_note_que_les_modules_qu_il_enseigne(): void
    {
        $note = ['module_id' => $this->module->id, 'etudiant_id' => $this->etudiant->id, 'intitule' => 'TP', 'valeur' => 15, 'date_evaluation' => today()->toDateString()];

        $this->actingAs($this->autreFormateur)->postJson('/api/notes', $note)->assertForbidden();
        $this->actingAs($this->formateur)->postJson('/api/notes', $note)->assertCreated();
        $this->actingAs($this->formateur)->postJson('/api/notes', ['valeur' => 21] + $note)->assertStatus(422);

        $this->actingAs($this->etudiant)->getJson('/api/me/notes')->assertOk()->assertJsonPath('moyenne', 15);
    }

    public function test_documents_deposes_par_le_formateur_et_cloisonnes_par_promotion(): void
    {
        $this->actingAs($this->etudiant)->postJson("/api/promotions/{$this->promotion->id}/documents", ['titre' => 'x', 'fichier' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();

        $id = $this->actingAs($this->formateur)->postJson("/api/promotions/{$this->promotion->id}/documents", [
            'titre' => 'Support cours', 'fichier' => UploadedFile::fake()->create('cours.pdf', 100, 'application/pdf'),
        ])->assertCreated()->json('id');

        $this->actingAs($this->formateur)->postJson("/api/promotions/{$this->promotion->id}/documents", [
            'titre' => 'Script', 'fichier' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertStatus(422);

        $this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/documents")->assertOk()->assertJsonCount(1);
        $this->actingAs($this->etudiant)->get("/api/documents/{$id}/telechargement")->assertOk();
        $this->actingAs($this->etudiantHorsPromo)->get("/api/documents/{$id}/telechargement")->assertForbidden();
    }
}
