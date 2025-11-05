<?php

namespace houdaslassi\Vantage\Listeners;

use houdaslassi\Vantage\Support\Traits\ExtractsRetryOf;
use houdaslassi\Vantage\Support\TagExtractor;
use houdaslassi\Vantage\Support\PayloadExtractor;
use houdaslassi\Vantage\Support\JobPerformanceContext;
use Illuminate\Queue\Events\JobProcessing;
use houdaslassi\Vantage\Models\QueueJobRun;

class RecordJobStart
{
    use ExtractsRetryOf;

    public function handle(JobProcessing $event): void
    {
        // Master switch: if package is disabled, don't track anything
        if (!config('vantage.enabled', true)) {
            return;
        }

        $uuid = $this->bestUuid($event);

        // Telemetry config & sampling
        $telemetryEnabled = config('vantage.telemetry.enabled', true);
        $sampleRate = (float) config('vantage.telemetry.sample_rate', 1.0);
        $captureCpu = config('vantage.telemetry.capture_cpu', true);

        $memoryStart = null;
        $memoryPeakStart = null;
        $cpuStart = null;

        if ($telemetryEnabled && (mt_rand() / mt_getrandmax()) <= $sampleRate) {
            $memoryStart = @memory_get_usage(true) ?: null;
            $memoryPeakStart = @memory_get_peak_usage(true) ?: null;

            if ($captureCpu && function_exists('getrusage')) {
                $ru = @getrusage();
                if (is_array($ru)) {
                    $userUs = ($ru['ru_utime.tv_sec'] ?? 0) * 1_000_000 + ($ru['ru_utime.tv_usec'] ?? 0);
                    $sysUs  = ($ru['ru_stime.tv_sec'] ?? 0) * 1_000_000 + ($ru['ru_stime.tv_usec'] ?? 0);
                    $cpuStart = ['user_us' => $userUs, 'sys_us' => $sysUs];
                }
            }

            // keep CPU baseline in memory only
            if ($cpuStart) {
                JobPerformanceContext::setBaseline($uuid, [
                    'cpu_start_user_us' => $cpuStart['user_us'],
                    'cpu_start_sys_us' => $cpuStart['sys_us'],
                ]);
            }
        }

        $payloadJson = PayloadExtractor::getPayload($event);
        $jobClass = $this->jobClass($event);
        $queue = $event->job->getQueue();
        $connection = $event->connectionName ?? null;

        // Check if record already exists (by UUID - most reliable)
        $row = QueueJobRun::where('uuid', $uuid)->first();

        // Fallback: try to find by job class, queue, connection (for jobs without UUID)
        if (!$row) {
            $row = QueueJobRun::where('job_class', $jobClass)
                ->where('queue', $queue)
                ->where('connection', $connection)
                ->where('status', 'processing')
                ->where('created_at', '>', now()->subMinute()) // Only very recent
                ->orderByDesc('id')
                ->first();
        }

        if ($row) {
            // Update existing record
            $row->status = 'processing';
            $row->started_at = now();
            $row->finished_at = null; // Reset if it was set
            $row->attempt = $event->job->attempts();
            $row->retried_from_id = $this->getRetryOf($event);
            $row->payload = $payloadJson;
            $row->job_tags = TagExtractor::extract($event);
            $row->memory_start_bytes = $memoryStart;
            $row->memory_peak_start_bytes = $memoryPeakStart;
            // Reset end metrics
            $row->memory_end_bytes = null;
            $row->memory_peak_end_bytes = null;
            $row->memory_peak_delta_bytes = null;
            $row->cpu_user_ms = null;
            $row->cpu_sys_ms = null;
            $row->duration_ms = null;
            $row->exception_class = null;
            $row->exception_message = null;
            $row->stack = null;
            $row->save();
        } else {
            // Create new record
            QueueJobRun::create([
                'uuid'             => $uuid,
                'job_class'        => $jobClass,
                'queue'            => $queue,
                'connection'       => $connection,
                'attempt'          => $event->job->attempts(),
                'status'           => 'processing',
                'started_at'       => now(),
                'retried_from_id'  => $this->getRetryOf($event),
                'payload'          => $payloadJson,
                'job_tags'         => TagExtractor::extract($event),
                // telemetry columns (nullable if disabled/unsampled)
                'memory_start_bytes' => $memoryStart,
                'memory_peak_start_bytes' => $memoryPeakStart,
            ]);
        }
    }

    /**
     * Get best available UUID for the job
     */
    protected function bestUuid(JobProcessing $event): string
    {
        // Try Laravel's built-in UUID
        if (method_exists($event->job, 'uuid') && $event->job->uuid()) {
            return (string) $event->job->uuid();
        }

        // Fallback to job ID
        if (method_exists($event->job, 'getJobId') && $event->job->getJobId()) {
            return (string) $event->job->getJobId();
        }

        // Last resort: generate new UUID
        return (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Get job class name
     */
    protected function jobClass(JobProcessing $event): string
    {
        if (method_exists($event->job, 'resolveName')) {
            return $event->job->resolveName();
        }

        return get_class($event->job);
    }
}

