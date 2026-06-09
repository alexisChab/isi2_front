<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\MusiqueController;
use App\Http\Controllers\AbonnementController;
use App\Http\Controllers\ArtsiteController;
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {

    // Génère automatiquement toutes les URLs du CRUD pour la playlist
    Route::apiResource('playlists', PlaylistController::class);

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/musiques', [MusiqueController::class, 'index']);
Route::get('/albums', [\App\Http\Controllers\AlbumController::class, 'index']);
Route::get('/albums/{id}/musiques', [\App\Http\Controllers\AlbumController::class, 'musiques']);
Route::get('/styles', [\App\Http\Controllers\StyleController::class, 'index']);
Route::get('/styles/{libelle}/musiques', [\App\Http\Controllers\StyleController::class, 'musiques']);
Route::get('/musiques/gratuites', [\App\Http\Controllers\MusiqueController::class, 'gratuites']);

Route::middleware('auth:sanctum')->group(function () {
    // Génère les routes GET, POST, PUT, DELETE pour /api/playlists
    Route::apiResource('playlists', PlaylistController::class);
    Route::post('playlists/{id}/musiques', [PlaylistController::class, 'addMusique']);
    Route::post('/musiques/acheter', [\App\Http\Controllers\MusiqueController::class, 'acheterLot']);
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
    Route::get('/factures', [\App\Http\Controllers\UtilisateurController::class, 'facturation']);
    Route::post('/abonnement', [\App\Http\Controllers\AbonnementController::class, 'souscrire']);
    Route::delete('/abonnement', [\App\Http\Controllers\AbonnementController::class, 'desabonner']);
    Route::delete('/playlists/{id}', [\App\Http\Controllers\PlaylistController::class, 'destroy']);
    Route::get('/playlists/{id}/musiques', [\App\Http\Controllers\PlaylistController::class, 'contenu']);
    Route::get('/artistes', [\App\Http\Controllers\ArtisteController::class, 'index']);
    Route::get('/artistes/{id}/albums', [\App\Http\Controllers\ArtisteController::class, 'albums']);

});


