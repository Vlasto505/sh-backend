<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id',
        'study_program',
        'study_year',
        'university',
        'skills',
        'cv_path',
        'academic_eligible',
        'bio',
    ];

    protected function casts(): array
    {
        return [
            'skills'            => 'array',
            'academic_eligible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
