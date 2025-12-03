# Algorithm & CAP Trigger DSL Specification

## AI-Assisted Bundle Engine v2.2

**Version**: 1.0.0  
**Date**: 2025-12-03

---

## Overview

This document defines the Domain-Specific Languages (DSLs) used by the Bundle Engine's interpreter engines:

1. **Algorithm DSL** (JSON) - For InterRAI CA decision-support algorithms
2. **CAP Trigger DSL** (YAML) - For Clinical Assessment Protocol triggers
3. **Service Intensity Matrix** (JSON) - For algorithm→service mappings

The goal is to keep clinical logic in **data files** rather than PHP code, enabling:
- Easy updates without code deployments
- Clinical review/approval workflows
- Future AI-assisted rule refinement
- Clear audit trails

---

## 1. Algorithm DSL (JSON)

### 1.1 File Location

```
config/bundle_engine/algorithms/
├── self_reliance_index.json
├── assessment_urgency.json
├── service_urgency.json
├── rehabilitation.json
├── personal_support.json
├── distressed_mood.json
├── pain_scale.json
└── chess_ca.json
```

### 1.2 Schema Definition

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "InterRAI Algorithm Definition",
  "type": "object",
  "required": ["name", "version", "output_range", "tree"],
  "properties": {
    "name": {
      "type": "string",
      "description": "Human-readable algorithm name"
    },
    "version": {
      "type": "string",
      "pattern": "^\\d+\\.\\d+\\.\\d+(-[a-z]+)?$",
      "description": "Semantic version with optional suffix (e.g., '1.0.0-draft')"
    },
    "verification_status": {
      "type": "string",
      "enum": ["unverified", "hc_mapped", "draft_verified", "verified"],
      "default": "unverified"
    },
    "verification_source": {
      "type": ["string", "null"],
      "description": "Reference to authoritative source document"
    },
    "verification_date": {
      "type": ["string", "null"],
      "format": "date"
    },
    "verified_by": {
      "type": ["string", "null"],
      "description": "Name/role of verifier"
    },
    "output_range": {
      "type": "array",
      "items": {"type": "integer"},
      "minItems": 2,
      "maxItems": 2,
      "description": "[min, max] output score range"
    },
    "output_type": {
      "type": "string",
      "enum": ["integer", "boolean", "category"],
      "default": "integer"
    },
    "description": {
      "type": "string"
    },
    "items_used": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "ca_code": {"type": "string"},
          "hc_code": {"type": "string"},
          "description": {"type": "string"},
          "mapping_notes": {"type": "string"}
        }
      },
      "description": "Documentation of CA→HC item mappings"
    },
    "computed_inputs": {
      "type": "object",
      "additionalProperties": {
        "type": "object",
        "properties": {
          "formula": {"type": "string"},
          "description": {"type": "string"}
        }
      },
      "description": "Derived values calculated before tree evaluation"
    },
    "tree": {
      "$ref": "#/definitions/decision_node"
    }
  },
  "definitions": {
    "decision_node": {
      "oneOf": [
        {
          "type": "object",
          "required": ["return"],
          "properties": {
            "return": {
              "type": ["integer", "boolean", "string"]
            }
          },
          "description": "Leaf node - returns a value"
        },
        {
          "type": "object",
          "required": ["condition", "true_branch", "false_branch"],
          "properties": {
            "condition": {"type": "string"},
            "true_branch": {"$ref": "#/definitions/decision_node"},
            "false_branch": {"$ref": "#/definitions/decision_node"}
          },
          "description": "Branch node - evaluates condition"
        }
      ]
    }
  }
}
```

### 1.3 Condition Syntax

Conditions are simple expressions evaluated against the input data:

| Operator | Example | Description |
|----------|---------|-------------|
| `==` | `C1 == 0` | Equality |
| `!=` | `C1 != 0` | Inequality |
| `>=` | `C1 >= 2` | Greater than or equal |
| `<=` | `C1 <= 1` | Less than or equal |
| `>` | `C1 > 0` | Greater than |
| `<` | `C1 < 3` | Less than |
| `&&` | `C1 == 0 && C2a == 0` | Logical AND |
| `||` | `C1 >= 2 || C2a >= 2` | Logical OR |
| `== true` | `SRI == true` | Boolean check |
| `== false` | `SRI == false` | Boolean check |

### 1.4 Computed Inputs

Computed inputs allow complex calculations before tree traversal:

```json
{
  "computed_inputs": {
    "SRI": {
      "formula": "C1 == 0 && C2a == 0 && C2b == 0 && C2c == 0 && C2d == 0 && C2e == 0",
      "description": "Self-Reliance Index: true if no impairment in decision-making or ADL"
    },
    "ADL_deficit_count": {
      "formula": "(C2a > 0 ? 1 : 0) + (C2b > 0 ? 1 : 0) + (C2c > 0 ? 1 : 0) + (C2d > 0 ? 1 : 0) + (C2e > 0 ? 1 : 0)",
      "description": "Count of ADL items with any impairment"
    }
  }
}
```

Supported formula syntax:
- Arithmetic: `+`, `-`, `*`, `/`
- Comparison: `==`, `!=`, `>=`, `<=`, `>`, `<`
- Logical: `&&`, `||`, `!`
- Ternary: `condition ? value_if_true : value_if_false`
- Grouping: `(expression)`

### 1.5 Example: Rehabilitation Algorithm

```json
{
  "name": "Rehabilitation Algorithm",
  "version": "1.0.0-hc_mapped",
  "verification_status": "hc_mapped",
  "verification_source": "InterRAI CA Manual Section 4.4",
  "output_range": [1, 5],
  "output_type": "integer",
  "description": "Identifies persons who may be candidates for PT/OT rehabilitation services",
  
  "items_used": [
    {"ca_code": "C1", "hc_code": "iB3a", "description": "Decision making"},
    {"ca_code": "C2a", "hc_code": "iG1ga", "description": "Bathing"},
    {"ca_code": "C2b", "hc_code": "iG1ia", "description": "Bath transfer (mapped from transfer)"},
    {"ca_code": "C2c", "hc_code": "iG1fa", "description": "Personal hygiene"},
    {"ca_code": "C2d", "hc_code": "iG1ca", "description": "Dressing lower body"},
    {"ca_code": "C2e", "hc_code": "iG1ha", "description": "Locomotion (mapped from bed mobility)"},
    {"ca_code": "D3a", "hc_code": "iG1aa", "description": "Meal preparation"},
    {"ca_code": "D3b", "hc_code": "iG1da", "description": "Ordinary housework"},
    {"ca_code": "D3c", "hc_code": "iadl_medications", "description": "Medication management"},
    {"ca_code": "D3d", "hc_code": null, "description": "Stair use (NOT AVAILABLE)", "mapping_notes": "Default to 0"},
    {"ca_code": "D4", "hc_code": null, "description": "ADL decline (NOT AVAILABLE)", "mapping_notes": "Default to 0"},
    {"ca_code": "B2c", "hc_code": null, "description": "Palliative referral", "mapping_notes": "Check referral_type"}
  ],

  "computed_inputs": {
    "SRI": {
      "formula": "C1 == 0 && C2a == 0 && C2b == 0 && C2c == 0 && C2d == 0 && C2e == 0",
      "description": "Self-Reliance Index"
    },
    "IADL_decline": {
      "formula": "D3a > 0 || D3b > 0 || D3c > 0 || D3d > 0",
      "description": "Any IADL decline indicator"
    },
    "IADL_deficit_count": {
      "formula": "(D3a >= 3 ? 1 : 0) + (D3b >= 3 ? 1 : 0) + (D3c >= 3 ? 1 : 0) + (D3d >= 3 ? 1 : 0)",
      "description": "Count of IADL items with score >= 3"
    },
    "ADL_deficit_count": {
      "formula": "(C2a > 0 ? 1 : 0) + (C2b > 0 ? 1 : 0) + (C2c > 0 ? 1 : 0) + (C2d > 0 ? 1 : 0) + (C2e > 0 ? 1 : 0)",
      "description": "Count of ADL items with any impairment"
    }
  },

  "tree": {
    "condition": "B2c == true",
    "true_branch": { "return": 1 },
    "false_branch": {
      "condition": "SRI == true",
      "true_branch": {
        "condition": "IADL_decline == true",
        "true_branch": { "return": 2 },
        "false_branch": { "return": 1 }
      },
      "false_branch": {
        "condition": "D4 >= 1",
        "true_branch": {
          "condition": "IADL_deficit_count >= 3",
          "true_branch": { "return": 5 },
          "false_branch": {
            "condition": "IADL_deficit_count >= 1",
            "true_branch": { "return": 4 },
            "false_branch": { "return": 3 }
          }
        },
        "false_branch": {
          "condition": "ADL_deficit_count >= 3",
          "true_branch": { "return": 3 },
          "false_branch": { "return": 2 }
        }
      }
    }
  }
}
```

---

## 2. CAP Trigger DSL (YAML)

### 2.1 File Location

```
config/bundle_engine/cap_triggers/
├── functional/
│   ├── adl.yaml
│   └── iadl.yaml
├── clinical/
│   ├── falls.yaml
│   ├── pain.yaml
│   └── pressure_ulcer.yaml
├── cognition/
│   ├── cognitive_loss.yaml
│   └── mood.yaml
└── social/
    ├── informal_support.yaml
    └── social_relationship.yaml
