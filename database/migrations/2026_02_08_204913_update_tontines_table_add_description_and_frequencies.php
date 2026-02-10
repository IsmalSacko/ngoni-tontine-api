<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            // Ajouter la colonne description
            $table->text('description')->nullable()->after('frequency');

            // Rendre start_date nullable
            $table->date('start_date')->nullable()->change();
        });

        // Modifier l'enum frequency pour ajouter 'quarterly' et 'yearly'
        // Note: MySQL ne permet pas de modifier directement un ENUM,
        // donc on doit utiliser une requête brute
        DB::statement("ALTER TABLE tontines MODIFY COLUMN frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            // Supprimer la colonne description
            $table->dropColumn('description');

            // Remettre start_date en NOT NULL
            $table->date('start_date')->nullable(false)->change();
        });

        // Revenir à l'ancien enum
        DB::statement("ALTER TABLE tontines MODIFY COLUMN frequency ENUM('daily', 'weekly', 'monthly') NOT NULL");
    }
};
