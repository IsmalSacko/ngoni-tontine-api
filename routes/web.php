<?php

use Illuminate\Support\Facades\Route;

// Route d'accueil : affiche la vue home contenant les règles de confidentialité
Route::get('/', function () {
    return view('home');
});

// Lien d'invitation (QR code) : ouvre l'app si elle est installée, sinon les boutiques.
Route::get('/rejoindre/{token}', function (string $token) {
    $invitation = \App\Models\Invitation::where('token', $token)->where('active', true)->first();
    $target = $invitation?->target();

    return view('rejoindre', [
        'token' => $token,
        'name' => $target?->name,
        'kind' => $invitation?->group_id ? 'group' : 'caisse',
        'package' => config('services.tontine_app.android_package'),
        'playStore' => 'https://play.google.com/store/apps/details?id='.config('services.tontine_app.android_package'),
    ]);
});

// Liens qui ouvrent l'app directement (Android App Links, iOS Universal Links).
Route::get('/.well-known/assetlinks.json', fn () => response()->json([[
    'relation' => ['delegate_permission/common.handle_all_urls'],
    'target' => [
        'namespace' => 'android_app',
        'package_name' => config('services.tontine_app.android_package'),
        'sha256_cert_fingerprints' => config('services.tontine_app.android_fingerprints'),
    ],
]]));

Route::get('/.well-known/apple-app-site-association', fn () => response()->json([
    'applinks' => [
        'apps' => [],
        'details' => [[
            'appID' => config('services.tontine_app.ios_app_id'),
            'paths' => ['/rejoindre/*'],
        ]],
    ],
]));
