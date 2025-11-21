# Connected Capacity V2: Architectural Review & Implementation Plan

This document outlines the findings from a deep-dive architectural review of the Connected Capacity V2 codebase and presents a comprehensive remediation plan.

## SECTION 1 — System-wide Architecture Review

### 1. Architecture Conflicts (The "Split-Brain" Problem)
The most critical issue is a fundamental conflict between two competing architectures:
*   **Architecture A (React SPA):** `routes/web.php` defines a catch-all `/{any?}` route that serves `view('app')`, which mounts a React root (`resources/js/app.jsx`). This indicates an intention to build a Single Page Application.
*   **Architecture B (Server-Side Blade):** `routes/cc2.php` and `app/Http/Controllers/CC2` define server-side routes that return Blade templates (e.g., `view('cc2.organizations.profile')`).
*   **The Conflict:** The `RouteServiceProvider` loads `web.php` (with its aggressive wildcard) alongside `cc2.php`. Depending on the exact load order and route priority, the SPA catch-all is likely **shadowing** the CC2 Blade routes, or vice versa. You essentially have two distinct applications fighting for the same URL space.

**Root Cause:** Incremental development where new features were added in a new paradigm (React) without fully deprecating or integrating the old one (Blade/Legacy). The aggressive `/{any?}` wildcard in `web.php` is the primary culprit for routing conflicts.

**Downstream Impact:**
*   **Inconsistent User Experience:** Users navigate between different UI styles and paradigms.
*   **Maintenance Burden:** Developers need to understand and maintain two distinct frontend rendering approaches and their associated backend logic.
*   **Routing Ambiguity:** Debugging route-related issues becomes complex due as the order of route registration dictates accessibility.
*   **Fragmented Feature Development:** New features might inadvertently use the wrong architectural pattern or become intertwined with legacy code.

### 2. Top Structural Risks
*   **Legacy "Fat" Controllers:** Controllers in `app/Http/Controllers/Legacy` (e.g., `DashboardController`, `PatientsController`) perform heavy database aggregation, complex conditional logic, and direct view rendering.
    *   **Root Cause:** Business logic is tightly coupled with HTTP request handling and presentation logic.
    *   **Downstream Impact:** Difficult to test, reuse, and maintain. Any change to business rules requires modifying the controller, violating the Single Responsibility Principle (SRP).
*   **Incomplete Domain Boundaries:** The `Patient` model currently includes fields like `retirement_home_id` (V1) alongside `primary_coordinator_id` and `risk_flags` (V2). The crucial "Transition Needs Profile" (TNP) concept from Spec 2.1 is not modeled as a distinct entity; rather, data like `triage_summary` and `risk_flags` are JSON-casted attributes on the `Patient` model itself.
    *   **Root Cause:** Evolution of business requirements without a corresponding refactoring of the core domain models. Over-reliance on JSON columns instead of proper relationships for structured data.
    *   **Downstream Impact:** `Patient` model becomes overly complex. Business logic related to TNP cannot be encapsulated. Queries for specific TNP criteria are inefficient.
*   **Role/Permission Ambiguity:** Authorization relies on direct string comparisons for user roles (`$user->role == 'hospital'`) in controllers and middleware.
    *   **Root Cause:** Lack of a centralized, explicit mechanism for defining and managing user roles and permissions.
    *   **Downstream Impact:** Error-prone role checks. Difficult to refactor or add new roles. Security vulnerabilities if role strings are mistyped or inconsistent.
*   **Livewire Components:** No Livewire components were immediately identified in `app/Http/Livewire`, implying it's either not used, or components are very limited. If Livewire *is* used but not following SRP, it could lead to complex, stateful components. (No specific Livewire SRP issues identified without deeper component analysis).
*   **Unsafe Migrations:** No specific non-idempotent or unsafe migrations were identified during this review, but a thorough review of the `database/migrations` directory is warranted during the implementation phase.

### 3. Data Model Inconsistencies
*   **`Patient` Model Overlap:** The `Patient` model holds a mix of demographic data and clinical assessment data (`triage_summary`, `risk_flags`).
    *   **Root Cause:** `Patient` is serving as both the core demographic record and the `TransitionNeedsProfile`.
    *   **Downstream Impact:** Leads to a "fat" patient record, making it harder to query specific clinical aspects or evolve the TNP independently.
*   **`TriageResult` vs. `TransitionNeedsProfile`:** The codebase has a `TriageResult` model, but Spec 2.1 explicitly uses "Transition Needs Profile (TNP)". These seem to be synonymous but with different terminology and possibly expanded V2.1 data points.
    *   **Root Cause:** Terminology shift in the business requirements not yet reflected in the codebase.
    *   **Downstream Impact:** Confusing for new developers, mismatch with documentation, potential for misinterpretation of data.
