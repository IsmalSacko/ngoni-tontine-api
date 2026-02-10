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
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->foreignId('beneficiary_id')->nullable()->constrained('tontine_members');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
