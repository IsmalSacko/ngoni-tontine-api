<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une tontine devient une « caisse » typée : tontine rotative (le pot va à
     * un membre par tour), cotisation régulière (l'argent reste en caisse) ou
     * collecte pour un projet (avec un objectif). Les caisses communes ont des
     * dépenses, d'où un solde.
     */
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->string('type', 20)->default('rotating')->after('name');
            $table->unsignedBigInteger('target_amount')->nullable()->after('amount');
            $table->date('deadline')->nullable()->after('start_date');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('label');
            $table->text('note')->nullable();
            $table->date('spent_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');

        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn(['type', 'target_amount', 'deadline']);
        });
    }
};
