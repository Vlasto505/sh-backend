<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = [
        'application_id',
        'evaluator_id',
        'score',
        'criteria_scores',
        'notes',
        'status',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'status'          => EvaluationStatus::class,
            'score'           => 'decimal:2',
            'criteria_scores' => 'array',
            'evaluated_at'    => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
