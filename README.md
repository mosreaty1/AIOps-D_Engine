# PulseGuard

A lightweight PHP service that monitors application metrics, detects behavioral anomalies, and groups related signals into actionable incidents.

## What it does

PulseGuard continuously queries a Prometheus instance, compares live metrics against dynamic baselines, and surfaces problems before they become outages.

**Detection** — flags endpoints showing abnormal latency (>3× baseline), elevated error rates (>10%), or sudden traffic spikes (>2× baseline).

**Correlation** — instead of flooding you with per-endpoint alerts, it groups related signals into a single incident (e.g. `SERVICE_DEGRADATION`, `ERROR_STORM`, `LATENCY_SPIKE`) when more than half the monitored fleet is affected.

**Alerting** — dispatches to console, JSON file, or a webhook endpoint with built-in deduplication (5-minute cooldown per incident type) to suppress repeat noise.

## Requirements

- PHP 8.2+
- Composer
- A running Prometheus instance
- SQLite (default) or MySQL/PostgreSQL

## Setup

```bash
composer run setup       # install deps, copy .env, migrate, build assets
```

Configure your Prometheus URL and monitored endpoints in `.env`, then run:

```bash
php artisan pulse:monitor
```

## Tech stack

Laravel 12 · PHPUnit 11 · Vite · Tailwind CSS

## License

MIT