*   **Referral & Service Provider Integration:** The `Referral` model currently links to `ServiceType` and `ServiceProviderOrganization`. The `ServiceProviderOrganization` model has `regions` and `capabilities` as JSON-casted arrays.
    *   **Root Cause:** Business rules for matching referrals to SPO/SSPOs based on regions/capabilities are likely implemented in application logic, not directly enforced by the database.
    *   **Downstream Impact:** Difficult to query or validate region/capability matches at the database level. Potential for data inconsistencies if application logic is bypassed.

### 4. Frontend Layering Issues
*   **Legacy Blade Layouts:** Many directories under `resources/views` (e.g., `hospitals`, `retirement_homes`, `bookings`) likely contain full Blade templates and layouts that do not conform to the new CC2 design system. The `app.blade.php` uses `mix('css/app.css')` and `mix('js/app.js')`, indicating a reliance on Laravel Mix.
    *   **Root Cause:** Historical development in Blade, followed by a shift to React for new features.
    *   **Downstream Impact:** Inconsistent UI/UX, increased asset build complexity (managing both Mix and eventually Vite), and technical debt.
*   **CC2 Design Tokens:** The provided UI/UX wireframes and notes highlight specific design tokens (colors, spacing, typography) and a component philosophy (clean, left-aligned, teal/indigo palette). These are not uniformly applied across the entire application, as evidenced by the existence of legacy Blade views.
    *   **Root Cause:** Lack of a centralized design system implemented in code that can be universally applied.
    *   **Downstream Impact:** "Two apps in one" look and feel, hindering brand consistency and user adoption of new V2.1 features.

---

## SECTION 2 — UI/UX Unification & Legacy Cleanup Strategy

### 1. Identification of Legacy UI Pages
Based on the `resources/views` directory and the routing analysis, the following pages are likely using legacy UI and will need to be rewritten in React:

*   **`patients/`**: `read.blade.php`, `create.blade.php`, `edit.blade.php`, `patient-assessment-detail-view.blade.php`, `assessment-form.blade.php`, `confirm-patient.blade.php`, `placed-patients.blade.php`
    *   *Note:* The `CC2/Intake` controllers (`ReferralController`, `TransitionReviewController`) might also render Blade templates, but they should be API-driven based on the wireframes.
*   **`hospitals/`**: `dashboard.blade.php`, `create.blade.php`, `edit.blade.php`
*   **`retirement_homes/`**: `dashboard.blade.php`, `create.blade.php`, `edit.blade.php`
*   **`bookings/`**: `index.blade.php`, `show.blade.php` (and related CRUD views)
*   **`profiles/`**: `admin.blade.php`, `hospital.blade.php`, `retirement_home.blade.php`, `change_password.blade.php`
*   **Other general V1 views:** `dashboard/dashboard.blade.php`, `login.blade.php`
*   **Blade Layouts:** `layouts/app.blade.php` (if used by legacy views), `layouts/auth.blade.php`, `layouts/guest.blade.php`. These are likely base layouts for V1.

### 2. Uniform Global Design System Application
The goal is to achieve a "one app, one UI system" look and feel, adhering to the CC2 design principles (clean, left-aligned, generous whitespace, teal/indigo palette).

**Strategy:**
*   **Foundational Styling:**
    *   **Tailwind CSS:** Fully leverage Tailwind CSS for all styling, including custom colors from the CC2 palette (teal, indigo, etc.) defined in `tailwind.config.js`. Remove all other CSS frameworks.
*   **Component Library (React):**
    *   **Atomic Design Principles:** Develop a comprehensive React component library using atomic design principles (atoms, molecules, organisms, templates, pages).
    *   **UI Primitives (Atoms):** Define fundamental UI elements (buttons, inputs, checkboxes, typography components, icons) that embody the CC2 design tokens. Examples: `Button.jsx`, `Input.jsx`, `Checkbox.jsx`, `Heading.jsx`, `Text.jsx`.
    *   **Layout Components:** Create reusable React components for common layout patterns (e.g., `PageHeader.jsx`, `SidebarLayout.jsx`, `Card.jsx`, `Table.jsx`) to enforce consistent spacing, shadows, and borders.
