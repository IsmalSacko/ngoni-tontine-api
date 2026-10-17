<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un groupe — l'association des ressortissants d'un village, un groupe de
     * jeunes — réunit plusieurs caisses autour des mêmes membres : ils sont
     * saisis une fois dans le groupe, puis inscrits dans chacune de ses caisses.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('role', 20)->default('membre');
            $table->timestamps();
        });

        Schema::table('tontines', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
        });

        Schema::table('tontine_members', function (Blueprint $table) {
            $table->foreignId('group_member_id')->nullable()->after('tontine_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tontine_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_member_id');
        });

        Schema::table('tontines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });

        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
