<?php

namespace App\Services;

use App\Models\Incident;

class EventCorrelator
{
    public function correlate(array $allAnomalies, array $baselines, array $currentMetrics): array
    {
        $incidents = [];
        $globalAnomalies = ['latency' => [], 'error' => [], 'traffic' => []];

        foreach ($allAnomalies as $endpoint => $anomalies) {
            foreach ($anomalies as $anomaly) {
                if ($anomaly['type'] === 'latency_anomaly') {
                    $globalAnomalies['latency'][] = $endpoint;
                }
                if ($anomaly['type'] === 'error_rate_anomaly') {
                    $globalAnomalies['error'][] = $endpoint;
                }
                if ($anomaly['type'] === 'traffic_anomaly') {
                    $globalAnomalies['traffic'][] = $endpoint;
                }
            }
        }

        $totalEndpoints = count($baselines);
        if ($totalEndpoints === 0) {
            return [];
        }

        $hasGlobalLatency = count($globalAnomalies['latency']) > ($totalEndpoints / 2);
        $hasGlobalErrors = count($globalAnomalies['error']) > ($totalEndpoints / 2);
        $hasGlobalTraffic = count($globalAnomalies['traffic']) > ($totalEndpoints / 2);

        if ($hasGlobalLatency && $hasGlobalErrors) {
            $affected = array_unique(array_merge($globalAnomalies['latency'], $globalAnomalies['error']));
            $incidents[] = new Incident([
                'incident_type' => 'SERVICE_DEGRADATION',
                'severity' => 'critical',
                'affected_service' => 'global',
                'affected_endpoints' => $affected,
                'summary' => 'Critical service degradation detected. Widespread high latency and error rates across multiple endpoints.',
                'triggering_signals' => ['latency_anomaly', 'error_rate_anomaly'],
                'baseline_values' => $baselines,
                'observed_values' => $currentMetrics,
            ]);
            $globalAnomalies['latency'] = [];
            $globalAnomalies['error'] = [];
        }

        if (count($globalAnomalies['error']) > 0) {
            if ($hasGlobalErrors) {
                $incidents[] = new Incident([
                    'incident_type' => 'ERROR_STORM',
                    'severity' => 'high',
                    'affected_service' => 'global',
                    'affected_endpoints' => $globalAnomalies['error'],
                    'summary' => 'System-wide error storm detected. High error rates on multiple endpoints.',
                    'triggering_signals' => ['error_rate_anomaly'],
                    'baseline_values' => $baselines,
                    'observed_values' => $currentMetrics,
                ]);
            } else {
                foreach ($globalAnomalies['error'] as $endpoint) {
                    $incidents[] = new Incident([
                        'incident_type' => 'LOCALIZED_ENDPOINT_FAILURE',
                        'severity' => 'medium',
                        'affected_service' => 'api',
                        'affected_endpoints' => [$endpoint],
                        'summary' => "High error rate localized to endpoint: {$endpoint}",
                        'triggering_signals' => ['error_rate_anomaly'],
                        'baseline_values' => [$endpoint => $baselines[$endpoint] ?? []],
                        'observed_values' => [$endpoint => $currentMetrics[$endpoint] ?? []],
                    ]);
                }
            }
        }

        if (count($globalAnomalies['latency']) > 0) {
            if ($hasGlobalLatency) {
                $incidents[] = new Incident([
                    'incident_type' => 'LATENCY_SPIKE',
                    'severity' => 'high',
                    'affected_service' => 'global',
                    'affected_endpoints' => $globalAnomalies['latency'],
                    'summary' => 'System-wide latency spike detected.',
                    'triggering_signals' => ['latency_anomaly'],
                    'baseline_values' => $baselines,
                    'observed_values' => $currentMetrics,
                ]);
            } else {
                foreach ($globalAnomalies['latency'] as $endpoint) {
                    $alreadyFlagged = collect($incidents)->contains(function ($incident) use ($endpoint) {
                        return $incident->incident_type === 'LOCALIZED_ENDPOINT_FAILURE' && in_array($endpoint, $incident->affected_endpoints);
                    });

                    if (!$alreadyFlagged) {
                        $incidents[] = new Incident([
                            'incident_type' => 'LOCALIZED_LATENCY_SPIKE',
                            'severity' => 'medium',
                            'affected_service' => 'api',
                            'affected_endpoints' => [$endpoint],
                            'summary' => "High latency localized to endpoint: {$endpoint}",
                            'triggering_signals' => ['latency_anomaly'],
                            'baseline_values' => [$endpoint => $baselines[$endpoint] ?? []],
                            'observed_values' => [$endpoint => $currentMetrics[$endpoint] ?? []],
                        ]);
                    }
                }
            }
        }

        if ($hasGlobalTraffic) {
            $incidents[] = new Incident([
                'incident_type' => 'TRAFFIC_SURGE',
                'severity' => 'low',
                'affected_service' => 'global',
                'affected_endpoints' => $globalAnomalies['traffic'],
                'summary' => 'System-wide traffic surge detected compared to baseline.',
                'triggering_signals' => ['traffic_anomaly'],
                'baseline_values' => $baselines,
                'observed_values' => $currentMetrics,
            ]);
        }

        return $incidents;
    }
}
