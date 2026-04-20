<?php

namespace App\Services;

class AnomalyDetector
{
    public function detect(array $baselines, array $currentMetrics): array
    {
        $anomalies = [];

        $minLatencyThreshold = 0.2;
        if ($currentMetrics['latency'] > ($baselines['latency'] * 3) && $currentMetrics['latency'] > $minLatencyThreshold) {
            $anomalies[] = [
                'type' => 'latency_anomaly',
                'description' => sprintf(
                    'Latency (%.2f s) is more than 3x the baseline (%.2f s)',
                    $currentMetrics['latency'],
                    $baselines['latency']
                ),
                'observed' => $currentMetrics['latency'],
                'baseline' => $baselines['latency'],
            ];
        }

        $totalRequestRate = $currentMetrics['request_rate'];
        $errorPercentage = 0;
        if ($totalRequestRate > 0) {
            $errorPercentage = ($currentMetrics['error_rate'] / $totalRequestRate) * 100;
        }

        if ($errorPercentage > 10) {
            $anomalies[] = [
                'type' => 'error_rate_anomaly',
                'description' => sprintf(
                    'Error rate is %.2f%% (Current errors/s: %.2f, Total reqs/s: %.2f) which exceeds the 10%% threshold',
                    $errorPercentage,
                    $currentMetrics['error_rate'],
                    $totalRequestRate
                ),
                'observed' => $errorPercentage,
                'baseline' => 10,
            ];
        }

        $minTrafficThreshold = 5.0;
        if ($currentMetrics['request_rate'] > ($baselines['request_rate'] * 2) && $currentMetrics['request_rate'] > $minTrafficThreshold) {
            $anomalies[] = [
                'type' => 'traffic_anomaly',
                'description' => sprintf(
                    'Traffic (%.2f req/s) is more than 2x the baseline (%.2f req/s)',
                    $currentMetrics['request_rate'],
                    $baselines['request_rate']
                ),
                'observed' => $currentMetrics['request_rate'],
                'baseline' => $baselines['request_rate'],
            ];
        }

        return $anomalies;
    }
}
