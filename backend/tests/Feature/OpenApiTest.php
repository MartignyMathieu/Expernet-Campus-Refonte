<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Garantit que la documentation OpenAPI reste synchronisée avec les routes réellement exposées. */
class OpenApiTest extends TestCase
{
    public function test_le_document_openapi_est_valide_et_en_version_3(): void
    {
        $spec = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringStartsWith('3.0', $spec['openapi']);
        $this->assertNotEmpty($spec['paths']);
    }

    public function test_chaque_route_metier_de_l_api_est_documentee(): void
    {
        $spec = json_decode(file_get_contents(public_path('openapi.json')), true);
        $manquantes = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_starts_with($route->uri(), 'api/') || ! str_starts_with($action, 'App\\')) {
                continue;
            }
            // Les noms de paramètres peuvent différer entre Laravel et la doc : on compare les gabarits.
            $gabarit = preg_replace('/\{[^}]+\}/', '{}', '/'.$route->uri());
            $documentees = array_map(fn ($p) => preg_replace('/\{[^}]+\}/', '{}', $p), array_keys($spec['paths']));
            $index = array_search($gabarit, $documentees, true);

            foreach (array_diff($route->methods(), ['HEAD']) as $methode) {
                if ($index === false || ! isset($spec['paths'][array_keys($spec['paths'])[$index]][strtolower($methode)])) {
                    $manquantes[] = "$methode /{$route->uri()}";
                }
            }
        }

        $this->assertSame([], $manquantes, 'Routes non documentées dans openapi.json');
    }

    public function test_swagger_ui_est_servi(): void
    {
        $this->get('/api/docs')->assertOk()->assertSee('openapi.json');
    }
}
