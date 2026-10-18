<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workout extends Model
{
    use SoftDeletes;

    protected $fillable = ['client_id', 'routine_id', 'name', 'started_at', 'ended_at', 'notes'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** Route & outbox merujuk sesi lewat client_id (bisa dibuat offline sebelum punya id server). */
    public function getRouteKeyName(): string
    {
        return 'client_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('sort')->orderBy('id');
    }

    public function sets(): HasManyThrough
    {
        return $this->hasManyThrough(WorkoutSet::class, WorkoutExercise::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function scopeFinished(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }
}
