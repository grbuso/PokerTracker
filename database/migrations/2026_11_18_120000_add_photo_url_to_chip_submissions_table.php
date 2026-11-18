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
        Schema::table('chip_submissions', function (Blueprint $table): void {
            $table->text('photo_url')->nullable()->after('chip_counts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chip_submissions', function (Blueprint $table): void {
            $table->dropColumn('photo_url');
        });
    }
};
