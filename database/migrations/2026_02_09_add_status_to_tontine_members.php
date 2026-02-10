<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontine_members', function (Blueprint $table) {
            $table->enum('status', ['active', 'paused', 'inactive'])->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('tontine_members', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
