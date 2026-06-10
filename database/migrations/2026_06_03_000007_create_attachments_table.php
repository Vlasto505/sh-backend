<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->morphs('attachable');
            $table->string('collection', 32)->default('attachment');
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->string('disk', 64)->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
