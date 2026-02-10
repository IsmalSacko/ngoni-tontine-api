<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('db');

$exists = \Illuminate\Support\Facades\Schema::hasColumn('tontine_members', 'status');
echo "Column status exists: " . ($exists ? "YES\n" : "NO\n");

// Verifier aussi les migrations enregistrées
$migrations = \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%status%')->get();
echo "Migration status enregistrée: " . ($migrations->count() > 0 ? "YES\n" : "NO\n");
if ($migrations->count() > 0) {
    foreach ($migrations as $m) {
        echo "  - " . $m->migration . " (batch: " . $m->batch . ")\n";
    }
}
