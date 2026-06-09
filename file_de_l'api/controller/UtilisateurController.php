<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtilisateurController extends Controller
{
    public function facturation(Request $request)
    {
        $user = $request->user();

        // 1. On interroge la table pivot 'a_acheter' en la joignant avec la table 'musiques'
        // N'oubliez pas d'adapter 'musique_id' et 'date_' si vos colonnes ont un nom légèrement différent.
        $achats = \Illuminate\Support\Facades\DB::table('a_acheter')
            ->join('musiques', 'a_acheter.idMusique', '=', 'musiques.id')
            ->where('a_acheter.id_utilisateur', $user->id)
            ->select('musiques.name as titre', 'musiques.prix', 'a_acheter.date_ as date_achat', 'a_acheter.NumLot')
            ->orderBy('a_acheter.date_', 'desc')
            ->get();

        // 2. Si l'utilisateur n'a encore rien acheté
        if ($achats->isEmpty()) {
            return response()->json([
                'message' => 'Vous n\'avez acheté aucune musique pour le moment.'
            ], 200);
        }

        // 3. On regroupe les achats par NumLot grâce à la méthode très puissante groupBy() des Collections Laravel
        $achatsGroupes = $achats->groupBy('NumLot');

        $factures = [];
        $totalGlobal = 0;

        // 4. On boucle sur chaque "Panier" (lot) pour construire la facture
        foreach ($achatsGroupes as $numLot => $musiquesDuLot) {

            // On fait la somme mathématique du prix brut
            $totalLot = $musiquesDuLot->sum('prix');
            $totalGlobal += $totalLot;

            $factures[] = [
                'numero_lot' => $numLot,
                'date_achat' => $musiquesDuLot->first()->date_achat, // Toutes les musiques du lot ont la même date
                'nombre_musiques' => $musiquesDuLot->count(),
                'total_lot' => $totalLot . ' €',
                'musiques' => $musiquesDuLot->map(function($item) {
                    return [
                        'titre' => $item->titre,
                        // Note : Comme on passe par DB::table, l'accesseur "prix()" de MusiqueModels [3] est ignoré, on ajoute donc le sigle € manuellement
                        'prix' => $item->prix . ' €'
                    ];
                })
            ];
        }

        // 5. On renvoie le reçu complet au format JSON
        return response()->json([
            'utilisateur' => $user->name . ' ' . $user->surname,
            'email' => $user->Email, // Données issues de votre MCD [2]
            'nombre_factures' => $achatsGroupes->count(),
            'total_depense_global' => $totalGlobal . ' €',
            'historique_factures' => $factures
        ], 200);
    }
}
