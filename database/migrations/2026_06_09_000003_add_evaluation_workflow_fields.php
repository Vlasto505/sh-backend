<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-criterion scores given by an evaluator (spec 10 – kritérium, skóre).
        // Stored as { "Criterion name": score, ... }; the weighted total goes to `score`.
        Schema::table('evaluations', function (Blueprint $table) {
            $table->json('criteria_scores')->nullable()->after('score');
        });

        // Committee message to the applicant (supplement request / decision reason, spec 6.3).
        Schema::table('applications', function (Blueprint $table) {
            $table->text('review_note')->nullable()->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn('criteria_scores');
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};
