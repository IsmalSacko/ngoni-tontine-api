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
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_id']);
        });

        DB::statement('ALTER TABLE cycles MODIFY beneficiary_id BIGINT UNSIGNED NULL');

        Schema::table('cycles', function (Blueprint $table) {
            $table->foreign('beneficiary_id')->references('id')->on('tontine_members');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_id']);
        });

        DB::statement('ALTER TABLE cycles MODIFY beneficiary_id BIGINT UNSIGNED NOT NULL');

        Schema::table('cycles', function (Blueprint $table) {
            $table->foreign('beneficiary_id')->references('id')->on('tontine_members');
        });
    }
};
