<?php

use Illuminate\Support\Facades\Route;

// Application API-only : pas de panel web. La racine renvoie un simple
// statut, la documentation interactive vit sous /api/documentation
// (Swagger UI, voir config/l5-swagger.php).
Route::get('/', fn () => response()->json([
    'service' => config('app.name'),
    'status' => 'ok',
    'documentation' => url('/api/documentation'),
]));
