from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable
)
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from datetime import date

OUTPUT = "report.pdf"

doc = SimpleDocTemplate(
    OUTPUT,
    pagesize=A4,
    leftMargin=2.5 * cm,
    rightMargin=2.5 * cm,
    topMargin=2.5 * cm,
    bottomMargin=2.5 * cm,
)

styles = getSampleStyleSheet()

title_style = ParagraphStyle(
    "Title",
    parent=styles["Title"],
    fontSize=24,
    textColor=colors.HexColor("#1a1a2e"),
    spaceAfter=6,
    alignment=TA_CENTER,
)
subtitle_style = ParagraphStyle(
    "Subtitle",
    parent=styles["Normal"],
    fontSize=11,
    textColor=colors.HexColor("#555555"),
    spaceAfter=4,
    alignment=TA_CENTER,
)
h1_style = ParagraphStyle(
    "H1",
    parent=styles["Heading1"],
    fontSize=14,
    textColor=colors.HexColor("#16213e"),
    spaceBefore=16,
    spaceAfter=6,
    borderPad=4,
)
h2_style = ParagraphStyle(
    "H2",
    parent=styles["Heading2"],
    fontSize=12,
    textColor=colors.HexColor("#0f3460"),
    spaceBefore=10,
    spaceAfter=4,
)
body_style = ParagraphStyle(
    "Body",
    parent=styles["Normal"],
    fontSize=10,
    leading=15,
    textColor=colors.HexColor("#333333"),
    spaceAfter=6,
)
bullet_style = ParagraphStyle(
    "Bullet",
    parent=body_style,
    leftIndent=16,
    bulletIndent=4,
    spaceAfter=3,
)
code_style = ParagraphStyle(
    "Code",
    parent=styles["Code"],
    fontSize=9,
    backColor=colors.HexColor("#f4f4f4"),
    leftIndent=12,
    rightIndent=12,
    spaceAfter=6,
    leading=13,
)

def hr():
    return HRFlowable(width="100%", thickness=0.5, color=colors.HexColor("#cccccc"), spaceAfter=8, spaceBefore=4)

def h1(text):
    return Paragraph(text, h1_style)

def h2(text):
    return Paragraph(text, h2_style)

def body(text):
    return Paragraph(text, body_style)

def bullet(text):
    return Paragraph(f"• {text}", bullet_style)

def code(text):
    return Paragraph(text, code_style)

def sp(n=1):
    return Spacer(1, n * 0.3 * cm)

story = []

# ── Cover ──────────────────────────────────────────────────────────────────────
story.append(sp(4))
story.append(Paragraph("PulseGuard", title_style))
story.append(Paragraph("Metric Monitoring & Anomaly Detection Engine", subtitle_style))
story.append(Paragraph("Technical Report", subtitle_style))
story.append(sp(1))
story.append(Paragraph(f"Date: {date.today().strftime('%B %d, %Y')}", subtitle_style))
story.append(sp(2))
story.append(hr())

# ── 1. Overview ───────────────────────────────────────────────────────────────
story.append(h1("1. Overview"))
story.append(body(
    "PulseGuard is a PHP service built on the Laravel framework that continuously monitors "
    "application metrics sourced from Prometheus, identifies behavioral anomalies across "
    "service endpoints, and correlates related signals into actionable incidents. "
    "The system is designed to surface real problems quickly while suppressing noise through "
    "dynamic baselines and alert deduplication."
))

# ── 2. Architecture ───────────────────────────────────────────────────────────
story.append(h1("2. Architecture"))
story.append(body(
    "The engine follows a service-layer architecture orchestrated by a single Artisan CLI command. "
    "Each concern is isolated in its own service class, injected via Laravel's dependency container."
))
story.append(sp())

components = [
    ["Component", "Responsibility"],
    ["PrometheusClient", "HTTP wrapper for PromQL queries against the Prometheus API"],
    ["BaselineService", "Calculates normal behavior from 1-hour historical metric windows"],
    ["AnomalyDetector", "Compares live metrics to baselines; flags latency, error, and traffic anomalies"],
    ["EventCorrelator", "Groups endpoint anomalies into system-level incidents using correlation rules"],
    ["AlertingService", "Dispatches alerts to console, JSON file, and webhooks with deduplication"],
    ["RunAIOpsDetection", "Orchestrates the full pipeline; runs continuously (20 s loop)"],
]

tbl = Table(components, colWidths=[5 * cm, 11.5 * cm])
tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#16213e")),
    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
    ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
    ("FONTSIZE", (0, 0), (-1, -1), 9),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.HexColor("#f9f9f9"), colors.white]),
    ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#cccccc")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story.append(tbl)

# ── 3. Detection Logic ────────────────────────────────────────────────────────
story.append(h1("3. Detection Logic"))
story.append(body(
    "The AnomalyDetector evaluates three independent signal types per endpoint on every loop iteration:"
))

story.append(h2("3.1 Latency Anomaly"))
story.append(body(
    "Triggered when the current 95th-percentile latency exceeds <b>3×</b> the baseline value "
    "<b>and</b> is above an absolute floor of <b>0.2 s</b>. The floor prevents false positives "
    "when baseline latency is very low."
))

story.append(h2("3.2 Error Rate Anomaly"))
story.append(body(
    "Triggered when the proportion of 5xx responses exceeds <b>10%</b> of total request traffic "
    "for the endpoint within the last 1 minute."
))

story.append(h2("3.3 Traffic Spike Anomaly"))
story.append(body(
    "Triggered when the current request rate exceeds <b>2×</b> the baseline rate "
    "<b>and</b> is above a minimum of <b>5 req/s</b> to exclude idle endpoints."
))

