<?php

namespace App\Console\Commands;

use App\Services\AlertingService;
use App\Services\AnomalyDetector;
use App\Services\BaselineService;
use App\Services\EventCorrelator;
use App\Services\PrometheusClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RunAIOpsDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aiops:detect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously analyze metrics and automatically detect abnormal system behavior.';

    protected $endpoints = [
        '/api/normal',
        '/api/slow',
        '/api/db',
        '/api/error',
        '/api/validate'
    ];

    /**
     * Execute the console command.
     */
    public function handle(
        PrometheusClient $prometheusClient,
        BaselineService $baselineService,
        AnomalyDetector $anomalyDetector,
        EventCorrelator $eventCorrelator,
        AlertingService $alertingService
    ) {
        $this->info("Starting AIOps Detection Engine...");

        // Ensure storage directory exists
        if (!Storage::disk('local')->exists('aiops')) {
            Storage::disk('local')->makeDirectory('aiops');
        }

        while (true) {
            $this->info("--- Loop Start: " . now()->toDateTimeString() . " ---");
            
            $allAnomalies = [];
            $baselinesGlobal = [];
            $currentMetricsGlobal = [];

            // Fetch metrics and baselines for each endpoint
            foreach ($this->endpoints as $endpoint) {
                // 1. Get baselines
                $baselines = $baselineService->getBaselinesForEndpoint($endpoint);
                $baselinesGlobal[$endpoint] = $baselines;

                // 2. Get current metrics (simulate fetching for endpoint from full API results for simplicity, 
                // or query specifically if needed). Let's fetch recent (last 1m) metrics for clarity.
                
                // Helper to extract value from PromQL result matching our endpoint
                $extractMetric = function($results, $endpoint, $default = 0.0) {
                    if (empty($results)) return $default;
                    foreach ($results as $result) {
                        if (isset($result['metric']['path']) && $result['metric']['path'] === $endpoint) {
                           return (float) ($result['value'][1] ?? $default);
                        }
                        // Fallback if no path label, mostly for overall metrics test
                    }
                    return $default;
                };

                // Request rate currently
                $reqRateResult = $prometheusClient->query("rate(http_requests_total{path=\"{$endpoint}\"}[1m])");
                $currentReqRate = $extractMetric($reqRateResult, $endpoint, 0.0);

                // Error rate currently
                $errRateResult = $prometheusClient->query("rate(http_requests_total{path=\"{$endpoint}\", status=~\"5..\"}[1m])");
                $currentErrRate = $extractMetric($errRateResult, $endpoint, 0.0);

                // Latency currently
                $latencyResult = $prometheusClient->query("histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{path=\"{$endpoint}\"}[1m]))");
                $currentLatency = $extractMetric($latencyResult, $endpoint, 0.05);

                $currentMetrics = [
                    'latency' => $currentLatency,
                    'request_rate' => $currentReqRate,
                    'error_rate' => $currentErrRate,
                ];
                $currentMetricsGlobal[$endpoint] = $currentMetrics;

                $this->line("Endpoint {$endpoint}: Req Rate: {$currentReqRate}, Err Rate: {$currentErrRate}, Latency: {$currentLatency}");

                // 3. Detect anomalies
                $anomalies = $anomalyDetector->detect($baselines, $currentMetrics);
                if (!empty($anomalies)) {
                    $allAnomalies[$endpoint] = $anomalies;
                    foreach ($anomalies as $anomaly) {
                        $this->warn("Anomaly detected on {$endpoint}: " . $anomaly['type'] . " - " . $anomaly['description']);
                    }
                }
            }

            // 4. Correlate Events
            if (!empty($allAnomalies)) {
                $incidents = $eventCorrelator->correlate($allAnomalies, $baselinesGlobal, $currentMetricsGlobal);
                
                // 5. Alert and Store
                foreach ($incidents as $incident) {
                    $alertingService->dispatch($incident, $this);
                    $this->storeIncident($incident);
                }
            }

            $alertingService->cleanup();

            $this->info("--- Loop End. Sleeping for 20s ---");
            sleep(20);
        }
    }

    protected function storeIncident($incident)
    {
        $path = storage_path('app/aiops/incidents.json');
        
        $incidents = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content) {
                $incidents = json_decode($content, true) ?? [];
            }
        }

        $incidents[] = $incident->toArray();

        file_put_contents($path, json_encode($incidents, JSON_PRETTY_PRINT));
    }
}
