<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertingService
{
    /** @var array<string, int> */
    protected array $recentAlerts = [];

    /**
     * Dispatches an alert for the given incident if not recently dispatched.
     */
    public function dispatch(Incident $incident, Command $console = null): void
    {
        $dedupKey = $this->generateDedupKey($incident);

        // Deduplication: suppress if alerted in the last 5 minutes
        $now = time();
        if (isset($this->recentAlerts[$dedupKey])) {
            if (($now - $this->recentAlerts[$dedupKey]) < 300) {
                return; // Suppress duplicate
            }
        }

        $this->recentAlerts[$dedupKey] = $now;

        $this->alertConsole($incident, $console);
        $this->alertJson($incident);
        $this->alertWebhook($incident);
    }

    protected function generateDedupKey(Incident $incident): string
    {
        sort($incident->affected_endpoints);
        $endpointsString = implode(',', $incident->affected_endpoints);
        return md5($incident->incident_type . '|' . $endpointsString);
    }

    protected function alertConsole(Incident $incident, ?Command $console): void
    {
        if (!$console) {
            return;
        }

        $message = sprintf(
            "[%s] %s ALERT: %s - %s",
            $incident->detected_at,
            strtoupper($incident->severity),
            $incident->incident_type,
            $incident->summary
        );

        if ($incident->severity === 'critical') {
            $console->error($message);
        } elseif ($incident->severity === 'high') {
            $console->error($message); // Laravel console error is red
        } elseif ($incident->severity === 'medium') {
            $console->warn($message);
        } else {
            $console->info($message);
        }
    }

    protected function alertJson(Incident $incident): void
    {
        Log::channel('single')->info('PULSEGUARD_ALERT', [
            'incident_id' => $incident->incident_id,
            'incident_type' => $incident->incident_type,
            'severity' => $incident->severity,
            'timestamp' => $incident->detected_at,
            'summary' => $incident->summary,
        ]);
    }

    protected function alertWebhook(Incident $incident): void
    {
        $webhookUrl = config('services.pulseguard.webhook_url');
        if (!$webhookUrl) {
            return;
        }

        try {
            Http::timeout(3)->post($webhookUrl, [
                'incident_id' => $incident->incident_id,
                'incident_type' => $incident->incident_type,
                'severity' => $incident->severity,
                'timestamp' => $incident->detected_at,
                'summary' => $incident->summary,
            ]);
        } catch (\Exception $e) {
            // Silently fail webhook if configured but unreachable
            Log::warning('Failed to dispatch PulseGuard webhook: ' . $e->getMessage());
        }
    }

    /**
     * Cleans up the deduplication cache slowly over time.
     */
    public function cleanup(): void
    {
        $now = time();
        foreach ($this->recentAlerts as $key => $timestamp) {
            if (($now - $timestamp) >= 300) {
                unset($this->recentAlerts[$key]);
            }
        }
    }
}
