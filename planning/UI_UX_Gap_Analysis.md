# Current SPA vs UI/UX Spec – Gap Analysis

**Date:** 2025-11-23
**Comparison:** `UI:UX/` Wireframes vs `resources/js/` Codebase

---

## 1. High-Level Architecture & Layout

| Feature | Intended UX (Spec v1) | Current Implementation | Gap / Severity |
| :--- | :--- | :--- | :--- |
| **Theme** | Light, Clean, Teal/Indigo/Slate-50. | Dark Mode (`bg-gray-900`, `text-white`). | **CRITICAL**: Complete visual mismatch. Needs global theme switch. |
| **Sidebar** | White, Light Borders, Teal Active State. | Dark/Transparent (`bg-gray-800/50`), Blue Active State. | **HIGH**: Needs restyling to match "Medical/Professional" look. |
| **Top Bar** | White, Sticky, Breadcrumbs, Search, User Profile. | Dark (`bg-gray-800/30`), Minimal content. | **MEDIUM**: Missing breadcrumbs and search. |
| **Container** | Centered `max-w-7xl`, `bg-slate-50`. | Full width, Dark background. | **HIGH**: Layout structure needs adjustment. |

## 2. Navigation & IA

| Feature | Intended UX (Spec v1) | Current Implementation | Gap / Severity |
| :--- | :--- | :--- | :--- |
| **SPO Links** | Dashboard, Intake (Referrals, TNP), Care Mgmt, Network. | Dashboard, Patients, Settings. | **HIGH**: Missing core business modules (Referrals, Network). |
| **Role Logic** | Distinct sections for SPO vs SSPO vs Field. | Basic role checks exist (`Sidebar.jsx`), but sections are incomplete. | **MEDIUM**: Logic is there, content is missing. |
| **Mobile** | Dedicated Bottom Nav for Field Staff. | Responsive Sidebar only. | **MEDIUM**: Field Staff experience is suboptimal. |

## 3. Component Library

| Component | Intended UX (Spec v1) | Current Implementation | Gap / Severity |
| :--- | :--- | :--- | :--- |
| **Cards** | White, Shadow-sm, Rounded-xl. | Basic `Card.jsx` exists (likely unstyled or basic). | **MEDIUM**: Need to standardize "Premium" card look. |
| **Data Tables** | Avatars, Status Pills, Action Menus, Clean Headers. | `DataTable.jsx` exists but likely generic. | **HIGH**: Worklists are the core of the app; need rich tables. |
| **Wizards** | Stepper Header, Centered Form, Bottom Actions. | Missing. | **HIGH**: "Referral Wizard" and "Onboarding" need this pattern. |
| **AI Widgets** | "Gemini" branded containers/buttons. | Missing. | **MEDIUM**: AI integration points need visual distinction. |

## 4. Page-Level Gaps

### 4.1 Dashboard (Home)
*   **Spec**: KPI Grid (Missed Care, Capacity), Partner Table, AI Forecast.
*   **Current**: `DashboardLayout` exists, but `DashboardPlaceholder` is empty/generic.
*   **Action**: Build the "Care Operations" dashboard widgets.

### 4.2 Referral Intake
*   **Spec**: 3-Step Wizard (Source -> Clinical -> Review) with AI Paste.
*   **Current**: Missing entirely.
*   **Action**: Create `pages/Referrals/CreateReferral.jsx` using Wizard pattern.

### 4.3 Transition Reviews (TNP)
*   **Spec**: List with Acuity Scores, Detail view with Tabs.
*   **Current**: `pages/Tnp` folder exists but likely empty or basic.
*   **Action**: Implement the "Acuity Prioritized" list view.

### 4.4 Analytics
*   **Spec**: Compliance Dashboard with Charts.
*   **Current**: Missing.
*   **Action**: Create `pages/Analytics/ComplianceDashboard.jsx`.

---

## 5. Summary
The current React application is a functional skeleton but lacks the **visual polish** and **domain-specific workflows** defined in the wireframes. The biggest hurdle is the **Theme Mismatch** (Dark vs Light) which must be addressed first to align with the "Connected Capacity" brand.
