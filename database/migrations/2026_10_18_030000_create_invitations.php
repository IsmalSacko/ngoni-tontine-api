<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rejoindre par QR code : le bureau affiche l'invitation d'un groupe ou
     * d'une caisse ; qui la scanne demande à entrer, le bureau accepte ou refuse.
     * Un lien qui a trop circulé se désactive.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tontine_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_requests');
        Schema::dropIfExists('invitations');
    }
};
