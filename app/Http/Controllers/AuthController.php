<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // liste des emails autorisés
    private $adminEmails = [
        'test@test.com',
        'admin@multisac.com',
    ];

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // vérifier si email est admin
        if (!in_array($request->email, $this->adminEmails)) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Email ou mot de passe incorrect'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }
}