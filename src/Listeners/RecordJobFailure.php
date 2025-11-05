<?php

namespace houdaslassi\Vantage\Listeners;

use houdaslassi\Vantage\Notifications\JobFailedNotification;
use houdaslassi\Vantage\Support\Traits\ExtractsRetryOf;
use houdaslassi\Vantage\Support\TagExtractor;
use houdaslassi\Vantage\Support\PayloadExtractor;
use houdaslassi\Vantage\Support\JobPerformanceContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use houdaslassi\Vantage\Models\QueueJobRun;

class RecordJobFailure
{
    use ExtractsRetryOf;

    public function handle(JobFailed $event): void
    {
        // Master switch: if package is disabled, don't track anything
        if (!config('vantage.enabled', true)) {
            return;
        }

        $telemetryEnabled = config('vantage.telemetry.enabled', true);
        $captureCpu = config('vantage.telemetry.capture_cpu', true);

        $memoryEnd = null;
        $memoryPeakEnd = null;
        $cpuDelta = ['user_ms' => null, 'sys_ms' => null];

        if ($telemetryEnabled) {
            $memoryEnd = @memory_get_usage(true) ?: null;
            $memoryPeakEnd = @memory_get_peak_usage(true) ?: null;

            if ($captureCpu && function_exists('getrusage')) {
                $ru = @getrusage();
                if (is_array($ru)) {
                    $userUs = ($ru['ru_utime.tv_sec'] ?? 0) * 1_000_000 + ($ru['ru_utime.tv_usec'] ?? 0);
                    $sysUs  = ($ru['ru_stime.tv_sec'] ?? 0) * 1_000_000 + ($ru['ru_stime.tv_usec'] ?? 0);
                    // We don't have UUID here reliably; try to fetch baseline via job's uuid if available
                    $uuid = method_exists($event->job, 'uuid') && $event->job->uuid() ? (string) $event->job->uuid() : null;
                    if ($uuid) {
                        $baseline = JobPerformanceContext::getBaseline($uuid);
                        if ($baseline) {
                            $cpuDelta['user_ms'] = max(0, (int) round(($userUs - ($baseline['cpu_start_user_us'] ?? 0)) / 1000));
                            $cpuDelta['sys_ms']  = max(0, (int) round(($sysUs  - ($baseline['cpu_start_sys_us'] ?? 0)) / 1000));
                        }
                    }
                }
            }
        }

        $row = QueueJobRun::create([
            'uuid'             => (string) Str::uuid(),
            'job_class'        => method_exists($event->job, 'resolveName')
                ? $event->job->resolveName()
                : get_class($event->job),
            'queue'            => $event->job->getQueue(),
            'connection'       => $event->connectionName ?? null,
            'attempt'          => $event->job->attempts(),
            'status'           => 'failed',
            'exception_class'  => get_class($event->exception),
            'exception_message'=> Str::limit($event->exception->getMessage(), 2000),
            'stack'            => Str::limit($event->exception->getTraceAsString(), 4000),
            'finished_at'      => now(),
            'retried_from_id'  => $this->getRetryOf($event),
            'payload'          => PayloadExtractor::getPayload($event),
            'job_tags'         => TagExtractor::extract($event),
            // telemetry end metrics
            'memory_end_bytes' => $memoryEnd,
            'memory_peak_end_bytes' => $memoryPeakEnd,
            'cpu_user_ms' => $cpuDelta['user_ms'],
            'cpu_sys_ms' => $cpuDelta['sys_ms'],
        ]);

        Log::info('Queue Monitor: Job failed', [
           'id' => $row->id,
           'job_class' => $row->job_class,
           'exception' => $row->exception_class,
        ]);

        if (config('vantage.notify.email') || config('vantage.notify.slack_webhook')) {
            Notification::route('mail', config('vantage.notify.email'))
                ->route('slack', config('vantage.notify.slack_webhook'))
                ->notify(new JobFailedNotification($row));
        }
    }
}

