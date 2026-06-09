<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Album;
class AlbumController extends Controller
{
    public function index()
    {
        // On récupère la totalité des albums existants
        $albums = Album::all();

        return response()->json($albums, 200);
    }

    public function musiques(string $id)
    {
        // 1. On récupère l'album (adaptez 'id' si la clé primaire s'appelle autrement)
        $album = \App\Models\Album::where('id', $id)->firstOrFail();

        // 2. On récupère les musiques liées.
        // D'après votre modèle, la clé étrangère est 'id_album'
        $musiques = \App\Models\Musique::where('id_album', $album->id)->get();

        return response()->json([
            'album' => $album->Name, // ou $album->Name selon votre base
            'nombre_pistes' => $musiques->count(),
            'musiques' => $musiques
        ], 200);
    }
}
