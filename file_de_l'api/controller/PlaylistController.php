<?php

Namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $playlists = Playlist::where('idUtilisateur', $request->user()->id)->get();
        return response()->json($playlists);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'Name' => 'required|string|max:100', // Correspond à l'attribut "Name" du MCD
        ]);

        $playlist = Playlist::create([
            'Name' => $request->Name,
            'idUtilisateur' => $request->user()->id // Associé grâce au token !
        ]);

        return response()->json([
            'message' => 'Playlist créée avec succès',
            'playlist' => $playlist
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $playlist = Playlist::where('id', $id)
            ->where('idUtilisateur', $request->user()->id)
            ->firstOrFail();

        return response()->json($playlist);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate(['Name' => 'required|string|max:100']);

        $playlist = Playlist::where('id', $id)
            ->where('idUtilisateur', $request->user()->id)
            ->firstOrFail();

        $playlist->update(['Name' => $request->Name]);

        return response()->json(['message' => 'Playlist mise à jour', 'playlist' => $playlist]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $playlist = Playlist::where('id', $id)
            ->where('idUtilisateur', $request->user()->id)
            ->firstOrFail();

        $playlist->delete();

        return response()->json(['message' => 'Playlist supprimée']);
    }
    public function addMusique(Request $request, string $id)
    {
        // 1. Validation de l'identifiant envoyé
        $request->validate([
            'idMusique' => 'required|integer'
        ]);

        $idMusique = $request->idMusique;
        $user = $request->user();

        // 2. On s'assure que la playlist lui appartient
        $playlist = Playlist::where('id', $id)
            ->where('idUtilisateur', $user->id)
            ->firstOrFail();

        // 3. On récupère la musique pour savoir si elle est payante
        // (D'après votre MCD, la clé primaire est id)
        $musique = \App\Models\Musique::where('id', $idMusique)->firstOrFail();

        // 4. VÉRIFICATION DES RÈGLES DE GESTION
        $canAdd = false;

        // Règle A : L'utilisateur a-t-il un abonnement ?
        // (On utilise la relation 'abonnements' définie précédemment dans le modèle Utilisateur)
        $hasAbonnement = $user->abonnements()->exists();

        if ($hasAbonnement) {
            $canAdd = true; // Il a un abonnement, il a tous les droits
        } else {
            // Règle B : S'il n'a pas d'abonnement, la musique est-elle gratuite ?
            if (!$musique->payant) {
                $canAdd = true; // C'est gratuit, c'est autorisé
            } else {
                // Règle C : La musique est payante et il n'a pas d'abonnement. L'a-t-il achetée ?
                // (Adaptez 'a_acheter' si le nom de votre table pivot est différent)
                $aAchete = \Illuminate\Support\Facades\DB::table('a_acheter')
                    ->where('idUtilisateur', $user->id)
                    ->where('musique_id', $idMusique)
                    ->exists();

                if ($aAchete) {
                    $canAdd = true; // Il l'a achetée, c'est autorisé
                }
            }
        }

        // Si aucune des 3 conditions n'est remplie, on bloque avec une erreur 403
        if (!$canAdd) {
            return response()->json([
                'erreur' => 'Accès refusé : Vous devez posséder un abonnement ou avoir acheté cette musique payante pour l\'ajouter à votre playlist.'
            ], 403);
        }

        // 5. On vérifie si la musique n'est pas DÉJÀ dans la playlist
        $dejaDansPlaylist = \Illuminate\Support\Facades\DB::table('_playlist_contenu')
            ->where('idPlaylist', $playlist->id)
            ->where('idMusique', $idMusique)
            ->exists();

        // Si elle y est déjà, on renvoie une erreur personnalisée au lieu de faire planter SQL
        if ($dejaDansPlaylist) {
            return response()->json([
                'erreur' => 'Cette musique est déjà présente dans votre playlist.'
            ], 409); // Le code HTTP 409 signifie "Conflit"
        }

        // 6. Si elle n'y est pas, l'ajout est autorisé et sécurisé !
        \Illuminate\Support\Facades\DB::table('_playlist_contenu')->insert([
            'idPlaylist' => $playlist->id,
            'idMusique'  => $idMusique
        ]);

        return response()->json([
            'message' => 'Musique ajoutée à la playlist avec succès !'
        ], 201);
    }

    public function contenu(Request $request, string $id)
    {
        $user = $request->user();

        // 1. On cherche la playlist par son ID ET on s'assure qu'elle appartient à l'utilisateur connecté
        // 2. Le with('musiques') charge automatiquement toutes les chansons grâce à votre table pivot _playlist_contenu
        $playlist = \App\Models\Playlist::where('id', $id)
            ->where('idUtilisateur', $user->id)
            ->with('musiques')
            ->firstOrFail();

        // 3. On renvoie un JSON propre et structuré
        return response()->json([
            // Remarque : utilisez $playlist->name ou $playlist->Name selon la casse exacte dans votre base
            'playlist_nom' => $playlist->Name,
            'createur' => $user->name,
            'nombre_pistes' => $playlist->musiques->count(),
            'musiques' => $playlist->musiques
        ], 200);
    }
}