```

### 2.2 Schema Definition

```yaml
# CAP Trigger Schema
name: string (required)
version: string (required, semver)
source: string (reference to CAPs manual section)
applicable_instruments: [HC, LTCF, CA] (required)
category: string (functional, clinical, cognition, social)

triggers:
  - level: IMPROVE | PREVENT | NOT_TRIGGERED (required)
    description: string
    conditions:
      all: # All conditions must be true
        - field: string
          operator: "==" | "!=" | ">=" | "<=" | ">" | "<"
          value: any
      any: # At least one condition must be true
        - field: string
          operator: string
          value: any
      min_count: # At least N of the following must be true
        count: integer
        from:
          - field: string
            operator: string
            value: any
    service_recommendations:
      SERVICE_CODE:
        priority: core | recommended | optional
        frequency_multiplier: number (optional, default 1.0)
        focus: string (area of focus)
    care_guidelines:
      - string (care guideline text)
```

### 2.3 Condition Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `==` | Equals | `field: cognitive_complexity, operator: "==", value: 0` |
| `!=` | Not equals | `field: has_recent_fall, operator: "!=", value: true` |
| `>=` | Greater or equal | `field: pain_score, operator: ">=", value: 2` |
| `<=` | Less or equal | `field: adl_support_level, operator: "<=", value: 3` |
| `>` | Greater than | `field: mobility_complexity, operator: ">", value: 0` |
| `<` | Less than | `field: chess_ca_score, operator: "<", value: 4` |

### 2.4 CAP-Required Fields

The CAP engine expects fields from `PatientNeedsProfile::toCAPInput()`:

```php
// PatientNeedsProfile::toCAPInput() returns:
[
    // Fall Risk
    'has_recent_fall' => bool,
    'falls_risk_level' => int (0-3),
    
    // Mobility & Function
    'mobility_complexity' => int (0-6),
    'adl_support_level' => int (0-6),
    'iadl_support_level' => int (0-6),
    
    // Cognition & Behaviour
    'cognitive_complexity' => int (0-6),
    'has_delirium' => bool,
    'behavioural_complexity' => int (0-6),
    
    // Clinical Risk
    'pain_score' => int (0-4),
    'health_instability' => int (0-5), // CHESS
    'has_pressure_ulcer_risk' => bool,
    'has_polypharmacy_risk' => bool,
    
    // Environment & Support
    'has_home_environment_risk' => bool,
    'caregiver_stress_level' => int (0-4),
    'lives_alone' => bool,
    
    // Recent Events
    'has_recent_hospital_stay' => bool,
    'has_recent_er_visit' => bool,
    
    // Assessment Context
    'has_full_hc_assessment' => bool,
    'episode_type' => string,
    'rehab_potential_score' => int (0-100),
    
    // Algorithm Scores (from CA)
    'personal_support_score' => int (1-6),
    'rehabilitation_score' => int (1-5),
    'chess_ca_score' => int (0-5),
    'distressed_mood_score' => int (0-9),
]
```

### 2.5 Example: Falls CAP

```yaml
name: Falls CAP
version: "1.0.0"
source: "InterRAI CAPs Manual Section 16"
applicable_instruments: [HC, LTCF]
category: clinical

