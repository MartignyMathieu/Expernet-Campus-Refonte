<?php

use App\Http\Controllers\Api\CandidatureController;
use App\Http\Controllers\Api\CatalogueController;
use Illuminate\Support\Facades\Route;

/*
| API REST Expernet — documentée dans public/openapi.json (Swagger UI : /api/docs).
| Toutes les routes sont préfixées par /api.
*/

// ---------- Module public ----------
Route::get('/formations', [CatalogueController::class, 'index']);
Route::get('/formations/facettes', [CatalogueController::class, 'facettes']);
Route::get('/formations/{formation}', [CatalogueController::class, 'show']);
Route::middleware('throttle:public-forms')->group(function () {
    Route::post('/candidatures', [CandidatureController::class, 'store']);
    Route::post('/contact', [CandidatureController::class, 'contact']);
});
