<?php

namespace App\Models;

use Illuminate\Support\Str;
use InvalidArgumentException;

class Incident
{
    public string $incident_id;
    public string $incident_type;
    public string $severity;
    public string $status;
    public string $detected_at;
    public string $affected_service;
    public array $affected_endpoints;
    public array $triggering_signals;
    public array $baseline_values;
    public array $observed_values;
    public string $summary;

    public function __construct(array $attributes)
    {
        $this->incident_id = $attributes['incident_id'] ?? Str::uuid()->toString();
        $this->incident_type = $attributes['incident_type'] ?? throw new InvalidArgumentException('incident_type is required');
        $this->severity = $attributes['severity'] ?? 'warning';
        $this->status = $attributes['status'] ?? 'open';
        $this->detected_at = $attributes['detected_at'] ?? now()->toIso8601String();
        $this->affected_service = $attributes['affected_service'] ?? 'unknown';
        $this->affected_endpoints = $attributes['affected_endpoints'] ?? [];
        $this->triggering_signals = $attributes['triggering_signals'] ?? [];
        $this->baseline_values = $attributes['baseline_values'] ?? [];
        $this->observed_values = $attributes['observed_values'] ?? [];
        $this->summary = $attributes['summary'] ?? '';
    }

    public function toArray(): array
    {
        return [
            'incident_id' => $this->incident_id,
            'incident_type' => $this->incident_type,
            'severity' => $this->severity,
            'status' => $this->status,
            'detected_at' => $this->detected_at,
            'affected_service' => $this->affected_service,
            'affected_endpoints' => $this->affected_endpoints,
            'triggering_signals' => $this->triggering_signals,
            'baseline_values' => $this->baseline_values,
            'observed_values' => $this->observed_values,
            'summary' => $this->summary,
        ];
    }
}