triggers:
  - level: IMPROVE
    description: "Recent fall with modifiable risk factors"
    conditions:
      all:
        - field: has_recent_fall
          operator: "=="
          value: true
      min_count:
        count: 2
        from:
          - field: mobility_complexity
            operator: ">="
            value: 2
          - field: has_polypharmacy_risk
            operator: "=="
            value: true
          - field: has_home_environment_risk
            operator: "=="
            value: true
          - field: has_delirium
            operator: "=="
            value: true
          - field: pain_score
            operator: ">="
            value: 2
          - field: cognitive_complexity
            operator: ">="
            value: 2
    service_recommendations:
      PT:
        priority: core
        frequency_multiplier: 1.5
        focus: balance_strength
      OT:
        priority: recommended
        focus: home_safety
      NUR:
        priority: core
        focus: medication_review
    care_guidelines:
      - "Assess and modify environmental hazards"
      - "Review medications for fall-risk drugs"
      - "Implement balance and strength training"
      - "Consider assistive devices"

  - level: PREVENT
    description: "Risk factors present but no recent fall"
    conditions:
      any:
        - field: falls_risk_level
          operator: ">="
          value: 2
      min_count:
        count: 1
        from:
          - field: mobility_complexity
            operator: ">="
            value: 2
          - field: has_polypharmacy_risk
            operator: "=="
            value: true
    service_recommendations:
      PT:
        priority: recommended
        focus: prevention
    care_guidelines:
      - "Monitor for fall risk factors"
      - "Educate patient and family on fall prevention"

  - level: NOT_TRIGGERED
    description: "No significant fall risk identified"
    conditions:
      default: true
