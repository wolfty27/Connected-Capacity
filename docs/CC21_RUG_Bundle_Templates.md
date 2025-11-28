# CC2.1 – RUG-III/HC Bundle Templates (LTC Stream)

**Version:** 0.1 (Draft)  
**Scope:** Bundled High Intensity Home Care – LTC  
**Note:** All bundles are designed within a **$5,000/week per-patient** envelope. Actual cost will depend on local wage rates and travel assumptions.

These are intentionally opinionated drafts – they reflect the CIHI grouping logic (ADLs, clinical flags, category triggers) and your LTC bundle context, but you can tune frequencies, durations, and CSS details

---

## Legend

- **RUG Category:** RUG-III/HC category (Rehab, Extensive Services, etc.)
- **Triggers:** Key clinical/functional characteristics from CIHI algorithm.
- **ADL Range:** RUG ADL score (x_adlsum).
- **IADL Range:** IADL impairment count (x_iadls).
- **Bundle Intent:** Clinical and system objectives for this template.
- **Services Table:** Default weekly pattern for CC2.1 (to be tuned per SPO).

Each template is identified by a **code** you can store in `care_bundle_templates.code` (e.g., `LTC_RB0_STANDARD`).

---

## 1. RB0 – Special Rehabilitation, High ADL

**Template Code:** `LTC_RB0_STANDARD`  
**RUG Category:** Special Rehabilitation  
**Triggers:**
- Total therapy minutes (PT/OT/SLP) ≥ 120 in last 7 days.
- ADL sum 11–18 (higher physical dependency).
- IADL any value.

**Bundle Intent:**
- Maintain or improve function via intensive rehab while safely supporting high ADL needs at home.
- Avoid LTC admission by replicating sub-acute rehab intensity in the community.

**Default Services (per week):**

| Service                | Freq/wk | Duration | Notes                       |
|------------------------|---------|----------|-----------------------------|
| Physiotherapy Visit    | 3       | 60 min   | Goal-focused, home-based   |
| Occupational Therapy   | 2       | 60 min   | Safety, transfers, ADLs    |
| Speech-Language Path.  | 1       | 45 min   | As indicated                |
| Nursing Visit          | 4       | 45 min   | Med mgmt, wound, monitoring|
| PSW Visit              | 14      | 60 min   | 2x/day ADL support          |
| Case Mgmt / RN Phone   | 3       | 15 min   | Triage + plan adjustments   |
| RPM/Virtual Check-in   | 7       | 10 min   | Daily short check-ins       |
| CSS: Meals             | 5       | -        | Meals-on-wheels, if needed  |
| CSS: Transport         | 1       | -        | Medical appointments        |

**Adjustments / Rules:**

- If ADL ≥ 15, increase PSW visits to 3x/day.
- If patient has complex wounds, add 2 more nursing visits/week.
- If cognitive impairment (CPS ≥ 3) is present, consider adding 2 behavioural activation visits/week.

---

## 2. RA2 – Special Rehabilitation, Lower ADL, Higher IADL

**Template Code:** `LTC_RA2_STANDARD`  
**RUG Category:** Special Rehabilitation  
**Triggers:**
- Therapy minutes ≥ 120.
- ADL 4–10 (lower physical dependency).
- IADL impairment count > 1 (x_iadls > 1).

**Bundle Intent:**
- Support extensive rehab in relatively mobile clients with significant IADL limitations (e.g., can move but cannot manage medications, meals, phone).

**Default Services:**

| Service                | Freq/wk | Duration |
|------------------------|---------|----------|
| Physiotherapy Visit    | 2       | 60 min   |
| Occupational Therapy   | 2       | 60 min   |
| Nursing Visit          | 2       | 45 min   |
| PSW Visit              | 7       | 60 min   |
| Case Mgmt / RN Phone   | 2       | 15 min   |
| CSS: Meals             | 5       | -        |
| CSS: Medication Mgmt   | 3       | -        |
| CSS: Transport         | 1       | -        |

**Adjustments / Rules:**

- If behaviours present, add 1 behavioural support visit/week.
- If caregiver available daily, reduce PSW visits from 7 to 5/wk.

---

## 3. RA1 – Special Rehabilitation, Lower ADL, Lower IADL

**Template Code:** `LTC_RA1_STANDARD`  
**RUG Category:** Special Rehabilitation  
**Triggers:**
- Therapy minutes ≥ 120.
- ADL 4–10.
- IADL impairment count ≤ 1 (x_iadls ≤ 1).

