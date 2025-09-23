<?php

namespace houdaslassi\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJobRun extends Model
{
    protected $table = 'queue_job_runs';

    protected $fillable = [
        'uuid',
        'job_class',
        'queue',
        'connection',
        'attempt',
        'status',
        'duration_ms',
        'exception_class',
        'exception_message',
        'stack',
        'payload_excerpt',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];
}