```

---

## 3. Service Intensity Matrix (JSON)

### 3.1 File Location

```
config/bundle_engine/service_intensity_matrix.json
```

### 3.2 Schema Definition

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "Service Intensity Matrix",
  "type": "object",
  "required": ["version", "last_updated"],
  "properties": {
    "version": {"type": "string"},
    "last_updated": {"type": "string", "format": "date"},
    "updated_by": {"type": "string"},
    "review_status": {
      "type": "string",
      "enum": ["draft", "under_review", "approved"]
    },
    "next_review_date": {"type": "string", "format": "date"}
  },
  "patternProperties": {
    "^[a-z_]+_to_[a-z_]+$": {
      "type": "object",
      "properties": {
        "description": {"type": "string"},
        "source": {
          "type": "object",
          "properties": {
            "primary": {"type": "string"},
            "secondary": {"type": "string"},
            "notes": {"type": "string"}
          }
        },
        "mappings": {
          "type": "object",
          "additionalProperties": {
            "type": "object",
            "required": ["hours", "label"],
            "properties": {
              "hours": {"type": "number"},
              "visits": {"type": "number"},
              "label": {"type": "string"},
              "confidence": {
                "type": "string",
                "enum": ["high", "medium", "exploratory"]
              },
              "rationale": {"type": "string"}
            }
          }
        },
        "modifiers": {
          "type": "object",
          "additionalProperties": {
            "type": "object",
            "properties": {
              "multiplier": {"type": "number"},
              "reason": {"type": "string"},
              "confidence": {"type": "string"},
              "source": {"type": "string"}
            }
          }
        }
      }
    }
  }
}
```

### 3.3 Example Structure

