<?php

namespace houdaslassi\QueueMonitor\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use houdaslassi\QueueMonitor\Models\QueueJobRun;

class RecordJobSuccess
{
    public function handle(JobProcessed $event): void
    {
        $jobClass  = method_exists($event->job, 'resolveName') ? $event->job->resolveName() : get_class($event->job);
        $queue     = $event->job->getQueue();
        $connection= $event->connectionName ?? null;

        // Try to find the most recent "processing" record for this job context
        $row = QueueJobRun::where('job_class', $jobClass)
            ->where('queue', $queue)
            ->where('connection', $connection)
            ->where('status', 'processing')
            ->orderByDesc('id')
            ->first();

        if ($row) {
            $row->status = 'processed';
            $row->finished_at = now();
            if ($row->started_at) {
                $row->duration_ms = $row->finished_at->diffInMilliseconds($row->started_at);
            }
            $row->save();
            return;
        }

        // Fallback: if we didn’t catch the start event (worker restart, etc.), create a processed row
        QueueJobRun::create([
            'uuid'        => method_exists($event->job, 'uuid') && $event->job->uuid()
                ? (string) $event->job->uuid()
                : (string) \Illuminate\Support\Str::uuid(),
            'job_class'   => $jobClass,
            'queue'       => $queue,
            'connection'  => $connection,
            'attempt'     => $event->job->attempts(),
            'status'      => 'processed',
            'finished_at' => now(),
        ]);
    }
}
