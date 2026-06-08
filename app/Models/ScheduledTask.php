<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledTask extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'without_overlapping' => 'boolean',
            'on_one_server' => 'boolean',
            'run_in_maintenance' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ScheduledTaskLog::class);
    }

    public function lastLog(): HasMany
    {
        return $this->hasMany(ScheduledTaskLog::class)->latest('started_at');
    }
}
