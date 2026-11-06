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
        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedInteger('chips_per_player')->nullable()->after('total_chips');
        });

        DB::table('rounds')->orderBy('id')->get()->each(function (object $round): void {
            $players = DB::table('round_user')->where('round_id', $round->id)->count();

            if ($players === 0) {
                return;
            }

            DB::table('rounds')->where('id', $round->id)->update([
                'chips_per_player' => intdiv($round->total_chips, $players),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('chips_per_player');
        });
    }
};
