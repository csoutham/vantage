@extends('vantage::layout')

@section('title', 'Job Details')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Job #{{ $job->id }}</h2>
        <p class="text-sm text-gray-500">{{ $job->job_class }}</p>
    </div>
    <div class="flex gap-2">
        @if($job->status === 'failed')
            <form action="{{ route('vantage.jobs.retry', $job->id) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    🔄 Retry Job
                </button>
            </form>
        @endif
        <a href="{{ route('vantage.jobs') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">
            ← Back to Jobs
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Main Info -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Basic Info -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        @if($job->status === 'processed')
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                ✅ Processed
                            </span>
                        @elseif($job->status === 'failed')
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                ❌ Failed
                            </span>
                        @else
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                ⏳ Processing
                            </span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Queue</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $job->queue ?? 'default' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Connection</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $job->connection ?? 'default' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Attempt</dt>
                    <dd class="mt-1 text-sm text-gray-900">#{{ $job->attempt }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Duration</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $job->formatted_duration }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">UUID</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono text-xs">{{ $job->uuid }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Started At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $job->started_at ? $job->started_at->format('Y-m-d H:i:s') : '-' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Finished At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $job->finished_at ? $job->finished_at->format('Y-m-d H:i:s') : '-' }}
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Tags -->
        @if($job->job_tags)
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Tags</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($job->job_tags as $tag)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                            🏷️ {{ $tag }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Exception Details -->
        @if($job->status === 'failed' && $job->exception_class)
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-red-900 mb-4">❌ Exception Details</h3>
                <div class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Exception Class</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono bg-red-50 p-2 rounded">
                            {{ $job->exception_class }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Message</dt>
                        <dd class="mt-1 text-sm text-gray-900 bg-red-50 p-3 rounded">
                            {{ $job->exception_message }}
                        </dd>
                    </div>

                    @if($job->stack)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 mb-2">Stack Trace</dt>
                            <dd class="mt-1 text-xs text-gray-900 bg-gray-900 text-green-400 p-4 rounded font-mono overflow-x-auto">
                                <pre class="whitespace-pre-wrap">{{ $job->stack }}</pre>
                            </dd>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Payload -->
        @if($job->payload)
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">📦 Payload</h3>
                <pre class="text-xs bg-gray-50 p-4 rounded overflow-x-auto"><code>{{ json_encode($job->decoded_payload, JSON_PRETTY_PRINT) }}</code></pre>
            </div>
        @endif

        <!-- Performance Metrics -->
        @php
            $hasPerformanceData = $job->memory_start_bytes !== null || $job->memory_end_bytes !== null || 
                                  $job->memory_peak_start_bytes !== null || $job->memory_peak_end_bytes !== null ||
                                  $job->cpu_user_ms !== null || $job->cpu_sys_ms !== null;
        @endphp
        @if($hasPerformanceData)
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">⚡ Performance Metrics</h3>
                
                <!-- Memory Metrics -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">💾 Memory Usage</h4>
                    <dl class="grid grid-cols-2 gap-4">
                        @if($job->memory_start_bytes !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Memory Start</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $job->formatted_memory_start }}</dd>
                        </div>
                        @endif

                        @if($job->memory_end_bytes !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Memory End</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $job->formatted_memory_end }}</dd>
                        </div>
                        @endif

                        @if($job->memory_peak_start_bytes !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Peak Memory Start</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $job->formatted_memory_peak_start }}</dd>
                        </div>
                        @endif

                        @if($job->memory_peak_end_bytes !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Peak Memory End</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $job->formatted_memory_peak_end }}</dd>
                        </div>
                        @endif

                        @if($job->memory_peak_delta_bytes !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Peak Memory Delta</dt>
                            <dd class="mt-1 text-sm font-medium {{ $job->memory_peak_delta_bytes >= 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $job->formatted_memory_peak_delta }}
                            </dd>
                        </div>
                        @endif

                        @if($job->memory_delta_bytes !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Memory Delta</dt>
                            <dd class="mt-1 text-sm font-medium {{ $job->memory_delta_bytes >= 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $job->formatted_memory_delta }}
                            </dd>
                        </div>
                        @endif
                    </dl>

                    <!-- Memory Usage Visual Indicator -->
                    @if($job->memory_peak_end_bytes !== null && $job->memory_start_bytes !== null)
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Start: {{ $job->formatted_memory_start }}</span>
                            <span>Peak: {{ $job->formatted_memory_peak_end }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            @php
                                $usagePercent = min(100, ($job->memory_peak_end_bytes / max($job->memory_start_bytes, 1)) * 100);
                                $colorClass = $usagePercent > 150 ? 'bg-red-500' : ($usagePercent > 120 ? 'bg-yellow-500' : 'bg-green-500');
                            @endphp
                            <div class="{{ $colorClass }} h-2 rounded-full" style="width: {{ min(100, $usagePercent) }}%"></div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- CPU Metrics -->
                @if($job->cpu_user_ms !== null || $job->cpu_sys_ms !== null)
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">⚡ CPU Time</h4>
                    <dl class="grid grid-cols-2 gap-4">
                        @if($job->cpu_user_ms !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">User Time</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $job->formatted_cpu_user }}</dd>
                        </div>
                        @endif

                        @if($job->cpu_sys_ms !== null)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">System Time</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $job->formatted_cpu_sys }}</dd>
                        </div>
                        @endif

                        @if($job->cpu_total_ms !== null)
                        <div class="col-span-2">
                            <dt class="text-xs font-medium text-gray-500">Total CPU Time</dt>
                            <dd class="mt-1 text-sm font-bold text-indigo-600">{{ $job->formatted_cpu_total }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Retry Chain -->
        @php
            $retries = $job->retries()->get();
        @endphp
        @if(!empty($retryChain) || $retries->isNotEmpty())
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">🔄 Retry Chain</h3>
                
                @if(!empty($retryChain))
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-2">Original Job:</p>
                        @foreach($retryChain as $retry)
                            <a href="{{ route('vantage.jobs.show', $retry->id) }}" 
                               class="block text-sm text-indigo-600 hover:text-indigo-800 mb-1">
                                #{{ $retry->id }} - {{ $retry->status }} ({{ $retry->created_at->diffForHumans() }})
                            </a>
                        @endforeach
                        <div class="text-sm text-gray-700 font-medium mt-1">
                            → #{{ $job->id }} (Current)
                        </div>
                    </div>
                @endif

                @if($retries->isNotEmpty())
                    <div>
                        <p class="text-sm text-gray-500 mb-2">Retried As:</p>
                        @foreach($retries as $retry)
                            <a href="{{ route('vantage.jobs.show', $retry->id) }}" 
                               class="block text-sm text-indigo-600 hover:text-indigo-800 mb-1">
                                #{{ $retry->id }} - {{ $retry->status }} ({{ $retry->created_at->diffForHumans() }})
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- Quick Stats -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Quick Stats</h3>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Created</dt>
                    <dd class="text-sm text-gray-900">{{ $job->created_at->diffForHumans() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Updated</dt>
                    <dd class="text-sm text-gray-900">{{ $job->updated_at->diffForHumans() }}</dd>
                </div>
                @if($job->duration_ms)
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Duration</dt>
                        <dd class="text-sm text-gray-900">{{ $job->formatted_duration }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection

