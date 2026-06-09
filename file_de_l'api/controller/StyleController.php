<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Style;
class StyleController extends Controller
{
    public function index()
    {
        // On récupère tous les styles dans la base de données
        $styles = Style::all();

        return response()->json($styles, 200);
    }

    public function musiques(string $libelle)
    {
        // 1. On cherche le style grâce à son libellé (qui est sa clé primaire sur votre MCD)
        // Note: Assurez-vous d'avoir bien mis "use App\Models\Style;" tout en haut du fichier
        $style = Style::where('libelle', $libelle)->firstOrFail();

        // 2. On récupère les musiques associées en joignant la table pivot "style_musique"
        $musiques = \Illuminate\Support\Facades\DB::table('style_musique')
            ->join('musiques', 'style_musique.idMusique', '=', 'musiques.id') // On fait le lien entre la table pivot et la musique
            ->where('style_musique.libelle', $style->libelle) // On filtre par le style demandé
            ->select('musiques.*') // On ne récupère que les infos de la chanson
            ->get();

        // 3. On retourne les données au format JSON
        return response()->json([
            'genre_musical' => $style->libelle,
            'nombre_titres' => $musiques->count(),
            'musiques' => $musiques
        ], 200);
    }
}
