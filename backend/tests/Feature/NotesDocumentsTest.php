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

    public function test_les_documents_sont_ranges_par_espace(): void
    {
        $url = "/api/promotions/{$this->promotion->id}/documents";
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'Programme', 'espace' => 'pedagogie', 'fichier' => UploadedFile::fake()->create('programme.pdf', 10, 'application/pdf')])->assertCreated();
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'Cours', 'fichier' => UploadedFile::fake()->create('cours.pdf', 10, 'application/pdf')])
            ->assertCreated()->assertJsonPath('espace', 'cours');
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'X', 'espace' => 'inconnu', 'fichier' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);

        $this->actingAs($this->etudiant)->getJson($url.'?espace=pedagogie')->assertOk()->assertJsonCount(1)->assertJsonPath('0.titre', 'Programme');
        $this->actingAs($this->etudiant)->getJson($url)->assertOk()->assertJsonCount(2);
    }

    public function test_droits_de_depot_par_espace(): void
    {
        $url = "/api/promotions/{$this->promotion->id}/documents";
        $fichier = fn () => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        // Administratif : administration seule
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'Règlement', 'espace' => 'administratif', 'fichier' => $fichier()])->assertForbidden();
        $this->actingAs($this->admin)->postJson($url, ['titre' => 'Règlement', 'espace' => 'administratif', 'fichier' => $fichier()])->assertCreated();
        // Rendus : apprenants seuls
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'Rendu', 'espace' => 'rendus', 'fichier' => $fichier()])->assertForbidden();
        $this->actingAs($this->etudiantHorsPromo)->postJson($url, ['titre' => 'Rendu', 'espace' => 'rendus', 'fichier' => $fichier()])->assertForbidden();
        $this->actingAs($this->etudiant)->postJson($url, ['titre' => 'Cours', 'espace' => 'cours', 'fichier' => $fichier()])->assertForbidden();

        $espaces = collect($this->actingAs($this->etudiant)->getJson('/api/documents/espaces')->assertOk()->json())->keyBy('code');
        $this->assertTrue($espaces['rendus']['peut_deposer']);
        $this->assertFalse($espaces['cours']['peut_deposer']);
        $this->assertSame('Rendus des apprenants', collect($this->actingAs($this->formateur)->getJson('/api/documents/espaces')->json())->firstWhere('code', 'rendus')['libelle']);
    }

    public function test_un_rendu_n_est_visible_que_de_son_auteur_et_de_l_equipe(): void
    {
        $url = "/api/promotions/{$this->promotion->id}/documents";
        $id = $this->actingAs($this->etudiant)->postJson($url, [
            'titre' => 'TP 2', 'espace' => 'rendus', 'description' => 'Mon rendu', 'fichier' => UploadedFile::fake()->create('tp2.zip', 50, 'application/zip'),
        ])->assertCreated()->json('id');

        $this->actingAs($this->etudiant)->getJson($url.'?espace=rendus')->assertJsonCount(1);
        $this->actingAs($this->autreEtudiant)->getJson($url.'?espace=rendus')->assertJsonCount(0);
        $this->actingAs($this->autreEtudiant)->get("/api/documents/{$id}/telechargement")->assertForbidden();
        $this->actingAs($this->formateur)->getJson($url.'?espace=rendus')->assertJsonCount(1);
        $this->actingAs($this->formateur)->get("/api/documents/{$id}/telechargement")->assertOk();
    }

    public function test_suppression_par_l_auteur_ou_l_administration(): void
    {
        $id = $this->deposer('cours.pdf', 'application/pdf');
        $this->assertTrue($this->actingAs($this->formateur)->getJson("/api/promotions/{$this->promotion->id}/documents")->json('0.peut_supprimer'));
        $this->assertFalse($this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/documents")->json('0.peut_supprimer'));

        $this->actingAs($this->etudiant)->deleteJson("/api/documents/{$id}")->assertForbidden();
        $this->actingAs($this->formateur)->deleteJson("/api/documents/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('documents', ['id' => $id]);

        $autre = $this->deposer('plan.pdf', 'application/pdf');
        $this->actingAs($this->admin)->deleteJson("/api/documents/{$autre}")->assertNoContent();
    }

    public function test_documents_rattaches_au_module_d_une_seance(): void
    {
        $this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/modules")->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $this->module->id);
        $this->actingAs($this->etudiantHorsPromo)->getJson("/api/promotions/{$this->promotion->id}/modules")->assertForbidden();

        // La séance du planning porte l'identifiant de son module (lien vers les documents)
        $this->actingAs($this->etudiant)->getJson('/api/planning')->assertJsonPath('seances.0.module_id', $this->module->id);

        $url = "/api/promotions/{$this->promotion->id}/documents";
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'TP module', 'espace' => 'exercices', 'module_id' => $this->module->id, 'fichier' => UploadedFile::fake()->create('tp.pdf', 10, 'application/pdf')])
            ->assertCreated()->assertJsonPath('module_id', $this->module->id);

        $autre = \App\Models\Module::create(['formation_id' => \App\Models\Formation::create([
            'filiere_id' => \App\Models\Filiere::first()->id, 'slug' => 'autre', 'intitule' => 'Autre', 'accroche' => 'x', 'niveau_rncp' => 5,
        ])->id, 'code' => 'X', 'intitule' => 'Module étranger']);
        $this->actingAs($this->formateur)->postJson($url, ['titre' => 'X', 'module_id' => $autre->id, 'fichier' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('module_id');
    }
}
