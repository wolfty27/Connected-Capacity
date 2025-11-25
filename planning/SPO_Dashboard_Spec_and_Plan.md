# SPO Care Operations Dashboard - Implementation Spec

## 1. SPO Care Dashboard UX Spec v1

### 1.1 Page Purpose & User Story
**Primary User:** SPO Admin / SPO Operations Manager.
**Goal:** Monitor the real-time health of the High Intensity Care program, identify immediate risks (missed care, unfilled shifts), and manage partner (SSPO) capacity to ensure 100% fulfillment of care bundles.

### 1.2 Information Hierarchy
1.  **Critical Alerts (Top Row):** Immediate operational failures or risks.
    *   *Missed Care (Past 24h)*: The "Zero Tolerance" metric.
    *   *Unfilled Shifts (Next 48h)*: Leading indicator of future missed care.
    *   *ED Visits / Readmissions*: Clinical failure events (added from RFP).
2.  **Program Volume (Top Row):**
    *   *Active Bundles*: Scale of operation.
3.  **Partner Network Health (Main Content - Left):**
    *   SSPO performance table. Are partners accepting referrals? Are they delivering care?
4.  **AI Insights (Main Content - Right):**
    *   Predictive capacity analysis and risk forecasting.

### 1.3 Layout & Regions
*   **Shell:** `AppLayout` (Sidebar + TopNav).
*   **Page Header:** Title "Care Operations", Subtitle, Global Date/Org Filter (optional), "Run Forecast" Action.
*   **KPI Strip:** Grid of 3-4 cards.
*   **Main Content Area:** 2-column grid (2/3 Left, 1/3 Right).
    *   **Left:** Tabbed Interface or Stacked Sections.
        *   *Tab 1: Partner Performance* (Table).
        *   *Tab 2: Quality & Compliance* (Detailed RFP metrics).
    *   **Right:** `AiForecastPanel` (Sticky or fixed height).

### 1.4 Component Inventory
*   **`KpiCard`**:
    *   Props: `label`, `value`, `trend` (e.g., "+2"), `status` ('success', 'warning', 'danger', 'neutral'), `icon`, `actionLabel` (optional), `onAction` (optional).
    *   Variants: Standard, Critical (Red border/pulse).
*   **`PartnerPerformanceTable`**:
    *   Columns: Organization, Specialty, Active Bundles, Referral Acceptance Rate (%), Missed Visit Rate (%), Status (RAG dot).
*   **`AiForecastPanel`**:
    *   States: Empty/Prompt, Loading (Skeleton/Spinner), Results (List of `InsightCard`s).
*   **`InsightCard`**:
    *   Props: `type` ('warning', 'opportunity'), `title`, `description`, `metric`.
*   **`MissedCareModal`**:
    *   Content: List of missed visits, reasons, and AI Root Cause Analysis.

---

## 2. SPO Care Dashboard ↔ RFP Obligation Map

| RFP Metric / Requirement | Target | UI Location | Time Window | SPO Action |
| :--- | :--- | :--- | :--- | :--- |
| **Missed Care Events** | **0%** | **KPI Card 1 (Critical)** | Past 24h | **Intervene:** Dispatch rapid response / on-call staff. Analyze root cause. |
| **Unfilled Shifts** | N/A | **KPI Card 2 (Warning)** | Next 48h | **Triage:** Offer shifts to SSPO network or internal float pool. |
| **ED Visits / Readmissions** | 0% Avoidable | **KPI Card 3 (Alert)** | Past 7d | **Investigate:** Review patient file, clinical notes, and transition history. |
| **Active Patients / Bundles** | 100% Eligible | **KPI Card 4 (Info)** | Real-time | **Monitor:** Track program growth and utilization. |
| **Referral Acceptance Rate** | **100%** | **Partner Table** (Column) | Rolling 30d | **Manage:** Discuss capacity with SSPOs falling below 100%. |
| **Time to First Service** | **< 24h** | **Quality Tab** (Chart) | Rolling 30d | **Monitor:** Ensure intake-to-service workflow is efficient. |
| **Complaints / Adverse Events** | 0 | **Quality Tab** (List) | Past 30d | **Resolve:** Manage incident reporting workflow. |
| **LTC Transition Requests** | 0 | **Quality Tab** (Count) | Cumulative | **Review:** Assess if bundle intensity is sufficient. |
| **Patient / Staff Satisfaction** | > 95% | **Quality Tab** (Score) | Quarterly | **Improve:** Review feedback and adjust care delivery. |
| **HPG Response Time** | 15 min | **Hidden / Alert** | Real-time | **Auto-Alert:** Push notification if response time breached. |

---

## 3. Implementation Plan (React + Laravel)

### Phase 1: Backend API & Services

- [ ] **[API-01] Create CareOps Metrics Service**
    -   Create `App\Services\CareOpsMetricsService`.
    -   Methods:
        -   `getMissedCareStats(orgId, timeframe)`: Returns count and list of missed visits.
        -   `getUnfilledShifts(orgId, timeframe)`: Returns count of unassigned visits in future.
        -   `getPartnerPerformance(orgId)`: Aggregates acceptance rate and active volume per SSPO.
        -   `getProgramVolume(orgId)`: Active patients, bundles.
- [ ] **[API-02] Create Dashboard API Endpoint**
    -   Create `App\Http\Controllers\Api\V2\Dashboard\SpoDashboardController`.
    -   Endpoint: `GET /api/v2/dashboards/spo`.
    -   Response: JSON structure matching the UX Spec (KPIs, Partners, Forecast placeholder).
- [ ] **[API-03] Implement AI Forecast Endpoint**
    -   Create `App\Http\Controllers\Api\V2\Ai\ForecastController`.
    -   Endpoint: `POST /api/v2/ai/forecast`.
    -   Logic: Trigger `GeminiService` to analyze schedule gaps vs referral volume.

### Phase 2: Frontend Components

- [ ] **[UI-01] Create Dashboard Components**
    -   `resources/js/components/dashboard/KpiCard.jsx`: Reusable card with status styles.
    -   `resources/js/components/dashboard/PartnerPerformanceTable.jsx`: Data table with status indicators.
    -   `resources/js/components/dashboard/AiForecastPanel.jsx`: Interactive panel with loading states.
- [ ] **[UI-02] Create SPO Dashboard Page**
    -   `resources/js/pages/CareOps/CareDashboardPage.jsx`.
    -   Layout: Implement the grid structure defined in UX Spec.
    -   Integration: Fetch data from `/api/v2/dashboards/spo` on mount.

### Phase 3: Integration & Logic

- [ ] **[INT-01] Wire Up AI Features**
    -   Connect "Run Forecast" button to `POST /api/v2/ai/forecast`.
    -   Display results in `AiForecastPanel`.
    -   Implement "Root Cause Analysis" modal for Missed Care KPI.
- [ ] **[INT-02] Real-time / Polling Updates**
    -   Implement polling (e.g., every 60s) for the KPI strip to ensure "Missed Care" is always up to date.

### Phase 4: Quality & Compliance View (Drill-down)

- [ ] **[UI-03] Build Quality Tab**
    -   Add a secondary view/tab to `CareDashboardPage`.
    -   Display secondary RFP metrics (Time to First Service, Complaints, Satisfaction) in a list or simple chart format.
