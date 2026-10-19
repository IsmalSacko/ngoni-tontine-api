<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'pin_code' => $request->pin_code,
            'password' => bcrypt(Str::random(40)),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Utilisateur enregistré avec succès',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::findByPhone($request->phone);
        if (! $user || $user->pin_code !== $request->pin_code) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function updateProfile(UpdateRequest $request)
    {
        $user = auth()->user(); // Récupérer l'utilisateur authentifié
        $user->update($request->only('name', 'phone', 'email'));

        return response()->json([
            'message' => 'Profil mis à jour avec succès 👍',
            'user' => $user,
        ], 200);
    }

    public function changePin(Request $request)
    {
        $request->validate([
            'current_pin' => 'required|string',
            'new_pin' => 'required|string|min:4',
        ]);

        $user = auth()->user();

        // Vérifier l'ancien PIN (comparaison directe car PINs stockés en clair)
        if ($user->pin_code !== $request->current_pin) {
            return response()->json([
                'message' => 'Le code PIN actuel est incorrect',
            ], 401);
        }

        // Mettre à jour le nouveau PIN (en clair pour cohérence avec le reste)
        $user->update([
            'pin_code' => $request->new_pin,
        ]);

        return response()->json([
            'message' => 'Code PIN modifié avec succès',
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ], 200);

    }

    /**
     * Supprime le compte, les caisses et groupes qu'il a créés (avec leur
     * historique) et sa photo. Le PIN confirme : un téléphone prêté ne suffit pas.
     */
    public function destroy(Request $request)
    {
        $request->validate(['pin_code' => 'required|string']);
        $user = auth()->user();
        if ($user->pin_code !== $request->pin_code) {
            return response()->json(['message' => 'Le code PIN est incorrect'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            foreach (\App\Models\Group::where('created_by', $user->id)->get() as $group) {
                $group->purge();
            }
            foreach ($user->tontines()->get() as $tontine) {
                $tontine->purge();
            }
            $user->tokens()->delete();
            $user->delete();
        });
        if ($user->avatar && file_exists(storage_path('app/public/'.$user->avatar))) {
            unlink(storage_path('app/public/'.$user->avatar));
        }

        return response()->json(['message' => 'Compte supprimé']);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = auth()->user();

        // Supprimer l'ancien avatar si existant
        if ($user->avatar && file_exists(storage_path('app/public/'.$user->avatar))) {
            unlink(storage_path('app/public/'.$user->avatar));
        }

        // Stocker le nouvel avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        // Mettre à jour le chemin dans la base de données
        $user->update(['avatar' => $avatarPath]);

        return response()->json([
            'message' => 'Avatar mis à jour avec succès',
            'user' => $user,
            'avatar_url' => asset('storage/'.$avatarPath),
        ], 200);
    }
}
