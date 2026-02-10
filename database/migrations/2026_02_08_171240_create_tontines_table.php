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
        Schema::create('tontines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->ForeignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tontines');
    }
};
