<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un membre déclare avoir payé ; le bureau valide (le paiement est alors
     * enregistré) ou refuse, avec un motif. Rien n'entre en caisse sans lui.
     */
    public function up(): void
    {
        Schema::create('payment_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tontine_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_declarations');
    }
};