**Bundle Intent:**
- Provide rehab-focused bundle for relatively independent clients who mainly need therapy and light support.

**Default Services:**

| Service                | Freq/wk | Duration |
|------------------------|---------|----------|
| Physiotherapy Visit    | 2       | 60 min   |
| Occupational Therapy   | 1       | 60 min   |
| Nursing Visit          | 1       | 45 min   |
| PSW Visit              | 4       | 45 min   |
| Case Mgmt / RN Phone   | 1       | 15 min   |
| CSS: Meals             | 3       | -        |

**Adjustments / Rules:**

- If ADL rises ≥ 8 at re-assessment, consider upgrading to RB0/RA2 template depending on therapy profile.

---

## 4. SE3 – Extensive Services, Highest Complexity

**Template Code:** `LTC_SE3_MAX_INTENSITY`  
**RUG Category:** Extensive Services  
**Triggers:**
- Presence of parenteral/IV feeding, IV meds, suctioning, trach care, ventilator/respirator.
- ADL sum ≥ 7.
- Extensive-care count (`x_ext_ct`) 4–5 (many additional high-need flags).

**Bundle Intent:**
- Provide near-ICU-level nursing support at home to avoid institutionalization.
- Manage high-risk treatments and complex care safely.

**Default Services:**

| Service                | Freq/wk | Duration |
|------------------------|---------|----------|
| Nursing Shift (RPN/RN) | 14      | 8h       | 2 shifts/day (or equivalent) |
| PSW Visit              | 7       | 60 min   |
| Respiratory Therapy    | 3       | 60 min   |
| Wound Care Nurse       | 3       | 60 min   |
| Case Mgmt / RN Phone   | 7       | 15 min   |
| RPM                    | 7       | 10 min   |
| CSS: Transport         | 1       | -        |

**Adjustments / Rules:**

- Use configurable mapping to shift-based vs visit-based patterns depending on local workforce.
- Tight budget management: if costs exceed cap, use rule engine to prioritize nursing over CSS add-ons.

---

## 5. SE2 – Extensive Services, Moderate Complexity

**Template Code:** `LTC_SE2_STANDARD`  
**RUG Category:** Extensive Services  
**Triggers:**
- Same extensive services, with `x_ext_ct` 2–3.

**Bundle Intent:**
- High-frequency nursing with some continuous coverage, but not full shift coverage everyday.

**Default Services:**

| Service            | Freq/wk | Duration |
|--------------------|---------|----------|
| Nursing Visit      | 14      | 60 min   |
| PSW Visit          | 7       | 60 min   |
| Respiratory/Wound  | 2       | 60 min   |
| Case Mgmt          | 5       | 15 min   |
| RPM                | 7       | 10 min   |

---

## 6. SE1 – Extensive Services, Lower Additional Complexity

**Template Code:** `LTC_SE1_STANDARD`  
**Triggers:**  
- Extensive services present, `x_ext_ct` 0–1.

**Bundle Intent:**
- Support one or two extensive therapies with strong daily nursing and PSW coverage.

**Default Services:**

| Service        | Freq/wk | Duration |
|----------------|---------|----------|
| Nursing Visit  | 10      | 60 min   |
| PSW Visit      | 7       | 60 min   |
| Case Mgmt      | 3       | 15 min   |
| RPM            | 7       | 10 min   |

---

## 7. SSB – Special Care, High ADL

**Template Code:** `LTC_SSB_STANDARD`  
**RUG Category:** Special Care  
**Triggers:**
- Special care indicators (stage 3/4 pressure ulcers + turning, complex nutrition, severe neuro, etc.) with ADL 14–18.

**Bundle Intent:**
- Maintain medically fragile patient with severe physical dependency and specific high-risk conditions outside LTC.

**Default Services:**

| Service         | Freq/wk | Duration |
|-----------------|---------|----------|
| Nursing Visit   | 10      | 45–60min |
| PSW Visit       | 21      | 60 min   | (3x/day) |
| Wound Care      | 3       | 60 min   |
| OT              | 1       | 60 min   |
| Case Mgmt       | 3       | 15 min   |
| CSS: Meals      | 7       | -        |
| CSS: Transport  | 1       | -        |

---

## 8. SSA – Special Care, Lower ADL