```json
{
  "version": "1.0.0",
  "last_updated": "2025-12-03",
  "updated_by": "Clinical Review Team",
  "review_status": "draft",
  "next_review_date": "2026-03-03",
  
  "psa_to_psw_hours": {
    "description": "Personal Support Algorithm score → PSW hours/week",
    "source": {
      "primary": "Ontario Home Care PSA Implementation Guide (2019)",
      "notes": "Ranges validated against utilization data"
    },
    "mappings": {
      "1": {"hours": 0, "label": "No PSW needed", "confidence": "high", "rationale": "SRI=1 indicates self-reliance"},
      "2": {"hours": 3, "label": "Minimal support", "confidence": "high", "rationale": "Light housekeeping or reminders"},
      "3": {"hours": 7, "label": "Moderate support", "confidence": "high", "rationale": "ADL assistance required but not daily"},
      "4": {"hours": 14, "label": "Moderate-high", "confidence": "medium", "rationale": "Daily ADL support; 2hrs/day"},
      "5": {"hours": 21, "label": "High support", "confidence": "medium", "rationale": "Extensive daily support; 3hrs/day"},
      "6": {"hours": 35, "label": "Very high", "confidence": "exploratory", "rationale": "Approaching 24hr care threshold"}
    },
    "modifiers": {
      "ADL_IMPROVEMENT_CAP": {
        "multiplier": 1.25,
        "reason": "ADL CAP triggered - increased PSW for restorative focus",
        "confidence": "medium"
      },
      "CAREGIVER_RELIEF_AXIS": {
        "multiplier": 1.15,
        "reason": "Caregiver relief scenario selected",
        "confidence": "medium"
      }
    }
  },
  
  "rehab_to_therapy_visits": {
    "description": "Rehabilitation Algorithm score → PT/OT visits/week",
    "source": {
      "primary": "InterRAI CA Rehabilitation Algorithm Paper (Hirdes et al., 2008)"
    },
    "mappings": {
      "1": {"visits": 0, "label": "No therapy indicated", "confidence": "high"},
      "2": {"visits": 1, "label": "Maintenance therapy", "confidence": "high"},
      "3": {"visits": 2, "label": "Moderate rehabilitation", "confidence": "high"},
      "4": {"visits": 3, "label": "Intensive rehabilitation", "confidence": "medium"},
      "5": {"visits": 5, "label": "Very intensive", "confidence": "exploratory"}
    }
  },
  
  "chess_to_nursing_visits": {
    "description": "CHESS-CA score → Nursing visits/week",
    "source": {
      "primary": "CHESS Scale Validation (Hirdes et al., 2003)"
    },
    "mappings": {
      "0": {"visits": 0, "label": "Stable - PRN only", "confidence": "high"},
      "1": {"visits": 0.5, "label": "Low instability", "confidence": "high"},
      "2": {"visits": 1, "label": "Moderate instability", "confidence": "high"},
      "3": {"visits": 2, "label": "High instability", "confidence": "medium"},
      "4": {"visits": 3, "label": "Very high instability", "confidence": "medium"},
      "5": {"visits": 5, "label": "Critical - daily nursing", "confidence": "exploratory"}
    }
  }
}
```

---

## 4. Engine Interfaces

### 4.1 DecisionTreeEngine

```php
interface DecisionTreeEngineInterface
{
    /**
     * Load an algorithm definition from a JSON file.
     */
    public function loadAlgorithm(string $algorithmName): array;
    
    /**
     * Evaluate an algorithm against input data.
     * 
     * @param string $algorithmName e.g., 'rehabilitation', 'personal_support'
     * @param array $input Key-value pairs of item codes and values
     * @return int|bool The algorithm output score
     */
    public function evaluate(string $algorithmName, array $input): int|bool;
    
    /**
     * Get metadata about an algorithm without evaluating it.
     */
    public function getAlgorithmMeta(string $algorithmName): array;
    
    /**
     * Validate an algorithm definition against the schema.
     */
    public function validateAlgorithm(array $definition): bool;
}
```

### 4.2 CAPTriggerEngine

```php
interface CAPTriggerEngineInterface
{
    /**
     * Load a CAP trigger definition from a YAML file.
     */
    public function loadCAP(string $capName): array;
    
    /**
     * Evaluate a CAP against profile data.
     * 
     * @param string $capName e.g., 'falls', 'pain', 'adl'
     * @param array $profileData Output of PatientNeedsProfile::toCAPInput()
     * @return array{level: string, description: string, recommendations: array, guidelines: array}
     */
    public function evaluate(string $capName, array $profileData): array;
    
    /**
     * Evaluate all applicable CAPs for a profile.
     * 
     * @return array<string, array> Map of CAP name to trigger result
     */
    public function evaluateAll(array $profileData): array;
    
    /**
     * Get list of available CAPs.
     */
    public function getAvailableCAPs(): array;
}
```

### 4.3 ServiceIntensityResolver

