# Queue Monitor (WIP)

A Laravel package for **tracking and monitoring queued jobs**.  
It extends Laravel’s built-in queue system by adding richer job tracking, failure details, notifications, and retry support.

---

## 📦 Features Implemented So Far

### 1. Job Run Tracking
Every job is tracked in a dedicated table: **`queue_job_runs`**.  
When jobs are dispatched, we automatically log:

- `uuid` – unique job identifier
- `job_class` – the job’s class name
- `queue` – which queue the job was dispatched to
- `connection` – which connection was used
- `attempt` – the attempt count
- `status` – `processing`, `processed`, or `failed`
- `started_at`, `finished_at`, `duration_ms` – for timing analysis

👉 This gives you a **per-job execution history**, not just failures.

---

### 2. Failure Recording with Exception Details
When a job fails, we store:

- `exception_class`
- `exception_message`
- `stack` trace
- `finished_at` timestamp

This makes debugging **much faster** compared to Laravel’s default `failed_jobs` table.

---

### 3. Notifications on Failure
When a job fails, the package can send notifications through:

- **Email** (configurable recipient)
- **Slack webhook** (configurable URL)

You can configure this in `config/queue-monitor.php`:

```php
'notify' => [
    'email' => env('QUEUE_MONITOR_NOTIFY_EMAIL'),
    'slack_webhook' => env('QUEUE_MONITOR_NOTIFY_SLACK'),
],
```

---

### 4. Retry Failed Jobs

You can retry a failed job run directly:

```bash
php artisan queue-monitor:retry {run_id}
```

**What this does:**

- Creates a new job instance of the same class
- Marks it as retried from the original run (`retried_from_id`)
- Links retries so you can see which run came from which failure

This is more developer-friendly than the default `queue:retry`, since it's tied into job run history.

---

## 🗂 Database Schema (simplified)

```sql
queue_job_runs
---------------
id
uuid
job_class
queue
connection
attempt
status
started_at
finished_at
duration_ms
exception_class
exception_message
stack
retries
retried_from_id
timestamps
