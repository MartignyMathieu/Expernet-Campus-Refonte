<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Formation;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    public function test_le_catalogue_public_liste_les_formations_publiees(): void
    {
        $this->getJson('/api/formations')->assertOk()->assertJsonCount(8, 'data')
            ->assertJsonStructure(['data' => [['slug', 'intitule', 'niveau_rncp', 'niveau_libelle', 'code_rncp', 'eligible_cpf', 'filiere']]]);
    }

    public function test_filtre_par_filiere(): void
    {
        $this->getJson('/api/formations?filiere=marketing')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_filtre_par_niveau_rncp(): void
    {
        $donnees = $this->getJson('/api/formations?niveau=7')->assertOk()->json('data');
        $this->assertNotEmpty($donnees);
        $this->assertSame([7], array_values(array_unique(array_column($donnees, 'niveau_rncp'))));
    }

    public function test_filtre_cpf(): void
    {
        Formation::where('slug', 'cda-bac4')->update(['eligible_cpf' => false]);
        $slugs = array_column($this->getJson('/api/formations?cpf=1')->json('data'), 'slug');
        $this->assertNotContains('cda-bac4', $slugs);
        $this->assertSame(['cda-bac4'], array_column($this->getJson('/api/formations?cpf=0')->json('data'), 'slug'));
    }

    public function test_filtres_combines_et_recherche_textuelle(): void
    {
        $this->getJson('/api/formations?q=cyber&filiere=informatique&niveau=7')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'hacking-cybersecurite-bac5');
    }

    public function test_un_filtre_invalide_est_rejete(): void
    {
        $this->getJson('/api/formations?niveau=9')->assertStatus(422);
        $this->getJson('/api/formations?filiere=inconnue')->assertStatus(422);
    }

    public function test_la_fiche_detaillee_contient_les_rubriques_obligatoires(): void
    {
        $this->getJson('/api/formations/cda-bac4')->assertOk()
            ->assertJsonPath('data.code_rncp', 'RNCP37873')
            ->assertJsonStructure(['data' => ['objectifs', 'prerequis', 'modalites', 'debouches', 'campus']]);
    }

    public function test_une_formation_non_publiee_est_invisible(): void
    {
        Formation::where('slug', 'cda-bac4')->update(['publiee' => false]);
        $this->getJson('/api/formations/cda-bac4')->assertNotFound();
        $this->getJson('/api/formations')->assertJsonCount(7, 'data');
    }

    public function test_les_facettes_sont_exposees(): void
    {
        $this->getJson('/api/formations/facettes')->assertOk()->assertJsonCount(Filiere::count(), 'filieres')->assertJsonCount(3, 'niveaux');
    }
}
