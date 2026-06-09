<?php

namespace App\Http\Controllers;

use App\Models\Musique;
use Illuminate\Http\Request;

class MusiqueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->user('sanctum')) {
            // L'utilisateur est connecté : il a le droit de voir TOUTES les musiques [1]
            $musiques = Musique::all();
        } else {
            // Aucun utilisateur n'est connecté : on ne renvoie QUE les musiques gratuites [1]
            $musiques = Musique::where('payant', false)->get();
        }

        return response()->json($musiques);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Musique $musique)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Musique $musique)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Musique $musique)
    {
        //
    }
    public function acheterLot(Request $request)
    {
        // 1. On attend un tableau (array) d'IDs dans le body de la requête
        $request->validate([
            'musiques' => 'required|array',
            'musiques.*' => 'integer' // Chaque élément du tableau doit être un entier
        ]);

        $user = $request->user();
        $musiquesDemandes = $request->musiques;

        // 2. On génère un numéro de lot et une date uniques pour tout le panier
        $numLot = rand(10000, 99999);
        $dateAchat = now();
        $musiquesAchetees = 0; // Compteur pour savoir combien ont vraiment été achetées

        // 3. On boucle sur chaque ID reçu
        foreach ($musiquesDemandes as $idMusique) {

            // On cherche la musique (on utilise find() pour ne pas bloquer si un ID est faux)
            $musique = \App\Models\Musique::find($idMusique);

            // Si la musique existe ET qu'elle est payante
            if ($musique && $musique->payant) {

                // On vérifie si l'utilisateur ne l'a pas déjà
                $dejaAchete = \Illuminate\Support\Facades\DB::table('a_acheter')
                    ->where('id_utilisateur', $user->id) // Attention au nom de votre colonne
                    ->where('idMusique', $idMusique)   // Attention au nom de votre colonne
                    ->exists();

                if (!$dejaAchete) {
                    // On insère l'achat avec le NumLot commun
                    \Illuminate\Support\Facades\DB::table('a_acheter')->insert([
                        'id_utilisateur' => $user->id,
                        'idMusique' => $idMusique,
                        'date_' => $dateAchat,
                        'NumLot' => $numLot
                    ]);
                    $musiquesAchetees++;
                }
            }
        }

        // 4. On renvoie une réponse adaptée selon ce qui s'est passé
        if ($musiquesAchetees > 0) {
            return response()->json([
                'message' => "Achat réussi ! $musiquesAchetees musique(s) ajoutée(s) à votre compte.",
                'NumLot' => $numLot
            ], 201);
        } else {
            return response()->json([
                'erreur' => "Aucune musique n'a été achetée (elles sont soit gratuites, soit introuvables, soit vous les possédez déjà)."
            ], 400);
        }
    }

    public function gratuites()
    {
        // On cherche les musiques où la colonne 'payant' est à 0 ou false
        $musiquesGratuites = Musique::where('payant', false)->get();

        return response()->json([
            'titre' => 'Découvrez nos titres 100% gratuits !',
            'nombre_titres' => $musiquesGratuites->count(),
            'musiques' => $musiquesGratuites
        ], 200);
    }
}
