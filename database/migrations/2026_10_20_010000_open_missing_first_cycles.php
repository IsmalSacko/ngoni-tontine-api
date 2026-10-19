<?php

use App\Models\Tontine;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Ouvre le tour 1 des caisses qui n'en ont aucun : sans lui, rien ne s'encaisse. */
    public function up(): void
    {
        Tontine::query()->whereDoesntHave('cycles')->each(fn (Tontine $t) => $t->ensureFirstCycle());
    }

    public function down(): void {}
};
