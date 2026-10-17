<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La fréquence n'est plus une liste fermée : « une seule fois » (une part
     * fixe, versée petit à petit) et « autre » (tous les N jours, semaines, mois
     * ou ans) s'ajoutent aux fréquences habituelles.
     */
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->string('frequency', 20)->default('monthly')->change();
            $table->unsignedSmallInteger('period_count')->nullable()->after('frequency');
            $table->string('period_unit', 10)->nullable()->after('period_count');
        });

        // L'éphémère type « participation par membre » devient une cotisation
        // payable une seule fois.
        DB::table('tontines')->where('type', 'share')->update(['type' => 'periodic', 'frequency' => 'once']);
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn(['period_count', 'period_unit']);
        });
    }
};
