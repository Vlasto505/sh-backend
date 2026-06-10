<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History of bulk messages sent by administrators (spec 6.4).
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by')->constrained('users');
            $table->string('subject');
            $table->text('body');
            $table->string('audience');           // human-readable audience label
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
