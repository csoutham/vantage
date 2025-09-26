<?php

namespace houdaslassi\QueueMonitor\Console\Commands;

use Illuminate\Console\Command;
use houdaslassi\QueueMonitor\Models\QueueJobRun;

class RetryFailedJob extends Command
{
    protected $signature = 'queue-monitor:retry {run_id}';
    protected $description = 'Retry a failed job run by ID';

    public function handle(): int
    {
        $run = QueueJobRun::find($this->argument('run_id'));

        if (! $run || $run->status !== 'failed') {
            $this->error('Job run not found or not failed.');
            return self::FAILURE;
        }

        $jobClass = $run->job_class;

        if (! class_exists($jobClass)) {
            $this->error("Job class {$jobClass} not found.");
            return self::FAILURE;
        }

        // For now, we just instantiate with no args (later: restore full payload)
        $job = new $jobClass();
        $job->queueMonitorRetryOf = $run->id;

        dispatch($job)
            ->onQueue($run->queue)
            ->onConnection($run->connection);

        $job->retried_from_id = $run->id;

        $this->info("Retried job {$jobClass} from run #{$run->id}");

        return self::SUCCESS;
    }
}