*   **Global Layout (React Root):** The `App.jsx` in `resources/js` will render a single, global `Layout` component that encapsulates:
    *   **Top Navigation:** (e.g., `GlobalNavBar.jsx`) dynamically displaying links based on current user role/permissions.
    *   **Sidebar Navigation:** (if applicable for some roles, e.g., SPO Admin).
    *   **Main Content Area:** Where all other React "page" components are rendered.
    *   **Global Notifications/Modals.**

### 3. Migration Path
**Principle:** Iterative, page-by-page conversion, starting with the most critical V2.1 workflows.

1.  **Frontend Build System:** Complete the migration from Laravel Mix to **Vite** (Phase 1). This is critical for modern React development.
2.  **API Endpoints:** For each legacy page to be rewritten:
    *   Identify data requirements.
    *   Create dedicated V2 API endpoints (`routes/api_v2.php`) that expose data via Laravel API Resources.
    *   Ensure API routes are protected by `auth:sanctum` middleware.
3.  **React Component Development:**
    *   Develop React components for each converted page, consuming the new V2 APIs.
    *   Integrate these components into the global React SPA (`resources/js/app.jsx`) using React Router (or equivalent).
4.  **Retire Legacy Views:** Once a legacy Blade view (`resources/views/patients/read.blade.php`) is fully replaced by its React equivalent, move the Blade file to a `resources/views/deprecated` folder. Ensure no routes point to it.
5.  **Consolidate Templates:** Eliminate duplicated HTML structures by ensuring all new React components use the shared UI primitives and layout components.
6.  **Progressive Enhancement/Degradation:** For very complex legacy flows, consider a temporary approach where a React component *embeds* a mini-Blade view for a specific legacy widget, but this should be short-lived.

### 4. Detection of “Two-App-in-One” Symptoms
*   **Route Groups:** `RouteServiceProvider`'s loading of `web.php` (with `/{any?}` wildcard) and `cc2.php` (Blade-based) creates a direct conflict. The `web.php` wildcard must be the *last* route registered to avoid shadowing.
*   **Conflicting Navigation:** Legacy navigation (if any is rendered via Blade) will directly conflict with the React global navigation.
*   **Inconsistent HTML Structure:** The Blade templates use older HTML structures and likely different base elements compared to the new React/Tailwind components.
*   **Layout Inheritance Conflicts:** Legacy Blade layouts (`resources/views/layouts/*`) will conflict with the single React global layout.

**Cleanup Plan:**
*   **Strict Route Ordering:** Ensure the `routes/spa.php` (containing `/{any?}`) is registered **last** in `RouteServiceProvider`.
*   **Isolate Legacy Routes:** Move all V1-specific routes (those serving `resources/views/legacy/*` views) into a separate `routes/legacy.php` file, protected by specific middleware (e.g., `legacy.auth`).
*   **Deprecate Old Layouts:** Rename `resources/views/layouts` to `resources/views/legacy_layouts` and systematically remove references as pages are migrated.
*   **Asset Management:** Remove all `mix()` calls from `app.blade.php` once Vite is fully integrated. Remove `webpack.mix.js`.

---

## SECTION 3 — Code-level Refactor Recommendations

### 1. Top 5 Functions/Classes/Components Requiring Refactoring

**A. `App\Http\Controllers\Legacy\DashboardController` - `index()` Method**
*   **Issue:** **Too complex, violates SRP.** Acts as a router based on user roles and then calls specialized methods that perform heavy database aggregation and view rendering. Contains multiple `try-catch` blocks.
*   **Impact:** Difficult to test, maintain, and extend. Any new role or dashboard type requires modifying this core method.
*   **Rewritten Version (Concept - Controller as Orchestrator):**
    ```php
    // ... (Code snippet in main response) ...
    ```
    *   **Reasoning:** Delegates the responsibility of selecting the correct dashboard logic and fetching data to a dedicated `DashboardFactory` and specific `DashboardService` implementations.

**B. `App\Models\Patient` - Data Attributes (`triage_summary`, `risk_flags`)**
*   **Issue:** **Bloated model, poor domain boundary.** `triage_summary` and `risk_flags` are stored directly on the `Patient` model, conflating identity with clinical assessment.
*   **Impact:** `Patient` model becomes complex and hard to query for specific clinical data points.
*   **Rewritten Version (Concept - Extract to `TransitionNeedsProfile` Model):**
    ```php
    // ... (Code snippet in main response) ...
    ```
    *   **Reasoning:** Establishes a clear domain boundary, aligning with Spec 2.1 terminology and allowing for structured storage and querying of clinical flags.