**Template Code:** `LTC_SSA_STANDARD`  
**Triggers:**
- Special care indicators with ADL 4–13 OR extensive care with ADL ≤ 6.

**Bundle Intent:**
- Support clinical complexity with moderate physical dependency.

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| Nursing Visit | 6       | 45 min   |
| PSW Visit     | 14      | 60 min   |
| Wound/Neuro   | 2       | 60 min   |
| Case Mgmt     | 2       | 15 min   |

---

## 9. CC0 – Clinically Complex, High ADL

**Template Code:** `LTC_CC0_STANDARD`  
**RUG Category:** Clinically Complex  
**Triggers:**
- Clinically complex flags (e.g. dehydration, pneumonia, end-stage disease, dialysis, chemo, oxygen, etc.).
- ADL 11–18.

**Bundle Intent:**
- High-touch nursing and PSW support with strong monitoring to prevent ED/hospital use.

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| Nursing Visit | 7       | 45 min   |
| PSW Visit     | 21      | 60 min   |
| OT/PT         | 1–2     | 60 min   |
| Case Mgmt     | 3       | 15 min   |
| RPM           | 7       | 10 min   |
| CSS: Meals    | 7       | -        |

---

## 10. CB0 – Clinically Complex, Moderate ADL

**Template Code:** `LTC_CB0_STANDARD`  
**Triggers:**  
- Clinically complex; ADL 6–10.

**Bundle Intent:**
- High clinical complexity but somewhat lower physical assistance needs.

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| Nursing Visit | 5       | 45 min   |
| PSW Visit     | 14      | 60 min   |
| OT/PT         | 1–2     | 60 min   |
| Case Mgmt     | 3       | 15 min   |
| CSS           | 5       | -        |

---

## 11. CA2 – Clinically Complex, Low ADL, Higher IADL

**Template Code:** `LTC_CA2_STANDARD`  
**Triggers:**  
- Clinically complex; ADL 4–5.
- IADL impairment ≥ 1.

**Bundle Intent:**
- Manage medical complexity where ADLs are relatively intact but IADLs impaired.

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| Nursing Visit | 3       | 45 min   |
| PSW Visit     | 7       | 45 min   |
| Case Mgmt     | 2       | 15 min   |
| CSS: Meals    | 5       | -        |
| CSS: Med Mgmt | 3       | -        |

---

## 12. CA1 – Clinically Complex, Low ADL, Low IADL

**Template Code:** `LTC_CA1_STANDARD`  

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| Nursing Visit | 3       | 45 min   |
| PSW Visit     | 4       | 45 min   |
| Case Mgmt     | 1       | 15 min   |
| CSS: Meals    | 3       | -        |

---

## 13. IB0 – Impaired Cognition, Moderate ADL

**Template Code:** `LTC_IB0_STANDARD`  
**RUG Category:** Impaired Cognition  
**Triggers:**
- CPS ≥ 3, ADL 6–10.

**Bundle Intent:**
- Dementia/cognitive bundles: prevent LTC by providing consistent PSW support + caregiver coaching.

**Default Services:**

| Service                   | Freq/wk | Duration |
|---------------------------|---------|----------|
| PSW Visit                 | 21      | 60 min   |
| Nursing Visit             | 2       | 45 min   |
| Behavioural Support PSW   | 2       | 60 min   |
| Activation / Recreation   | 3       | 60 min   |
| Caregiver Education/Coach | 1       | 60 min   |
| Case Mgmt                 | 2       | 15 min   |
| CSS: Respite              | 1       | 4h block |

---

## 14. IA2 – Impaired Cognition, Lower ADL, Higher IADL

**Template Code:** `LTC_IA2_STANDARD`  

**Default Services:**

| Service          | Freq/wk | Duration |
|------------------|---------|----------|
| PSW Visit        | 14      | 60 min   |
| Nursing Visit    | 1–2     | 45 min   |
| Activation       | 2       | 60 min   |
| Caregiver Support| 1       | 60 min   |
| CSS: Meals       | 5       | -        |

---

## 15. IA1 – Impaired Cognition, Lower ADL, Lower IADL

**Template Code:** `LTC_IA1_STANDARD`  

**Default Services:**

| Service          | Freq/wk | Duration |
|------------------|---------|----------|
| PSW Visit        | 10      | 60 min   |
| Nursing Visit    | 1       | 45 min   |
| Activation       | 2       | 60 min   |
| Caregiver Support| 1       | 60 min   |

---

