<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tontine_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->enum('method', ['cash', 'orange', 'wave']);
            $table->foreignId('validated_by')->constrained('users');
            $table->timestamp('paid_at');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
