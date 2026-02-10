<?php

use Illuminate\Support\Facades\Route;

// Route d'accueil : affiche la vue home contenant les règles de confidentialité
Route::get('/', function () {
    return view('home');
});
