# AIOps Detection Engine - Engineering Report

## 1. Baseline Design
The `BaselineService` establishes the "normal" operational state of the system by querying Prometheus for historical data over a defined trailing window, defaulting to 1 hour (`1h`). 
This dynamic baseline modeling prevents hardcoding static thresholds that fail to adapt to legitimate changes in system behavior (e.g., peak vs. off-peak hours).

The engine computes three primary baseline signals per endpoint:
*   **Latency Baseline:** The average of the 95th percentile latency over the baseline window.
*   **Request Rate Baseline:** The average request rate (requests per second) over the window.
*   **Error Rate Baseline:** The average rate of HTTP 5xx errors over the window.

If historical data is unavailable (e.g., when the system is first started), the service provides sensible safety defaults (e.g., 50ms for latency, 10 req/s, 0 errors/s) to ensure the anomaly detector still has a baseline to evaluate against until sufficient data is collected.

## 2. Anomaly Rules
The `AnomalyDetector` continuously evaluates recent metric observations (e.g., from the last 1 minute) against the calculated baselines to identify statistically significant deviations representing potential issues.

The detection rules emphasize multi-signal analysis—evaluating distinct performance dimensions independently—and apply minimum thresholds to prevent noise from trivial fluctuations on low-volume endpoints.

Our anomaly detection rules are as follows:
*   **Latency Anomaly:** Triggered if the current 95th percentile latency is greater than **3 times** the baseline latency. Wait, what if the baseline is extremely low (0.01s)? 3x is still imperceptible. Thus, a minimum threshold (e.g., `0.2s`) must also be breached.
*   **Error Rate Anomaly:** Triggered if the current error rate exceeds **10%** of the total traffic for that endpoint. 
*   **Traffic Anomaly (Surge):** Triggered if the current request rate is greater than **2 times** the baseline request rate, provided the traffic exceeds a minimum noise threshold (e.g., >5 req/s) to avoid alerting on a spike from 1 to 3 requests.

## 3. Event Correlation Strategy
Single anomalies are often symptoms of broader system issues. Alerting on every individual anomaly creates alert fatigue. The `EventCorrelator` addresses this by applying rules to group and elevate distinct anomalies into higher-order **Incidents** across the entire service fleet.

The correlator works by aggregating anomalies across all endpoints globally before applying correlation rules.

The correlation logic maps the combinations as follows:
*   **`SERVICE_DEGRADATION` (Critical):** If more than 50% of the monitored endpoints simultaneously exhibit both a latency anomaly and an error rate anomaly, this is correlated into a system-wide critical degradation event. Granular endpoint alerts are suppressed.
*   **`ERROR_STORM` (High):** If more than 50% of endpoints exhibit an error rate anomaly (without corresponding global latency), it correlates into a global error storm.
*   **`LATENCY_SPIKE` (High):** If more than 50% of endpoints exhibit a latency anomaly, it correlates into a global latency spike.
*   **`LOCALIZED_ENDPOINT_FAILURE` (Medium) / `LOCALIZED_LATENCY_SPIKE` (Medium):** If errors or latency spikes occur, but affect less than 50% of the fleet, they remain localized incidents pinpointing the exact struggling endpoints.
*   **`TRAFFIC_SURGE` (Low):** If more than 50% of endpoints experience a traffic anomaly, a surge event is created. This may not signify an outage, but provides critical context if degradation follows.

Once an incident is identified, the `AlertingService` deduplicates alerts by calculating a hash of the incident type and affected endpoints, ensuring that notifications are only dispatched once every 5 minutes per unique incident type/affected components combination.
