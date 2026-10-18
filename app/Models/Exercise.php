<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exercise extends Model
{
    protected $fillable = [
        'name',
        'muscle_group',
        'equipment',
        'kind',
        'notes',
        'archived_at',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'sort' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Gerakan yang tidak diarsipkan. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
