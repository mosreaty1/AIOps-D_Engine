<?php

namespace App\Services;

class BaselineService
{
    protected PrometheusClient $prometheusClient;

    public function __construct(PrometheusClient $prometheusClient)
    {
        $this->prometheusClient = $prometheusClient;
    }

    /**
     * Calculates the baseline metrics for a given endpoint.
     * Baselines are derived from a longer window (e.g., '1h') to represent normal behavior.
     *
     * @return array Returns baseline latency, request_rate, and error_rate
     */
    public function getBaselinesForEndpoint(string $endpoint, string $baselineWindow = '1h'): array
    {
        return [
            'latency' => $this->getBaselineLatency($endpoint, $baselineWindow),
            'request_rate' => $this->getBaselineRequestRate($endpoint, $baselineWindow),
            'error_rate' => $this->getBaselineErrorRate($endpoint, $baselineWindow),
        ];
    }

    protected function getBaselineLatency(string $endpoint, string $window): float
    {
        // Average 95th percentile latency over the baseline window
        $query = "avg_over_time(histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{path=\"{$endpoint}\"}[5m]))[{$window}:5m])";
        $results = $this->prometheusClient->query($query);
        
        return $this->extractValue($results, 0.05); // Default 50ms if no data
    }

    protected function getBaselineRequestRate(string $endpoint, string $window): float
    {
        // Average request rate over the baseline window
        $query = "avg_over_time(rate(http_requests_total{path=\"{$endpoint}\"}[5m])[{$window}:5m])";
        $results = $this->prometheusClient->query($query);
        
        return $this->extractValue($results, 10.0); // Default 10 req/s if no data
    }

    protected function getBaselineErrorRate(string $endpoint, string $window): float
    {
        // Average error rate over the baseline window
        $query = "avg_over_time(rate(http_requests_total{path=\"{$endpoint}\", status=~\"5..\"}[5m])[{$window}:5m])";
        $results = $this->prometheusClient->query($query);
        
        return $this->extractValue($results, 0.0); // Default 0 if no data
    }

    /**
     * Extracts the float value from a Prometheus query result, returning a default if no value exists.
     */
    protected function extractValue(array $results, float $default = 0.0): float
    {
        if (empty($results) || !isset($results[0]['value'][1])) {
            return $default;
        }

        $val = $results[0]['value'][1];
        if ($val === 'NaN' || !is_numeric($val)) {
            return $default;
        }

        return (float) $val;
    }
}
