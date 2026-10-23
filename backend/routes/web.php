<?php

use Illuminate\Support\Facades\Route;

// Documentation interactive de l'API (Swagger UI) - lit public/openapi.json
Route::view('/api/docs', 'swagger');

Route::get('/', fn () => response()->json(['application' => 'Expernet ENT API', 'documentation' => url('/api/docs')]));