**C. `App\Http\Controllers\Legacy\PatientsController` - `index()` Method**
*   **Issue:** **Mixes data retrieval, business logic, and presentation logic.** Performs N+1 queries and manually constructs HTML in the controller.
*   **Impact:** Inefficient, difficult to read, violates SRP.
*   **Rewritten Version (Concept - API Resource & Service/Repository):**
    ```php
    // ... (Code snippet in main response) ...
    ```
    *   **Reasoning:** Separates concerns into Repository (data), Controller (orchestration), and API Resource (presentation), improving efficiency and testability.

**D. `App\Http\Controllers\CC2\Organizations\ProfileController` - `show()` Method**
*   **Issue:** **Returns a Blade View in an intended SPA architecture.**
*   **Impact:** Inconsistent frontend architecture, hindering full SPA adoption.
*   **Rewritten Version (Concept - API Endpoint with Resource):**
    ```php
    // ... (Code snippet in main response) ...
    ```
    *   **Reasoning:** Aligns with the API-first strategy, returning structured JSON for the React frontend.

**E. `User` Model - Role Management**
*   **Issue:** **Magic strings for roles (`$user->role == 'admin'`) and lack of helper methods.**
*   **Impact:** Prone to typos, hard to refactor, limits expressiveness.
*   **Rewritten Version (Concept - Enums/Constants & Helper Methods):**
    ```php
    // ... (Code snippet in main response) ...
    ```
    *   **Reasoning:** Improves readability, maintainability, and reduces errors by using defined constants and helper methods.

### 2. Configuration & Secret Detection
*   **Hardcoded Values (Identified):** Role strings, redirect paths, default image paths, status strings, capability options.
*   **Moves:**
    *   `.env`: `GEMINI_API_KEY`, `DEFAULT_PATIENT_IMAGE_URL`, `VITE_API_BASE_URL`.
    *   `config/connected.php`: Feature flags, role lists, organization types, capability definitions.
    *   `config/services.php`: External API keys.
*   **Plain-Text Secrets:** None immediately found (good).
*   **Standard Environment Strategy:** Local, Dusk, CI, Production (as detailed in main response).

### 3. Dependency Review
*   **Composer:**
    *   `akaunting/laravel-apexcharts`: **Flagged (Low Value).** Replace with React charting library.
*   **NPM:**
    *   `laravel-mix`: **Flagged (Deprecated).** Replace with **Vite**.
    *   `react` / `react-dom` (^19.2.0): **Caution.** Bleeding-edge versions; ensure compatibility.
    *   `axios`, `lodash`, `tailwindcss`: Good choices.

### 4. Automated Quality Gates
*   **.php-cs-fixer.dist.php:** Standardize PHP style (PSR-12).
*   **.editorconfig:** Ensure IDE consistency.
*   **phpstan.neon.dist:** Static analysis (level 5 start).
*   **.styleci.yml:** Optional CI integration.

---

## SECTION 4 — Full Implementation Plan (Phased Roadmap)

This roadmap transforms the application from a hybrid Blade/React mess into a clean API-driven Orchestration Engine.

### Phase 0: Setup & Infrastructure (Weeks 1-2)
**Goal:** Establish a solid, modern technical foundation.
*   Migrate to **Vite**.
*   Standardize code style.
*   Centralize user roles.
*   Consolidate configuration.
*   Clean up routing conflicts.

### Phase 1: V2.1 Core API & Referral Intake (Weeks 3-4)
**Goal:** Establish foundational APIs and React components for Referral Intake.
*   Refactor `Patient` model (add `TransitionNeedsProfile`).
*   Implement V2.1 Referral APIs.
*   Develop React `Referral Dashboard`.

### Phase 2: Transition Review (TNP) & AI Integration (Weeks 5-6)
**Goal:** Implement TNP builder with AI assistance.
*   Implement TNP backend logic.
*   Integrate `GeminiService` for summarization.
*   Develop React `TransitionReviewDetail` page.

### Phase 3: Care Operations & Mobile Field App (Weeks 7-8)
**Goal:** Develop core care orchestration and mobile features.
*   Implement Care Assignment and SSPO backend.
*   Develop Mobile Field App API.
*   Build React `Care Dashboard` (SPO).

### Phase 4: Legacy Cleanup & Full System Integration (Week 9+)
**Goal:** Retire V1, unify system, ensure end-to-end functionality.
*   Remove V1 Blade UI.
*   Consolidate navigation.
*   Finalize role-based access.
*   Implement remaining V2.1 features.
*   Comprehensive testing.

### Final Deliverable
You have the architecture diagnosis, the clean-up strategy, the refactoring targets, and the step-by-step execution plan. This is a "green light" to begin the Phase 1 implementation.