```php
interface ServiceIntensityResolverInterface
{
    /**
     * Resolve algorithm scores to service intensities.
     * 
     * @param array $algorithmScores ['rehabilitation' => 3, 'personal_support' => 4, ...]
     * @param array $triggeredCAPs ['falls' => [...], 'pain' => [...], ...]
     * @param string|null $scenarioAxis Current scenario axis (for modifiers)
     * @return array<string, array{hours?: float, visits?: float, rationale: string}>
     */
    public function resolve(
        array $algorithmScores,
        array $triggeredCAPs,
        ?string $scenarioAxis = null
    ): array;
    
    /**
     * Get the service intensity for a specific algorithm.
     */
    public function getServiceIntensity(string $mapping, int $score): array;
}
```

---

## 5. Input Data Preparation

### 5.1 CA Item Code Mapping

When evaluating CA algorithms using HC data, use this mapping:

```php
class CAItemMapper
{
    private const CA_TO_HC_MAP = [
        // Section C - Preliminary Screener
        'C1'  => 'iB3a',    // Decision making
        'C2a' => 'iG1ga',   // Bathing
        'C2b' => 'iG1ia',   // Bath transfer (from transfer)
        'C2c' => 'iG1fa',   // Personal hygiene
        'C2d' => 'iG1ca',   // Dressing lower body
        'C2e' => 'iG1ha',   // Locomotion (from bed mobility)
        'C3'  => 'dyspnea', // Dyspnea
        'C5c' => 'mood_sad_expressions', // Sad/depressed
        
        // Section D - Extended Evaluation
        'D3a' => 'iG1aa',   // Meal preparation
        'D3b' => 'iG1da',   // Ordinary housework
        'D3c' => 'iadl_medications', // Medication management
        'D3d' => null,      // Stair use (NOT AVAILABLE - default 0)
        'D4'  => null,      // ADL decline (NOT AVAILABLE - default 0)
        'D8a' => 'iJ1a',    // Daily pain (frequency >= 3)
        'D19b' => 'caregiver_stress', // Family overwhelmed
        
        // Section E - Mood
        'E1a' => 'mood_negative_statements',
        'E1b' => 'mood_sad_expressions',
        'E1c' => 'mood_crying',
        
        // Section G - Health
        'G1a' => 'iJ1a',    // Pain frequency
        'G1b' => 'iJ1b',    // Pain intensity
    ];
    
    public static function mapToCAInput(array $rawItems): array
    {
        $caInput = [];
        foreach (self::CA_TO_HC_MAP as $caCode => $hcCode) {
            if ($hcCode === null) {
                $caInput[$caCode] = 0; // Default for unavailable items
            } else {
                $caInput[$caCode] = $rawItems[$hcCode] ?? 0;
            }
        }
        return $caInput;
    }
}
```

---

## 6. Validation & Testing

### 6.1 Schema Validation

All algorithm and CAP files are validated on load:

```php
// Algorithm validation
$validator = new AlgorithmSchemaValidator();
$errors = $validator->validate($algorithmJson);

// CAP validation
$validator = new CAPSchemaValidator();
$errors = $validator->validate($capYaml);
```

### 6.2 Test Cases (JSON)

Test cases can be embedded in algorithm files:

```json
{
  "test_cases": [
    {
      "name": "Self-reliant patient",
      "input": {"C1": 0, "C2a": 0, "C2b": 0, "C2c": 0, "C2d": 0, "C2e": 0, "D3a": 0, "D3b": 0, "D3c": 0, "D3d": 0, "D4": 0, "B2c": false},
      "expected_output": 1,
      "description": "No impairments → minimal rehab need"
    },
    {
      "name": "High ADL deficit with decline",
      "input": {"C1": 2, "C2a": 3, "C2b": 2, "C2c": 2, "C2d": 3, "C2e": 2, "D3a": 4, "D3b": 4, "D3c": 3, "D3d": 0, "D4": 1, "B2c": false},
      "expected_output": 5,
      "description": "ADL decline + high IADL deficit → maximum rehab"
    }
  ]
}
```

---

## Appendix: Verification Status Workflow

```
unverified → hc_mapped → draft_verified → verified
     │           │              │
     └─ Initial  └─ Using HC    └─ Checked against
        creation    data mapping   InterRAI docs
                                        │
                                        └─ Clinical sign-off
                                           (production ready)
```

---

*Document Version: 1.0.0*  
*Last Updated: 2025-12-03*

