<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('study_program')->nullable();
            $table->unsignedTinyInteger('study_year')->nullable();
            $table->string('university')->nullable()->default('UKF Nitra');
            $table->json('skills')->nullable();
            $table->string('cv_path')->nullable();
            $table->boolean('academic_eligible')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
