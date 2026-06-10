<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // Evaluation criteria the committee scores against (spec 10 / 15.1).
            // Stored as an array of { name: string, weight: int } objects.
            $table->json('evaluation_criteria')->nullable()->after('max_team_size');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('evaluation_criteria');
        });
    }
};
