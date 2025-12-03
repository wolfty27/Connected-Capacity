# AI Bundle Engine - Learning Infrastructure Design

**Version:** 1.0.0
**Status:** Design Complete
**Date:** 2025-12-03

## Overview

The Learning Infrastructure enables the AI Bundle Engine to improve over time by:
1. Tracking which scenarios are generated and selected
2. Correlating bundle configurations with patient outcomes
3. Identifying patterns that predict successful care plans
4. Proposing rule refinements based on evidence

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        DATA FLOW                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐          │
│  │   Bundle     │    │   Local      │    │   BigQuery   │          │
│  │   Engine     │───▶│   Event      │───▶│   Analytics  │          │
│  │   (Laravel)  │    │   Log Table  │    │   Warehouse  │          │
│  └──────────────┘    └──────────────┘    └──────────────┘          │
│         │                                       │                    │
│         │                                       ▼                    │
│         │                              ┌──────────────┐             │
│         │                              │   Learning   │             │
│         │                              │   Pipeline   │             │
│         │                              │   (Future)   │             │
│         │                              └──────────────┘             │
│         │                                       │                    │
│         │                                       ▼                    │
│         │                              ┌──────────────┐             │
│         │                              │   Rule       │             │
│         ◀──────────────────────────────│   Refinement │             │
│                                        │   Proposals  │             │
│                                        └──────────────┘             │
└─────────────────────────────────────────────────────────────────────┘
```

## Event Types

### 1. Scenario Generation Events
Captured when scenarios are generated for a patient.

| Field | Type | Description |
|-------|------|-------------|
| event_id | STRING | Unique event identifier (UUID) |
| event_type | STRING | "scenario_generated" |
| timestamp | TIMESTAMP | When event occurred |
| patient_ref | STRING | De-identified patient reference (P-XXXX) |
| patient_profile_hash | STRING | Hash of profile for grouping |
| scenario_id | STRING | Unique scenario identifier |
| primary_axis | STRING | Primary axis (e.g., "recovery_focused") |
| secondary_axes | ARRAY<STRING> | Secondary axes applied |
| service_count | INT64 | Number of services in scenario |
| weekly_hours | FLOAT64 | Total weekly service hours |
| weekly_cost | FLOAT64 | Estimated weekly cost |
| cost_status | STRING | "within_cap", "near_cap", "over_cap" |
| algorithm_scores | JSON | Algorithm scores at generation time |
| triggered_caps | ARRAY<STRING> | CAPs that influenced generation |
| confidence_level | STRING | "high", "medium", "low" |
| generation_time_ms | INT64 | Time to generate (performance) |

### 2. Scenario Selection Events
Captured when a coordinator selects a scenario for a patient.

| Field | Type | Description |
|-------|------|-------------|
| event_id | STRING | Unique event identifier |
| event_type | STRING | "scenario_selected" |
| timestamp | TIMESTAMP | When selection occurred |
| patient_ref | STRING | De-identified patient reference |
| scenario_id | STRING | Selected scenario ID |
| scenarios_offered_count | INT64 | How many scenarios were shown |
| scenario_rank | INT64 | Position in list (1=first/recommended) |
| was_recommended | BOOLEAN | Was this the recommended scenario? |
| selection_time_seconds | INT64 | Time from display to selection |
| modifications_made | BOOLEAN | Did coordinator modify after selection? |
| modification_summary | JSON | What was changed (services added/removed) |
| coordinator_id | STRING | De-identified coordinator reference |
| explanation_requested | BOOLEAN | Did they request an explanation? |

### 3. Care Plan Publication Events
Captured when a care plan is published/activated.

| Field | Type | Description |
|-------|------|-------------|
| event_id | STRING | Unique event identifier |
| event_type | STRING | "care_plan_published" |
| timestamp | TIMESTAMP | When plan was published |
| patient_ref | STRING | De-identified patient reference |
| care_plan_id | STRING | Care plan identifier |
| scenario_id | STRING | Original scenario (if AI-generated) |
| final_service_count | INT64 | Services in final plan |
| final_weekly_hours | FLOAT64 | Final weekly hours |
| final_weekly_cost | FLOAT64 | Final weekly cost |
| deviation_from_scenario | JSON | How much final differs from scenario |
| is_modification | BOOLEAN | Was this modifying an existing plan? |

### 4. Patient Outcome Events
Captured periodically to track patient progress.

| Field | Type | Description |
|-------|------|-------------|
| event_id | STRING | Unique event identifier |
| event_type | STRING | "patient_outcome" |
| timestamp | TIMESTAMP | When outcome was recorded |
| patient_ref | STRING | De-identified patient reference |
| care_plan_id | STRING | Associated care plan |
| outcome_type | STRING | Type of outcome (see below) |
| outcome_value | STRING | Outcome value/category |
| days_since_plan_start | INT64 | Days since care plan started |
| assessment_source | STRING | "hc", "ca", "bmhs", "manual" |

**Outcome Types:**
- `adl_change` - ADL score change (improved/stable/declined)
- `iadl_change` - IADL score change
- `hospitalization` - Hospital admission event
- `er_visit` - Emergency room visit
- `fall_incident` - Fall reported
- `episode_completed` - Care episode ended
- `episode_extended` - Care plan extended
- `goal_achieved` - Patient goal marked complete
- `readmission` - Readmitted within 30 days

## BigQuery Schema Definitions

### Table: `bundle_scenarios_generated`

```sql
CREATE TABLE IF NOT EXISTS `connected_capacity.bundle_scenarios_generated` (
    event_id STRING NOT NULL,
    event_type STRING NOT NULL DEFAULT 'scenario_generated',
    timestamp TIMESTAMP NOT NULL,
    
    -- Patient Context (de-identified)
    patient_ref STRING NOT NULL,
    patient_profile_hash STRING,
    
    -- Scenario Details
    scenario_id STRING NOT NULL,
    primary_axis STRING,
    secondary_axes ARRAY<STRING>,
    scenario_title STRING,
    scenario_description STRING,
    
    -- Service Composition
    service_count INT64,
    services JSON,  -- Array of {code, hours, visits}
    weekly_hours FLOAT64,
    weekly_cost FLOAT64,
    cost_status STRING,  -- within_cap, near_cap, over_cap
    
    -- Clinical Context
    rug_group STRING,
    needs_cluster STRING,
    episode_type STRING,
    algorithm_scores JSON,
    triggered_caps ARRAY<STRING>,
    
    -- Confidence
    confidence_level STRING,
    data_completeness_score FLOAT64,
    
    -- Performance
    generation_time_ms INT64,
    
    -- Metadata
    engine_version STRING,
    environment STRING  -- staging, production
)
PARTITION BY DATE(timestamp)
CLUSTER BY patient_ref, primary_axis;
```

### Table: `bundle_scenarios_selected`

```sql
CREATE TABLE IF NOT EXISTS `connected_capacity.bundle_scenarios_selected` (
    event_id STRING NOT NULL,
    event_type STRING NOT NULL DEFAULT 'scenario_selected',
    timestamp TIMESTAMP NOT NULL,
    
    -- Patient Context
    patient_ref STRING NOT NULL,
    
    -- Selection Context
    scenario_id STRING NOT NULL,
    scenarios_offered_count INT64,
    scenario_rank INT64,
    was_recommended BOOLEAN,
    
    -- User Behavior
    selection_time_seconds INT64,
    modifications_made BOOLEAN,
    modification_summary JSON,
    explanation_requested BOOLEAN,
    explanation_source STRING,  -- vertex_ai, rules_based
    
    -- Coordinator (de-identified)
    coordinator_ref STRING,
    
    -- Final Configuration
    final_service_count INT64,
    final_weekly_hours FLOAT64,
    final_weekly_cost FLOAT64,
    
    -- Metadata
    session_id STRING,
    environment STRING
)
PARTITION BY DATE(timestamp)
CLUSTER BY patient_ref, scenario_id;
```

### Table: `bundle_patient_outcomes`

```sql
CREATE TABLE IF NOT EXISTS `connected_capacity.bundle_patient_outcomes` (
    event_id STRING NOT NULL,
    event_type STRING NOT NULL DEFAULT 'patient_outcome',
    timestamp TIMESTAMP NOT NULL,
    
    -- Patient Context
    patient_ref STRING NOT NULL,
    care_plan_id STRING,
    
    -- Original Scenario Reference
    original_scenario_id STRING,
    original_primary_axis STRING,
    
    -- Outcome Details
    outcome_type STRING NOT NULL,
    outcome_value STRING,
    outcome_severity STRING,  -- mild, moderate, severe
    
    -- Timing
    days_since_plan_start INT64,
    days_since_last_assessment INT64,
    
    -- Clinical Context at Outcome
    assessment_source STRING,
    current_rug_group STRING,
    current_chess_score INT64,
    
    -- Metadata
    environment STRING
)
PARTITION BY DATE(timestamp)
CLUSTER BY patient_ref, outcome_type;
```

## Laravel Local Event Logging

Events are first logged to a local database table, then exported to BigQuery via a scheduled job.

### Migration: `bundle_engine_events`

```php
Schema::create('bundle_engine_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('event_type', 50)->index();
    $table->timestamp('event_timestamp');
    
    // Patient context (de-identified for export)
    $table->unsignedBigInteger('patient_id');
    $table->string('patient_ref', 20)->nullable();
    
    // Event payload
    $table->json('payload');
    
    // Export tracking
    $table->boolean('exported_to_bigquery')->default(false);
    $table->timestamp('exported_at')->nullable();
    
    $table->timestamps();
    
    $table->index(['event_type', 'event_timestamp']);
    $table->index(['patient_id', 'event_timestamp']);
    $table->index('exported_to_bigquery');
});
```

## Learning Loop (Future Phase)

### Step 1: Pattern Mining
Analyze correlations between:
- Scenario characteristics → Selection rate
- Scenario characteristics → Outcome improvements
- Algorithm scores → Service intensity effectiveness
- CAP triggers → Outcome prevention

### Step 2: Model Training
Train a scenario ranking model that predicts:
- Probability of selection (user preference)
- Probability of positive outcome (clinical effectiveness)
- Combined score = α * user_preference + β * clinical_effectiveness

### Step 3: Rule Refinement Proposals
Generate proposals to refine:
- Service intensity matrix mappings
- CAP trigger thresholds
- Algorithm score interpretations
- Default service configurations

### Step 4: Human-in-the-Loop Review
Clinical team reviews proposals:
- Accept: Auto-update configuration
- Reject: Log rejection reason for learning
- Modify: Update with clinical input

## Privacy & Compliance

### De-identification
- Patient IDs → Hashed references (P-XXXX)
- Coordinator IDs → Hashed references (C-XXXX)
- No PHI fields exported to BigQuery
- Validation before export

### Data Retention
- Local events: 90 days
- BigQuery: 3 years
- Aggregated metrics: Indefinite

### Access Control
- BigQuery: Service account with limited scope
- Local events: Admin role only
- Audit logging on all queries

## Implementation Checklist

- [x] Design BigQuery schemas
- [ ] Create Laravel migration
- [ ] Implement BundleEventLogger service
- [ ] Add event logging to ScenarioGenerator
- [ ] Add event logging to CarePlanController
- [ ] Create BigQuery export job
- [ ] Set up BigQuery tables in GCP
- [ ] Create outcome tracking triggers
- [ ] Build analytics dashboard
- [ ] Document learning pipeline design

