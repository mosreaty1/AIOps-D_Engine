<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PrometheusClient
{
    protected string $baseUrl;

    public function __construct(string $baseUrl = 'http://localhost:9090')
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Executes a PromQL query against the Prometheus API.
     */
    public function query(string $promql): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $promql,
            ]);

            if ($response->successful()) {
                return $response->json('data.result') ?? [];
            }
        } catch (\Exception $e) {
            // Log the error or handle it silently to prevent the detection engine from crashing
            \Illuminate\Support\Facades\Log::warning("Prometheus connection failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Gets the request rate per endpoint over a given window (e.g., '1m').
     */
    public function getRequestRatePerEndpoint(string $window = '1m'): array
    {
        $query = "rate(http_requests_total{{$window}})";
        return $this->query($query);
    }

    /**
     * Gets the error rate per endpoint over a given window.
     */
    public function getErrorRatePerEndpoint(string $window = '1m'): array
    {
        $query = "rate(http_requests_total{status=~\"5..\"}[{$window}])";
        return $this->query($query);
    }

    /**
     * Gets the 95th percentile latency per endpoint over a given window.
     */
    public function getLatencyPercentiles(string $window = '1m', float $percentile = 0.95): array
    {
        $query = "histogram_quantile({$percentile}, rate(http_request_duration_seconds_bucket[{$window}]))";
        return $this->query($query);
    }

    /**
     * Gets the error category counters.
     */
    public function getErrorCategoryCounters(string $window = '1m'): array
    {
        // Assuming there is a label 'category' or similar for different error types if custom metrics are used.
        // If simply relying on HTTP status codes:
        $query = "sum by (status) (rate(http_requests_total{status=~\"[45]..\"}[{$window}]))";
        return $this->query($query);
    }
}
