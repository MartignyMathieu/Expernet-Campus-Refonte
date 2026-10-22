<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AssiduiteController;
use App\Http\Controllers\Api\CandidatureController;
use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\ContenuController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmargementController;
use App\Http\Controllers\Api\ExamenController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\PlanningController;
use Illuminate\Support\Facades\Route;

/*
| API REST Expernet — documentée dans public/openapi.json (Swagger UI : /api/docs).
| Toutes les routes sont préfixées par /api.
*/

// ---------- Module public ----------
Route::get('/formations', [CatalogueController::class, 'index']);
Route::get('/formations/facettes', [CatalogueController::class, 'facettes']);
Route::get('/formations/{formation}', [CatalogueController::class, 'show']);
Route::get('/faq', [ContenuController::class, 'faq']);
Route::get('/articles', [ContenuController::class, 'articles']);
Route::get('/articles/{article}', [ContenuController::class, 'article']);
Route::get('/formation-continue', [ContenuController::class, 'formationContinue']);
Route::middleware('throttle:public-forms')->group(function () {
    Route::post('/candidatures', [CandidatureController::class, 'store']);
    Route::post('/contact', [CandidatureController::class, 'contact']);
});

// ---------- ENT (session Sanctum + MFA pour les comptes à privilèges) ----------
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    Route::middleware('mfa')->group(function () {
        Route::get('/planning', [PlanningController::class, 'index'])->middleware('permission:planning.view');

        // Émargement
        Route::post('/seances/{seance}/emargement/ouvrir', [EmargementController::class, 'ouvrir'])->middleware('permission:emargement.manage');
        Route::post('/seances/{seance}/emargement/cloturer', [EmargementController::class, 'cloturer'])->middleware('permission:emargement.manage');
        Route::get('/seances/{seance}/emargement', [EmargementController::class, 'feuille'])->middleware('permission:emargement.manage');
        Route::post('/seances/{seance}/emargement/signer', [EmargementController::class, 'signer'])
            ->middleware(['permission:emargement.sign', 'throttle:signature']);
        Route::get('/emargements/{emargement}/verification', [EmargementController::class, 'verifier'])->middleware('permission:planning.view');
        Route::get('/promotions/{promotion}/attestation', [EmargementController::class, 'attestation'])->middleware('permission:attestation.download');
        Route::get('/promotions/{promotion}/etudiants/{etudiant}/attestation', [EmargementController::class, 'attestation'])->middleware('permission:users.manage');

        // Tableau de bord formateur
        Route::get('/formateur/promotions', [AssiduiteController::class, 'mesPromotions'])->middleware('permission:assiduite.view');
        Route::get('/promotions/{promotion}/assiduite', [AssiduiteController::class, 'promotion'])->middleware('permission:assiduite.view');

        // Notes
        Route::get('/me/notes', [NoteController::class, 'mesNotes'])->middleware('permission:notes.view_own');
        Route::post('/notes', [NoteController::class, 'store'])->middleware('permission:notes.manage');

        // Examens, jurys, compétences
        Route::get('/examens', [ExamenController::class, 'index'])->middleware('permission:examens.view');
        Route::post('/examens', [ExamenController::class, 'store'])->middleware('permission:examens.manage');
        Route::put('/examens/{session}/jury', [ExamenController::class, 'jury'])->middleware('permission:examens.manage');
        Route::get('/examens/{session}/grille', [ExamenController::class, 'grille'])->middleware('permission:competences.evaluate');
        Route::post('/examens/{session}/evaluations', [ExamenController::class, 'evaluer'])->middleware('permission:competences.evaluate');

        // Documents
        Route::get('/promotions/{promotion}/documents', [DocumentController::class, 'index'])->middleware('permission:documents.view');
        Route::post('/promotions/{promotion}/documents', [DocumentController::class, 'store'])->middleware('permission:documents.upload');
        Route::get('/documents/{document}/telechargement', [DocumentController::class, 'telecharger'])->middleware('permission:documents.view');

        // Administration
        Route::prefix('admin')->middleware('permission:users.manage')->group(function () {
            Route::get('/tableau-de-bord', [AdminController::class, 'tableauDeBord']);
            Route::get('/utilisateurs', [AdminController::class, 'utilisateurs']);
            Route::post('/utilisateurs', [AdminController::class, 'creerUtilisateur']);
            Route::patch('/utilisateurs/{user}', [AdminController::class, 'modifierUtilisateur']);
            Route::post('/seances', [AdminController::class, 'creerSeance']);
            Route::get('/candidatures', [CandidatureController::class, 'index']);
        });
    });
});
