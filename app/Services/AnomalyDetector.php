<?php

namespace App\Services;

class AnomalyDetector
{
    /**
     * Evaluates current metrics against baselines to detect anomalies.
     *
     * @param array $baselines The baseline metrics for the endpoint
     * @param array $currentMetrics The currently observed metrics
     * @return array List of detected anomalies
     */
    public function detect(array $baselines, array $currentMetrics): array
    {
        $anomalies = [];

        // 1. Latency Anomaly: latency > 3x baseline
        // Alternatively, if baseline is very low (e.g., 0.05s), ensure it breaches a minimum threshold (e.g., 0.2s)
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

        // 2. Error Rate Anomaly: error rate > 10%
        // Calculate error percentage
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
                'baseline' => 10, // absolute threshold in this case
            ];
        }

        // 3. Traffic Anomaly (Spike): request rate > 2x baseline
        // Ensure baseline is non-zero and we have a minimum traffic to consider it a spike
        $minTrafficThreshold = 5.0; // req/s
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
