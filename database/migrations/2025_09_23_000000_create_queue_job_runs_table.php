<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('queue_job_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->string('job_class')->index();
            $table->string('queue')->nullable()->index();
            $table->string('connection')->nullable()->index();
            $table->unsignedInteger('attempt')->default(0);
            $table->enum('status', ['processing','processed','failed'])->index();

            // extra details for observability
            $table->unsignedBigInteger('duration_ms')->nullable()->index();
            $table->string('exception_class')->nullable()->index();
            $table->text('exception_message')->nullable();
            $table->text('stack')->nullable();
            $table->text('payload_excerpt')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_job_runs');
    }
};