## 16. BB0 – Behaviour Problems, Moderate ADL

**Template Code:** `LTC_BB0_STANDARD`  
**RUG Category:** Behaviour Problems  
**Triggers:**
- Behavioural symptoms ≥ 1 day/week (wandering, verbal/physical abuse, etc.).
- ADL 6–10.

**Bundle Intent:**
- Intense behavioural support + structure to prevent crisis and LTC.

**Default Services:**

| Service                  | Freq/wk | Duration |
|--------------------------|---------|----------|
| PSW Visit                | 21      | 60 min   |
| Behavioural Nursing      | 3       | 60 min   |
| Activation / Recreation  | 3       | 60 min   |
| Case Mgmt / Behavioural  | 3       | 30 min   |
| CSS: Respite             | 1–2     | 4h block |

---

## 17. BA2 – Behaviour Problems, Lower ADL, Higher IADL

**Template Code:** `LTC_BA2_STANDARD`  

**Default Services:**

| Service                  | Freq/wk | Duration |
|--------------------------|---------|----------|
| PSW Visit                | 14      | 60 min   |
| Behavioural Nursing      | 2       | 60 min   |
| Activation               | 3       | 60 min   |
| Case Mgmt                | 2       | 30 min   |
| CSS: Respite             | 1       | 4h block |

---

## 18. BA1 – Behaviour Problems, Lower ADL, Lower IADL

**Template Code:** `LTC_BA1_STANDARD`  

**Default Services:**

| Service             | Freq/wk | Duration |
|---------------------|---------|----------|
| PSW Visit           | 10      | 60 min   |
| Behavioural Nursing | 2       | 60 min   |
| Activation          | 2       | 60 min   |
| Case Mgmt           | 2       | 30 min   |

---

## 19. PD0 – Reduced Physical Function, High ADL

**Template Code:** `LTC_PD0_STANDARD`  
**RUG Category:** Reduced Physical Function  
**Triggers:**  
- No higher categories; ADL 11–18.

**Bundle Intent:**
- Intensive PSW support for mobility and self-care.

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| PSW Visit     | 21      | 60 min   |
| Nursing Visit | 2       | 45 min   |
| OT            | 1       | 60 min   |
| Case Mgmt     | 2       | 15 min   |
| CSS: Meals    | 7       | -        |

---

## 20. PC0 – Reduced Physical Function, ADL 9–10

**Template Code:** `LTC_PC0_STANDARD`  

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| PSW Visit     | 14      | 60 min   |
| Nursing Visit | 1–2     | 45 min   |
| OT/PT         | 1       | 60 min   |
| Case Mgmt     | 1       | 15 min   |
| CSS: Meals    | 5       | -        |

---

## 21. PB0 – Reduced Physical Function, ADL 6–8

**Template Code:** `LTC_PB0_STANDARD`  

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| PSW Visit     | 10      | 60 min   |
| Nursing Visit | 1       | 45 min   |
| OT            | 1       | 60 min   |
| Case Mgmt     | 1       | 15 min   |

---

## 22. PA2 – Reduced Physical Function, Low ADL, Higher IADL

**Template Code:** `LTC_PA2_STANDARD`  

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| PSW Visit     | 7       | 45–60min |
| Nursing Visit | 1       | 45 min   |
| Case Mgmt     | 1       | 15 min   |
| CSS: Meals    | 5       | -        |
| CSS: Med Mgmt | 2       | -        |

---

## 23. PA1 – Reduced Physical Function, Low ADL, Lower IADL

**Template Code:** `LTC_PA1_STANDARD`  

**Default Services:**

| Service       | Freq/wk | Duration |
|---------------|---------|----------|
| PSW Visit     | 5       | 45–60min |
| Nursing Visit | 1       | 45 min   |
| Case Mgmt     | 1       | 15 min   |

---

## Cross-Template Rules

- **24/7 Urgent Response:**  
  For all LTC bundles, ensure at least:
  - 24/7 on-call access via SPO, even if not explicit in service list (organizational capability requirement).

- **Missed Care Prevention:**  
  High-frequency PSW/Nursing templates must include redundancy (a second worker pool) so missed visits can be mitigated.

- **Reassessment & Rebanding:**  
  On each interRAI reassessment, RUG classification is recomputed; Bundle Engine should propose:
  - Keep current template
  - Downshift or upshift to neighbouring template based on changes in ADL, IADL, and flags.

---