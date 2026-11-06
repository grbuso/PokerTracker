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
        Schema::create('chip_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('chips');
            $table->json('chip_counts')->nullable();
            $table->string('source')->default('discord');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique(['round_id', 'user_id', 'recorded_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chip_submissions');
    }
};
