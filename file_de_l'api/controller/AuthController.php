<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\StoreUtilisateurRequest;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. On valide que les champs sont bien présents dans la requête
        $request->validate([
            'Email' => 'required|email',
            'Mot_de_Passe' => 'required',
        ]);
        $utilisateur = Utilisateur::where('Email', $request->Email)->first();

        // 3. On vérifie si l'utilisateur existe ET si le mot de passe est correct
        if (! $utilisateur || ! Hash::check($request->Mot_de_Passe, $utilisateur->Mot_de_Passe)) {
            // Si c'est faux, on renvoie une erreur 401 (Non autorisé)
            return response()->json([
                'erreur' => 'Les identifiants sont incorrects.'
            ], 401);
        }

        // 4. Si c'est bon, on utilise Sanctum pour créer le token opaque
        $token = $utilisateur->createToken('jeton_connexion')->plainTextToken;

        // 5. On renvoie le token à l'application front-end (ISI1)
        return response()->json([
            'message' => 'Connexion réussie !',
            'token' => $token,
            'utilisateur' => $utilisateur->name . ' ' . $utilisateur->surname
        ]);
    }

    public function register(StoreUtilisateurRequest $request)
    {
        // 1. La validation des données (email unique, mots de passe, etc.)
        // est automatiquement gérée par le StoreUtilisateurRequest !

        // 2. Création de l'utilisateur dans la base de données
        $utilisateur = Utilisateur::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'Email' => $request->Email,
            // On hache le mot de passe pour la sécurité
            'Mot_de_Passe' => Hash::make($request->Mot_de_Passe),
        ]);

        // 3. On génère tout de suite un jeton Sanctum pour connecter l'utilisateur automatiquement après son inscription
        $token = $utilisateur->createToken('jeton_connexion')->plainTextToken;

        // 4. On renvoie le résultat au format JSON avec un code 201 (Créé)
        return response()->json([
            'message' => 'Compte créé avec succès !',
            'utilisateur' => $utilisateur->name . ' ' . $utilisateur->surname,
            'token' => $token
        ], 201);
    }
    public function logout(Request $request)
    {
        // Supprime le token actuel de l'utilisateur
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie ! Le token n\'est plus valide.'
        ], 200);
    }

}