# ── 4. Correlation Rules ──────────────────────────────────────────────────────
story.append(h1("4. Incident Correlation"))
story.append(body(
    "The EventCorrelator groups per-endpoint anomalies into higher-level incidents. "
    "An issue is considered 'global' when more than 50% of monitored endpoints are affected. "
    "Correlation is applied in priority order to avoid duplicate incidents:"
))

incident_rows = [
    ["Incident Type", "Severity", "Trigger Condition"],
    ["SERVICE_DEGRADATION", "Critical", "Global latency AND global errors simultaneously"],
    ["ERROR_STORM", "High", "Global error rate (without co-occurring latency)"],
    ["LATENCY_SPIKE", "High", "Global latency spike (without co-occurring errors)"],
    ["LOCALIZED_ENDPOINT_FAILURE", "Medium", "Error anomaly on < 50% of endpoints"],
    ["LOCALIZED_LATENCY_SPIKE", "Medium", "Latency anomaly on < 50% of endpoints"],
    ["TRAFFIC_SURGE", "Low", "Global traffic spike across the fleet"],
]

itbl = Table(incident_rows, colWidths=[5.5 * cm, 2.5 * cm, 8.5 * cm])
itbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#0f3460")),
    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
    ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
    ("FONTSIZE", (0, 0), (-1, -1), 9),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.HexColor("#f9f9f9"), colors.white]),
    ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#cccccc")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story.append(itbl)

# ── 5. Alerting ───────────────────────────────────────────────────────────────
story.append(h1("5. Alerting & Deduplication"))
story.append(body(
    "The AlertingService dispatches incidents through three channels:"
))
story.append(bullet("<b>Console</b> — color-coded output (red for critical/high, yellow for medium)"))
story.append(bullet("<b>JSON log</b> — structured entry written via Laravel's single log channel"))
story.append(bullet("<b>Webhook</b> — HTTP POST to a configurable endpoint (optional)"))
story.append(sp())
story.append(body(
    "Deduplication prevents alert storms: an incident with the same type and affected endpoints "
    "is suppressed if it was already dispatched within the last <b>5 minutes</b>. "
    "The dedup key is an MD5 hash of the incident type and sorted endpoint list."
))

# ── 6. Configuration ──────────────────────────────────────────────────────────
story.append(h1("6. Configuration"))

cfg_rows = [
    ["Parameter", "Default", "Description"],
    ["Baseline window", "1 hour", "Historical window for calculating normal behavior"],
    ["Current metrics window", "1 minute", "Observation window for live metrics"],
    ["Latency threshold", "3× baseline + 0.2 s floor", "Multiplier above baseline to flag latency"],
    ["Error threshold", "> 10%", "Percentage of 5xx responses to flag errors"],
    ["Traffic threshold", "2× baseline + 5 req/s floor", "Multiplier above baseline to flag traffic spike"],
    ["Correlation threshold", "> 50% of endpoints", "Proportion of fleet affected to classify as global"],
    ["Dedup window", "5 minutes", "Cooldown period before re-alerting the same incident"],
    ["Loop interval", "20 seconds", "Pause between detection cycles"],
    ["Prometheus URL", "http://localhost:9090", "Configurable via .env"],
    ["Webhook URL", "None", "Set via services.pulseguard.webhook_url in config"],
]

ctbl = Table(cfg_rows, colWidths=[5 * cm, 4 * cm, 7.5 * cm])
ctbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#16213e")),
    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
    ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
    ("FONTSIZE", (0, 0), (-1, -1), 9),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.HexColor("#f9f9f9"), colors.white]),
    ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#cccccc")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story.append(ctbl)

# ── 7. Tech Stack ─────────────────────────────────────────────────────────────
story.append(h1("7. Tech Stack"))

stack_rows = [
    ["Layer", "Technology"],
    ["Framework", "Laravel 12 (PHP 8.2+)"],
    ["Database", "SQLite (default) / MySQL / PostgreSQL"],
    ["Queue & Cache", "Database-backed"],
    ["HTTP Client", "Laravel HTTP facade (Guzzle)"],
    ["Testing", "PHPUnit 11 with Mockery"],
    ["Frontend Build", "Vite 7 + Tailwind CSS 4"],
    ["Metrics Source", "Prometheus (HTTP API / PromQL)"],
]

stbl = Table(stack_rows, colWidths=[5 * cm, 11.5 * cm])
stbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#16213e")),
    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
    ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
    ("FONTSIZE", (0, 0), (-1, -1), 9),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.HexColor("#f9f9f9"), colors.white]),
    ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#cccccc")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story.append(stbl)

# ── 8. Setup & Usage ──────────────────────────────────────────────────────────
story.append(h1("8. Setup & Usage"))
story.append(body("Install dependencies and initialise the environment:"))
story.append(code("composer run setup"))
story.append(body("Start the monitoring loop:"))
story.append(code("php artisan pulse:monitor"))
story.append(body(
    "Incidents are persisted to <i>storage/app/pulseguard/incidents.json</i> and logged "
    "via the application log channel."
))

# ── Footer note ───────────────────────────────────────────────────────────────
story.append(sp(3))
story.append(hr())
story.append(Paragraph(
    f"PulseGuard — Confidential Technical Report — {date.today().year}",
    ParagraphStyle("footer", parent=styles["Normal"], fontSize=8,
                   textColor=colors.HexColor("#aaaaaa"), alignment=TA_CENTER)
))

doc.build(story)
print(f"Report generated: {OUTPUT}")
