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

    private function deposer(string $nom, string $mime, int $ko = 10): int
    {
        return $this->actingAs($this->formateur)->postJson("/api/promotions/{$this->promotion->id}/documents", [
            'titre' => $nom, 'fichier' => UploadedFile::fake()->create($nom, $ko, $mime),
        ])->assertCreated()->json('id');
    }

    public function test_apercu_des_documents_dans_le_portail(): void
    {
        $pdf = $this->deposer('cours.pdf', 'application/pdf');
        $image = $this->actingAs($this->formateur)->postJson("/api/promotions/{$this->promotion->id}/documents", [
            'titre' => 'Schéma', 'fichier' => UploadedFile::fake()->image('schema.png', 400, 300),
        ])->json('id');

        $liste = collect($this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/documents")->json())->keyBy('id');
        $this->assertSame('pdf', $liste[$pdf]['apercu']);
        $this->assertSame('image', $liste[$image]['apercu']);

        $r = $this->actingAs($this->etudiant)->get("/api/documents/{$pdf}/apercu")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $r->headers->get('Content-Disposition'));
        $this->actingAs($this->etudiant)->get("/api/documents/{$image}/apercu")->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_un_texte_est_toujours_servi_en_texte_brut(): void
    {
        $id = $this->actingAs($this->formateur)->postJson("/api/promotions/{$this->promotion->id}/documents", [
            'titre' => 'Mémo', 'fichier' => UploadedFile::fake()->createWithContent('memo.txt', "<script>alert(1)</script>\nCommandes Git"),
        ])->assertCreated()->json('id');

        $this->actingAs($this->etudiant)->get("/api/documents/{$id}/apercu")->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_pas_d_apercu_pour_les_formats_bureautiques_ni_hors_promotion(): void
    {
        $docx = $this->deposer('plan.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertNull($this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/documents")->json('0.apercu'));
        $this->actingAs($this->etudiant)->get("/api/documents/{$docx}/apercu")->assertStatus(415);

        $pdf = $this->deposer('cours.pdf', 'application/pdf');
        $this->actingAs($this->etudiantHorsPromo)->get("/api/documents/{$pdf}/apercu")->assertForbidden();
    }
}
