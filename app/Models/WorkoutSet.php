<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Satu set. Nama kelas WorkoutSet (bukan Set) agar tidak rancu; tabelnya `sets`. */
class WorkoutSet extends Model
{
    use SoftDeletes;

    public const KINDS = ['warmup', 'normal', 'drop', 'failure'];

    protected $table = 'sets';

    protected $fillable = [
        'client_id', 'sort', 'kind', 'weight_g', 'reps', 'duration_s', 'distance_m', 'rpe', 'done_at',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'weight_g' => 'integer',
            'reps' => 'integer',
            'duration_s' => 'integer',
            'distance_m' => 'integer',
            'rpe' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }
}
