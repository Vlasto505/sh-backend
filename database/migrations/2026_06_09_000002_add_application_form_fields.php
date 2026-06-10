<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Program A application fields (spec 7.1 – category + qualification stack).
        Schema::table('applications', function (Blueprint $table) {
            $table->string('category')->nullable()->after('proposed_solution');
            $table->string('qualification_stack')->nullable()->after('category');
        });

        // Which required document slot an uploaded file fulfils (spec 7.3).
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('document_type', 64)->nullable()->after('collection');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['category', 'qualification_stack']);
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
