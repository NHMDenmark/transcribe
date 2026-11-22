<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One run of the sync with the collection database: automatic, from the manual page, or from the command line. */
class SyncRun extends Model
{
    protected $table = 'sync_runs';

    protected $guarded = [];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    /** How long it took, in seconds; null while it runs. */
    public function seconds(): ?int
    {
        return $this->finished_at ? $this->started_at->diffInSeconds($this->finished_at) : null;
    }
}
