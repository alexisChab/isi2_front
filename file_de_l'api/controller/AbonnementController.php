<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Abonnement;
class AbonnementController extends Controller
{
    public function souscrire(Request $request)
    {
        $user = $request->user();

        // 1. On vérifie si l'utilisateur possède déjà un abonnement
        // (On utilise la relation 'abonnements()' définie dans votre modèle Utilisateur)
        if ($user->abonnements()->exists()) {
            return response()->json([
                'erreur' => 'Vous possédez déjà un abonnement Premium actif.'
            ], 400);
        }

        // 2. On crée l'abonnement
        // Note : Vérifiez bien les noms de vos colonnes dans votre migration "abonnements"
        $abonnement = new Abonnement();
        $abonnement->utilisateur_id = $user->id;
        $abonnement->date_debut = now(); // Correspond à l'attribut du MCD
        $abonnement->save();

        // 3. On renvoie un message de succès
        return response()->json([
            'message' => 'Félicitations, vous êtes maintenant abonné Premium ! Vous pouvez désormais ajouter n\'importe quelle musique à vos playlists.',
            'date_debut' => $abonnement->date_debut
        ], 201);
    }

    public function desabonner(Request $request)
    {
        $user = $request->user();

        // 1. On vérifie directement avec exists() si l'utilisateur est abonné
        if (!$user->abonnements()->exists()) {
            return response()->json([
                'erreur' => 'Vous n\'avez aucun abonnement Premium actif à résilier.'
            ], 400);
        }

        // 2. LA MAGIE ELOQUENT : On supprime directement depuis la relation.
        // Cela va générer "DELETE FROM abonnement WHERE utilisateur_id = X"
        // Ça ignore totalement le problème de la colonne "id" manquante !
        $user->abonnements()->delete();

        // 3. On renvoie le message de confirmation
        return response()->json([
            'message' => 'Votre abonnement Premium a bien été résilié. Vous n\'avez plus un accès illimité aux musiques payantes.'
        ], 200);
    }
}
