<?php

use App\Enums\AssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Program B company assignments / technical specifications (spec 8.2).
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('technical_spec')->nullable();
            $table->string('product_owner')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->text('expectations')->nullable();
            $table->enum('status', array_column(AssignmentStatus::cases(), 'value'))
                  ->default(AssignmentStatus::Draft->value);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
