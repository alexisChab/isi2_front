<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Artiste;

class ArtisteController extends Controller
{
    public function index()
    {
        $artistes = Artiste::all();

        return response()->json($artistes, 200);
    }

    /**
     * 2. Affiche un artiste et tous ses albums
     */
    public function albums(string $id)
    {
        // On utilise l'ID de l'artiste.
        // Note : remplacez 'idArtiste' par 'id' si votre clé primaire s'appelle simplement 'id' dans la base.
        $artiste = Artiste::where('id', $id)->firstOrFail();

        // On interroge la table des albums (nommée "name" sur votre MCD) via la clé étrangère
        $albums = \Illuminate\Support\Facades\DB::table('albums')
            ->where('idArtiste', $artiste->id)
            ->get();

        return response()->json([
            'artiste' => $artiste->name,
            'nombre_albums' => $albums->count(),
            'albums' => $albums
        ], 200);
    }
}
