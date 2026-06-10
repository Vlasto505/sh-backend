<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'organization_id',
        'title',
        'summary',
        'technical_spec',
        'product_owner',
        'budget',
        'expectations',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'budget' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
