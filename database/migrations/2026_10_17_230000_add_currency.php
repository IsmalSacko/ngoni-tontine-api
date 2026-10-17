<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La diaspora cotise dans la monnaie du pays où elle vit : euro en France,
     * dollar aux États-Unis… Un groupe a sa monnaie, que ses caisses prennent par
     * défaut ; une caisse peut avoir la sienne. Les montants restent entiers.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('currency', 3)->default('XOF')->after('description');
        });

        Schema::table('tontines', function (Blueprint $table) {
            $table->string('currency', 3)->default('XOF')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
