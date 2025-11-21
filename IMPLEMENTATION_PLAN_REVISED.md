# Connected Capacity V2.1 – Laravel 11 + Unified SPA Implementation Plan (Revised)

## 0. Non-Negotiable Requirements (Read First)

These are **hard constraints**. Any implementation work that violates these is considered incorrect:

1.  **Single Front-End Application (Reinforced)**
    *   The app **MUST** present as one cohesive React SPA. All user interactions and UI rendering happen within React.
    *   No part of the application will navigate to a Blade-rendered UI after the SPA has loaded, except for designated *temporary* authentication or error pages.

2.  **Strict No Split-Brain UI Loading (Aggressive)**
    *   We will **aggressively eliminate** any scenario where legacy Blade UI components or full pages are rendered or referenced within the authenticated user flow.
    *   Once a feature is migrated to React, its Blade counterpart (view, route, controller action returning view) **MUST be immediately disabled or removed**.

3.  **All User-Facing Screens in React (Comprehensive)**
    *   All user-facing interactive UI flows will be React components. This includes:
        *   **All** dashboards, lists, detail views, forms, wizards.
        *   Dedicated pages for Intake, TNP, Care Planning, Assignments, RPM, Notes, Org Settings, Metrics, User Profile.
    *   Blade's role is strictly limited to: SPA shell (`app.blade.php`), authentication routes (Login, Register, Forgot Password, Reset Password), and designated error/exception pages.
    *   **CRITICAL:** Blade **MUST NOT** be used for rendering any application-specific content, partials, or UI components for authenticated users.

4.  **Unified Design System**
    *   The front-end uses **Tailwind CSS + Headless UI (or simple, consistent custom components)**.
    *   Legacy Bootstrap, jQuery plugins, and ad hoc CSS must be systematically removed or isolated and then deleted.
    *   The UX patterns defined in the wireframes/notes (e.g., for TNP, dashboards) must be **generalized and applied to all pages in the app**, not just those example screens.

5.  **Backend on Laravel 11**
    *   The **target framework** is **Laravel 11** on **PHP 8.2+**.
    *   Laravel 11’s:
        *   Native Vite integration
        *   Modern routing & middleware structure
        *   Refined queue, cache, and HTTP layers
        must be used as the base for the new architecture.

6.  **Backend-as-API**
    *   Laravel 11 remains the backend and system of record:
        *   Auth & authorization (policies/guards)
        *   Domain models & services (Patients, Bundles, Providers, TNP, CareOps)
        *   Queues, jobs, notifications, integrations
    *   The API and SPA must be versioned, documented, and testable.

7.  **Incremental but Safe Migration (Expanded)**
    *   The app must remain deployable and *functionally usable for existing critical paths* as we migrate.
    *   This implies a robust **regression test suite** for V1 functionality and a parallel **feature flag strategy** to gradually enable React components while disabling Blade equivalents.
    *   **Data migration** (`triage_summary` to `TNP`) must occur *after* the Laravel 11 upgrade and *before* new React components rely on the new structure, with a clear rollback plan.

---

## 1. Current-State Architectural Assessment (Condensed)

*   **Framework:** Laravel **8.x** (current), legacy structure.
*   **PHP:** 8.0/8.1 (assumed).
*   **Front-End:**
    *   Legacy Blade views with Bootstrap + custom CSS.
    *   React + Vite components with Tailwind.
    *   Some React screens rendered inside Blade pages → hydration issues, double rendering, CSS conflicts.
*   **Routing:**
    *   `routes/web.php` mixes:
        *   Blade-rendered pages.
        *   SPA entrypoints.
        *   React “drop-ins” inside Blade containers.
*   **Domain:**
    *   Historical “placement” logic (hospitals → retirement homes).
    *   Emerging V2.1 “care orchestration” logic (bundled home/community care).
    *   Responsibilities blurred across controllers and Eloquent models.

Primary smells:
*   **Split-brain UI** (Blade vs React).
*   **Aging core** (Laravel 8) with long-term maintenance risk.

---

## 2. Target Architecture (Laravel 11 + SPA)

### 2.1 High-Level

*   **Backend:** Laravel **11** monolith, API-first mindset.
*   **Front-End:** Single React SPA served by:
    *   `resources/views/app.blade.php` → `<div id="root"></div>` → React mounts.
*   **Routing:**
    *   Laravel `routes/web.php`:
        *   SPA catch-all route (e.g., `/app`).
        *   Auth routes (until SPA-driven auth implemented).
        *   Minimal non-auth public routes if needed.
    *   `routes/api.php`:
        *   Versioned API, including mobile/field app endpoints.
*   **Domain Modules (Namespaced):**
    *   `Domain/Patients`
    *   `Domain/Bundles`
    *   `Domain/Providers`
    *   `Domain/CareOps`
    *   `Domain/Tnp` (Transition & Navigation Planning)
    *   `Domain/LegacyPlacement` (temporary, isolated, removable)

Each module exposes:
*   Eloquent models (or domain models + mappers).
*   Services.
*   Policies.
*   Thin controllers.
*   Tests.

---

## 3. Migration Principles

1.  **Upgrade the Foundation Before Building**
    *   Laravel 11 upgrade comes early, so that:
        *   New SPA work is done on a supported, stable framework.
        *   Vite and SPA tooling align with Laravel 11’s defaults.

2.  **Inventory Before Editing**
    *   Before any large deletions, create inventories of:
        *   Blade views and their routes.
        *   Front-end entrypoints and asset pipelines.
        *   Domain/business-critical flows.

3.  **One Route, One Owner**
    *   For any given user-facing use case (e.g., patient listing, care dashboard, TNP review), there must be **exactly one primary UI**.
    *   Once React takes over the use case, the Blade equivalent is marked `LEGACY_BLADE` and then removed in a later phase.

4.  **Feature Flags & Phased Cutovers**
    *   Use config/feature toggles and/or environment flags to:
        *   Gradually direct roles or flows into the SPA.
        *   Maintain fallbacks while migrating, then permanently switch and remove fallbacks.

5.  **Shared Layout & Components**
    *   The SPA must use a **single layout system** and a **shared UI component library**.
    *   All pages, including those not present in original wireframes, must consume these patterns.

6.  **Strict Deletion Passes**
    *   After each migration milestone, delete:
        *   Redundant Blade views.
        *   Associated routes.
        *   Legacy CSS/JS assets.
    *   Don’t leave “temporary” duplication in place.

---

## 4. Phase Plan (Including Laravel 11 Upgrade)

### Phase 0 – Preflight Assessment & Safety Net (Week 0–1)

**Goal:** Understand the current Laravel 8 system and establish safety nets for the upgrade and migration.

**Tasks:**

1.  **Confirm Core Environment:**
    *   Verify current Laravel version (`php artisan --version`) and PHP version (`php -v`).
    *   Document `composer.json` requirements and `package.json` dependencies.
    *   Identify current `node`, `npm`, `yarn` versions.

2.  **Blade View & Route Inventory (Automated + Manual)**
    *   **Automated Scan:** Write a script or use a static analysis tool to:
        *   List all `.blade.php` files under `resources/views/`.
        *   For each view, attempt to find associated routes in `routes/` that directly return that view.
        *   Identify controllers returning `view(...)` calls.
    *   **Categorization:** Manually categorize each identified Blade view/route:
        *   `V1_CRITICAL`: Must be replaced ASAP (e.g., patient lists, dashboards).
        *   `V1_SUPPORT`: Needs conversion, but not high priority (e.g., static info pages, minor forms).
        *   `V1_AUTH`: Authentication pages (login, register).
        *   `V1_ADMIN_ONLY`: Legacy admin functionality.
        *   `V2_BLADE_COMPONENT`: Existing Blade views specifically designed for CC2 that *still return views* (e.g., `cc2.organizations.profile`). These need to be converted to API endpoints.
    *   **Annotate Code:** Add `// LEGACY_BLADE_VIEW: <Purpose>` comments to associated controllers/routes for easier tracking.

3.  **Frontend Asset Inventory**
    *   Identify all `mix()` calls in Blade files (`resources/views`).
    *   Document entry points (`resources/js/app.js`, `resources/css/app.css`).
    *   Note any jQuery, Bootstrap, or other legacy frontend library usages.

4.  **Auth & Authorization Audit**
    *   Document the entire authentication setup: guards, providers, user model, Sanctum/Passport configuration.
    *   Identify all `Auth::user()->role` checks in controllers and middleware.
    *   List existing Policies (`app/Policies`).

5.  **Test Baseline & Regression Suite Development**
    *   Run existing PHPUnit tests and note passing/failing status.
    *   **CRITICAL:** Identify and document **critical user journeys** (e.g., hospital user logs in, views patient list, creates patient) in V1. Develop a **minimal, high-level Dusk/Playwright regression test suite** for these V1 journeys. This suite must pass after the Laravel 11 upgrade and continue to pass until V1 functionality is fully replaced by React. This is non-negotiable for "Incremental but Safe Migration."

**Definition of Done (Phase 0):**
*   Comprehensive inventories of Blade views, routes, frontend assets, and auth configuration.
*   **Minimal Dusk/Playwright regression suite for V1 critical paths is written and passing.**
*   Baseline PHPUnit test status is documented.
*   All current `composer.json` and `package.json` dependencies are documented.

---

### Phase 1 – Upgrade from Laravel 8 → 11 & PHP 8.2+ (Week 1–2)

**Goal:** Upgrade the backend to **Laravel 11** and PHP 8.2+, ensuring functional equivalence.

**Tasks:**

1.  **PHP Version Upgrade:**
    *   Update `composer.json` to require `"php": "^8.2"`.
    *   Run `composer update --no-dev`. This will force PHP to update if needed.
    *   Resolve any immediate PHP 8.1/8.2 deprecation warnings or errors.
    *   **CRITICAL:** Ensure the development environment runs PHP 8.2+.

2.  **Composer Dependency Update:**
    *   Update `composer.json` with Laravel 11 compatible versions:
        *   `"laravel/framework": "^11.0"`
        *   `"nunomaduro/collision": "^8.0"`
        *   `"phpunit/phpunit": "^11.0"`
        *   `"laravel/sanctum": "^4.0"` (if used)
        *   **Review all other packages:** Check for Laravel 11 compatibility. Prioritize updating those with explicit Laravel 11 support. Comment out any that cause immediate conflicts for later investigation.
    *   Run `composer update`. Resolve dependency conflicts.

3.  **Laravel 11 Project Structure & Configuration Alignment:**
    *   **New `bootstrap/app.php`:** Introduce the new Laravel 11 `bootstrap/app.php` file and adapt existing service provider registrations and boot logic. This is a significant change.
    *   **Console Kernel:** Update `app/Console/Kernel.php` to the new Laravel 11 structure (e.g., removing `load` methods for commands).
    *   **HTTP Kernel:** Review `app/Http/Kernel.php` for Laravel 11 middleware groups and aliases.
    *   **Exception Handler:** Adapt `app/Exceptions/Handler.php` if custom error reporting was in place.
    *   **Configuration Files:** Review `config/app.php`, `config/auth.php`, `config/database.php`, `config/logging.php` for Laravel 11 changes. Migrate any custom configurations.
    *   **Remove Deprecations:** Use Laravel Shift (if license allows) or manual review for deprecated Laravel 8 features (e.g., `Str::afterLast` might be `Str::afterLast($str, '/')`).
    *   **New `storage` structure:** `storage/app/public` symlink needs to be adjusted.

4.  **Auth & Sanctum/Passport Re-validation:**
    *   Verify `config/auth.php` and `config/sanctum.php` (if present) are aligned with Laravel 11/Sanctum 4.x.
    *   Ensure `Auth` facade and middleware continue to function correctly.
    *   Test basic login/logout flows against existing database.

5.  **Tests & Manual Smoke Testing:**
    *   Run the **Phase 0 V1 regression test suite (Dusk/Playwright)**. This suite MUST pass. Address any failures.
    *   Run all PHPUnit tests. Update test code for Laravel 11 changes (e.g., `refreshDatabase` trait, HTTP test assertions).
    *   Perform a thorough manual smoke test of all critical V1 features identified in Phase 0.

**Definition of Done (Phase 1):**
*   Application runs on **Laravel 11.x** and **PHP 8.2+**.
*   All `composer.json` dependencies are compatible and updated.
*   Project structure (e.g., `bootstrap/app.php`) aligns with Laravel 11 conventions.
*   **Phase 0 V1 regression test suite passes without modification.**
*   All existing PHPUnit tests pass.
*   Critical V1 flows are manually smoke-tested and confirmed functional.
*   No major framework deprecation warnings or errors are present in logs.

---

### Phase 2 – SPA Bootstrap & Vite Consolidation (Week 2–3)

**Goal:** Standardize on Laravel 11’s Vite integration, using a single React entrypoint.

**Tasks:**

1.  **Vite as the Sole Build Tool (Aggressive Consolidation):**
    *   **Remove Laravel Mix:** Delete `webpack.mix.js` and remove `laravel-mix` from `package.json`.
    *   **Vite Configuration:** Create/update `vite.config.js` to define:
        *   Laravel plugin (`laravel({ input: 'resources/js/app.jsx', refresh: true })`).
        *   React plugin (`@vitejs/plugin-react`).
        *   Tailwind CSS integration.
    *   **NPM Dependencies:** Add `@vitejs/plugin-react`, `postcss`, `autoprefixer`. Update `tailwindcss`.
    *   **`package.json` Scripts:** Ensure `dev`, `build`, `preview` scripts use Vite commands.
    *   **Asset Cleanup:** Remove all generated `public/css/app.css`, `public/js/app.js` (from Mix).

2.  **SPA Blade Bootstrap (`app.blade.php`):**
    *   Confirm `resources/views/app.blade.php` is the **only** SPA bootstrap.
    *   It must:
        *   Use the `@vite` Blade directive to load the React bundle.
        *   Include essential meta tags (CSRF token, base URL, potentially user context).
        *   Contain a single root HTML element (e.g., `<div id="root"></div>`) for React to mount into.
        *   **CRITICAL:** Remove all existing `mix()` calls.

3.  **Web Routes Guardrails & SPA Entry:**
    *   **`routes/web.php`:**
        *   Ensure only Laravel 11 standard auth routes remain (Login, Register, Password Reset).
        *   Define the SPA catch-all route **at the very end** of `routes/web.php`:
            ```php
            // ALL AUTHENTICATED USERS will land in the SPA
            Route::middleware(['auth'])->group(function () {
                // This route will serve the SPA for all non-matching authenticated URLs
                Route::get('/{any?}', fn () => view('app'))->where('any', '.*');
            });

            // GUESTS who hit non-auth routes will be redirected to login
            // OR serve a public SPA shell (e.g., marketing content)
            Route::get('/{any?}', fn () => redirect()->route('login'))->where('any', '.*');
            ```
        *   **CRITICAL:** If any Blade routes still return full pages for authenticated users, they MUST be **disabled/commented out/redirected** to the SPA entry point (`/`).
        *   Mark all legacy Blade routes as `LEGACY_DISABLED` for later removal.

4.  **Initial React Entry & Basic Auth Context:**
    *   `resources/js/app.jsx` (or `app.tsx`):
        *   Set up React Router (v6+).
        *   Initialize a basic `AuthContext` by making an API call to `/api/user` (or `/api/me`) to retrieve the authenticated user's details and roles.
        *   Render a placeholder React component (e.g., `<DashboardPlaceholder />`).

5.  **Clean up `resources/js`:** Ensure only V2.1 React code and components reside here. Remove any outdated React components or structures from previous iterations.

**Definition of Done (Phase 2):**
*   Laravel 11 app builds and runs with **Vite as the sole asset pipeline**.
*   **`resources/views/app.blade.php` is the single SPA bootstrap**, using `@vite` directive exclusively.
*   **No `mix()` calls** or legacy frontend assets are referenced anywhere in `resources/views`.
*   `/` (or equivalent) renders a basic React shell.
*   **Authenticated users are routed exclusively to the React SPA.**
*   `AuthContext` successfully retrieves user details from `/api/user`.
*   **All legacy Blade routes for authenticated users are disabled/redirected.**
*   Phase 0 V1 regression tests pass (now primarily testing API routes and the SPA's ability to render, not old Blade).

---

### Phase 3 – SPA Shell, Navigation & Role-Aware Layout (Week 3–5)

**Goal:** Build the unified SPA shell, global navigation, and replace top-level legacy dashboards with React equivalents.

**Tasks:**

1.  **Backend: Refine Roles & Permissions (Laravel 11 Style):**
    *   **User Model Constants:** Ensure `App\Models\User` has all role constants (`ROLE_ADMIN`, `ROLE_SPO_ADMIN`, etc.) as class constants.
    *   **Middleware:** Implement new middleware (e.g., `EnsureUserHasRole` or `EnsureFeatureEnabled`) for API routes if not already present in `cc2` routes.
    *   **Policies:** Begin defining fine-grained Laravel Policies (`App\Policies/*`) for core V2.1 models (e.g., `PatientPolicy`, `ReferralPolicy`, `TransitionNeedsProfilePolicy`). These policies will leverage the `User` model's role constants.
    *   **Centralized Role Config:** Integrate role names and their mapping to display labels into `config/connected.php`.

2.  **Frontend: Global Layout & Navigation (React):**
    *   **`AppLayout.jsx`:** Implement the main React layout component (sidebar + topbar + content area) conforming to CC2 design principles.
    *   **`GlobalNavBar.jsx` / `Sidebar.jsx`:** Build navigation components.
        *   These components will dynamically render links based on the user's roles and permissions (`AuthContext.can(permission)`).
        *   Integrate icons and ensure consistent styling using Tailwind.
    *   **`PageHeader.jsx`:** Create a reusable component for page titles, descriptions, and primary actions, adhering to the CC2 wireframe style.

3.  **Frontend: React Router & Protected Routes:**
    *   Refine `resources/js/app.jsx` to configure React Router (v6+).
    *   Implement **Route Guards** within React Router (e.g., using `Outlet` and conditional rendering) to protect routes based on `AuthContext` (user roles/permissions).
    *   Ensure a `NotFoundPage` (404) is gracefully handled within the SPA.

4.  **Frontend: Core UI Component Library (React + Tailwind):**
    *   Prioritize building out the essential reusable UI primitives (atoms, molecules) based on CC2 design.
        *   `Button.jsx`: Primary, Secondary, Danger, Link styles.
        *   `Input.jsx`, `Select.jsx`, `Checkbox.jsx`: Standard form controls.
        *   `DataTable.jsx`: A generic data table component with pagination, sorting, and filtering capabilities (can be a wrapper around a headless UI library or custom).
        *   `Modal.jsx`, `Spinner.jsx`, `Toast.jsx`: Feedback and interaction components.
        *   `Card.jsx`, `Section.jsx`: Layout and grouping components.
    *   **CRITICAL:** These components MUST be designed to be flexible enough to support pages that have not yet been migrated. They will serve as the foundation for *all* future React development.

5.  **Backend: Replace Legacy Dashboard Controllers with API Endpoints:**
    *   **Legacy Dashboard Deactivation:** Disable/redirect all routes that return legacy Blade dashboards (e.g., from `Legacy\DashboardController`).
    *   **API Endpoints:** Create new API endpoints (e.g., `api/v2/dashboards/admin`, `api/v2/dashboards/spo`) that return data required for the new React dashboards.
    *   **API Resources:** Utilize Laravel API Resources (`App\Http\Resources\V2/*`) to format dashboard data consistently for the frontend.
    *   **Controller Refactor:** Refactor existing dashboard logic (data fetching, aggregation) from `Legacy\DashboardController` into dedicated `App\Services\DashboardService` (or similar) classes, making them backend-agnostic for API consumption.

**Definition of Done (Phase 3):**
*   **Authenticated users exclusively land in the React SPA**, with a unified `AppLayout`.
*   Global React navigation (sidebar/topbar) functions correctly and is **role/permission-aware**.
*   **All legacy Blade dashboards are removed or permanently redirected** to their React SPA equivalents.
*   A foundational React UI component library (buttons, inputs, data table, modals) is built and adheres to CC2 design.
*   **Key V2.1 dashboards (e.g., SPO Care Dashboard, Admin Overview) are implemented in React**, consuming dedicated API endpoints.

---

#### Phase 4 – TNP (Transition Review) & AI Integration (Week 5–7)

**Goal:** Implement the core V2.1 workflow for Transition Needs Profile (TNP) in Laravel 11 domain + React SPA, with Gemini-based summarization.

**Tasks:**

1.  **Domain: TNP in Laravel 11 (Refined & Detailed)**
    *   **Models:**
        *   Create `App\Models\TransitionNeedsProfile.php`.
        *   Add `clinical_flags` (JSON array), `narrative_summary` (text), `status`, `bundle_recommendation_id` fields.
        *   Establish `Patient` `hasOne` `TransitionNeedsProfile` relationship.
        *   Migrate `triage_summary` and `risk_flags` from `Patient` to new `TransitionNeedsProfile` model via a dedicated, idempotent database migration. **This migration must be tested thoroughly and have a clear rollback plan.**
        *   Remove `triage_summary` and `risk_flags` from `Patient` `$fillable` and `$casts`.
    *   **Controllers (`App\Http\Controllers\Api\V2\TnpController.php`):**
        *   `index`, `show`, `store`, `update` endpoints for TNPs.
        *   Ensure thin controllers, delegating logic to services.
    *   **Policies:** Define `TransitionNeedsProfilePolicy` to control access based on `User` roles (SPO Coordinator, Field Staff, etc.).
    *   **Services:** Create `App\Services\TnpService` to encapsulate TNP creation, update, and complex business logic.

2.  **GeminiService Integration (Refined & Queued):**
    *   **`App\Services\GeminiService.php`:** Implement a service to interact with the Gemini API.
        *   Methods: `summarizeClinicalNotes(string $notes)`, `analyzeRisk(array $tnpData)`, `generateBundleRecommendation(array $tnpData)`.
        *   Handle API key retrieval from `config('connected.ai.gemini_api_key')`.
        *   Implement basic retry logic and error handling.
    *   **Queue Integration:** For long-running AI operations (e.g., initial narrative summarization), dispatch Laravel Jobs.
        *   Job dispatches AI call.
        *   Stores result in `TransitionNeedsProfile` (`ai_summary_status`, `ai_summary_text`).
        *   Notifies user (e.g., via Broadcasting or Database Notifications) when complete.
    *   **Frontend Integration:** React components will trigger AI calls via API endpoints that dispatch these jobs and poll for results.

3.  **React Screens for TNP (Detailed):**
    *   **`TnpReviewListPage.jsx`:**
        *   Use `DataTable` component.
        *   Filters: `status`, `SPO`, `patient_name`, `assigned_coordinator`.
        *   Actions: "View TNP", "Create New TNP".
    *   **`TnpReviewDetailPage.jsx`:**
        *   Use `AppLayout` + `PageHeader`.
        *   Structure with tabs (e.g., "Patient Overview", "Clinical Inputs", "AI Insights", "Care Bundle Recommendation").
        *   **Clinical Flags:** Implement interactive checkboxes/toggles (using UI component library).
        *   **Narrative Input:** Rich text editor or textarea for clinical notes. "Generate AI Summary" button triggers `GeminiService`.
        *   **AI Summary Display:** Render AI-generated summary.
        *   **Care Bundle Recommendation:** Dynamically display bundle options based on TNP inputs.

4.  **UX Pattern Propagation (Reinforced):**
    *   Apply the established CC2 UX patterns (Page Header, Card layouts, consistent form fields, primary/secondary action buttons) to *all* new TNP screens.
    *   **CRITICAL:** Document these patterns (e.g., in `resources/js/components/docs/DesignSystem.md`) for future reference.

5.  **Legacy TNP/Transition Views Removal (Aggressive):**
    *   Disable/redirect any Blade views related to `TriageResult` or `AssessmentForm`. Remove their routes.
    *   **Data Migration Completion:** Confirm that the data migration for `triage_summary` and `risk_flags` to `TransitionNeedsProfile` is fully complete and verified.

**Definition of Done (Phase 4):**
*   **`TransitionNeedsProfile` model** is fully functional, supporting V2.1 data structure.
*   **Data migration** from `Patient` attributes to `TransitionNeedsProfile` is complete and verified.
*   **`GeminiService`** is integrated and can perform SBAR summarization.
*   **React TNP review pages** (list and detail) are fully operational, consuming APIs and leveraging AI.
*   **All legacy Triage/Assessment Blade views are eliminated** or redirected.
*   UX patterns established in Phase 3 are consistently applied.

---

#### Phase 5 – Care Operations & Mobile Field App API (Week 7–9)

**Goal:** Implement Care Operations module (assignments, visits, tasks) and mobile/field app APIs, leveraging new V2.1 features.

**Tasks:**

1.  **Domain: CareOps & Assignments (Detailed):**
    *   **Entities:** Create/refine `App\Models\CareAssignment.php`, `Visit.php`, `Task.php`, `Note.php` (for interdisciplinary notes).
    *   **Relationships:** Establish relationships to `Patient`, `TransitionNeedsProfile`, `User` (Field Staff, SPO Coordinator), `ServiceProviderOrganization`.
    *   **Controllers (`App\Http\Controllers\Api\V2\CareOpsController.php`, etc.):**
        *   Endpoints for listing/managing assignments, scheduling visits, updating task status.
        *   Thin controllers, using `App\Services\CareOpsService`.
    *   **Policies:** Define policies for `CareAssignment`, `Visit`, `Task` (e.g., Field Staff can only update their assigned tasks, SPO Admin can assign).

2.  **SPA: Care Dashboards & Worklists (React):**
    *   **`CareDashboardPage.jsx` (SPO/SSPO):**
        *   Implement high-level metrics (Missed Visits, Unfilled Shifts – pulling from `CareOpsService` APIs).
        *   SSPO Partner Performance section (using `DataTable`).
        *   AI Capacity Forecast integration (calls `GeminiService` API endpoint).
    *   **`FieldStaffWorklistPage.jsx`:**
        *   "My Visits Today" list (using reusable list components).
        *   "My Tasks" (checkbox for completion).
        *   Real-time status updates.

3.  **Mobile Field App API (Laravel 11, Versioned):**
    *   **`routes/api_mobile.php` (new file, loaded in `RouteServiceProvider`):**
        *   `Route::prefix('mobile/v1')->middleware(['auth:sanctum', 'role:FIELD_STAFF'])`.
        *   Endpoionts for: `GET /visits/today`, `PATCH /visits/{id}/clock_in`, `PATCH /visits/{id}/clock_out`, `PUT /tasks/{id}/complete`, `POST /notes` (voice-to-text via AI).
        *   **CRITICAL:** API responses MUST be lightweight and optimized for mobile network conditions.
    *   **Controllers (`App\Http\Controllers\Api\Mobile\V1\VisitController.php`, etc.):** Implement specific controllers for mobile endpoints.
    *   **Services:** `App\Services\MobileVisitService`, `App\Services\MobileNoteService`.

4.  **Legacy Placement Isolation (Finalized):**
    *   Review all remaining "Legacy Placement" code. If still required, refactor into `Domain/LegacyPlacement` or `App\Services\LegacyPlacementService`.
    *   **Goal:** Ensure this code can be easily removed in the future without impacting V2.1.
    *   **CRITICAL:** New V2.1 CareOps flows MUST NOT be coupled to legacy placement code.

**Definition of Done (Phase 5):**
*   **Care Operations module** is fully implemented in the backend (models, services, policies, APIs).
*   **SPO Care Dashboard** is functional, displaying real-time metrics and AI forecasts.
*   **Mobile Field App API (v1)** is implemented, authenticated, and tested.
*   **Field Staff Worklist React page** is fully functional, consuming the mobile APIs.
*   Legacy placement logic is clearly isolated.

---

#### Phase 6 – Full Legacy Removal, Cleanup & Hardening (Week 9+)

**Goal:** Fully remove split-brain architecture, delete all remaining Blade UIs, clean assets, and harden the application on Laravel 11.

**Tasks:**

1.  **Legacy Blade Removal (Aggressive Deletion):**
    *   Using the inventory from Phase 0 and `LEGACY_DISABLED` markers:
        *   **Permanently delete all Blade views** (`.blade.php` files) that have been replaced by React SPA pages.
        *   **Permanently delete their associated routes** from `routes/web.php` and `routes/cc2.php`.
        *   Remove any Blade partials or components.
    *   Review `app/Http/Controllers/Legacy` for any unused controllers. Delete if no longer necessary.

2.  **CSS & Asset Cleanup (Thorough):**
    *   Remove all `public/css/` and `public/js/` files not generated by Vite.
    *   Remove Bootstrap, jQuery, and any other legacy CSS/JS libraries from `public/` and `package.json`.
    *   Ensure `tailwind.config.js` is clean and contains only CC2 design system values.
    *   Verify only Vite-built assets are being used by the application.

3.  **Routing Finalization (Clean Slate):**
    *   **`routes/web.php`:** Should contain only:
        *   Laravel's standard authentication routes (login, register, password reset).
        *   The SPA catch-all route (`Route::get('/{any?}', ...)`) as the *very last* route.
        *   Any truly public, non-authenticated routes if necessary (e.g., marketing landing pages).
    *   **`routes/api.php`:** Should contain only clean, versioned (e.g., `v1`, `v2`) API endpoints.
    *   Remove `routes/cc2.php` if all its routes have been replaced by `api/v2` endpoints.

4.  **RBAC & Security Review (Comprehensive):**
    *   Conduct a full audit of all Laravel Policies to ensure they are correctly implemented and cover all V2.1 domain actions.
    *   Verify that React Router guards and frontend role checks correctly enforce permissions received from the backend APIs.
    *   Review Laravel 11's default security headers and ensure they are configured optimally.
    *   Implement robust API rate limiting for all V2.1 API endpoints.

5.  **Testing & QA (Final Validation):**
    *   Execute all PHPUnit, Dusk, Playwright tests. Ensure 100% pass rate.
    *   Perform extensive manual end-to-end testing across all user roles:
        *   Verify that no user journey leads to a Blade UI page.
        *   Confirm that there are no "React-in-Blade" partials or inconsistent layouts.
        *   Check for double CSS loading, hydration errors, or visual glitches.
        *   Verify performance across critical V2.1 flows.

6.  **Documentation Update:** Update `README.md`, `CODE_REVIEW.md` and any internal documentation to reflect the final Laravel 11 + React SPA architecture.

**Definition of Done (Phase 6):**
*   Connected Capacity runs on **Laravel 11** with a **single React SPA** front-end.
*   **No split-brain UI remains anywhere in the codebase or user experience.**
*   All legacy Blade views, routes, and assets are permanently removed.
*   Domain modules and API are clean, versioned, and backed by comprehensive tests.
*   The system is fully hardened, secure, and ready for ongoing feature development on a modern, maintainable foundation.

---

## (4) Execution Backlog

Here is a structured backlog derived from the improved plan, presented as tasks for a coding agent.

### Phase 0: Preflight Assessment & Safety Net

*   **Task: Confirm Current Environment**
    *   Description: Verify current Laravel, PHP, Node, NPM versions.
    *   Code Touchpoints: `composer.json`, `package.json`
    *   Dependencies: None
    *   Acceptance: Verified versions documented.

*   **Task: Generate Blade View & Route Inventory**
    *   Description: Scan for all Blade files, associated routes, and controllers returning views. Categorize them.
    *   Code Touchpoints: `resources/views/**/*.blade.php`, `routes/**/*.php`, `app/Http/Controllers/**/*.php`
    *   Dependencies: None
    *   Acceptance: Detailed inventory markdown file created. Controllers/routes annotated with `// LEGACY_BLADE_VIEW` comments.

*   **Task: Frontend Asset Inventory**
    *   Description: Identify all `mix()` calls and legacy frontend library usages.
    *   Code Touchpoints: `resources/views/**/*.blade.blade.php`, `package.json`
    *   Dependencies: None
    *   Acceptance: Documented list of legacy assets/calls.

*   **Task: Audit Auth & Authorization**
    *   Description: Document current auth setup, role checks, and policies.
    *   Code Touchpoints: `config/auth.php`, `app/Models/User.php`, `app/Http/Middleware/**/*.php`, `app/Policies/**/*.php`
    *   Dependencies: None
    *   Acceptance: Comprehensive audit report created.

*   **Task: Develop V1 Regression Test Suite (Dusk/Playwright)**
    *   Description: Create high-level end-to-end tests for critical existing V1 user journeys.
    *   Code Touchpoints: `tests/Browser/**/*.php` (Dusk) or `tests/e2e/**/*.spec.js` (Playwright)
    *   Dependencies: None
    *   Acceptance: V1 regression test suite exists and passes for identified critical paths.

### Phase 1: Upgrade from Laravel 8 → 11 & PHP 8.2+

*   **Task: Upgrade PHP Version to 8.2+**
    *   Description: Update `composer.json` to require PHP ^8.2. Resolve any immediate PHP version conflicts.
    *   Code Touchpoints: `composer.json`
    *   Dependencies: None
    *   Acceptance: `php -v` in project root shows 8.2+. `composer update` completes without PHP version errors.

*   **Task: Update Composer Dependencies for Laravel 11**
    *   Description: Update `laravel/framework` to ^11.0, `collision` to ^8.0, `phpunit` to ^11.0, `sanctum` to ^4.0. Review and update other packages for Laravel 11 compatibility.
    *   Code Touchpoints: `composer.json`
    *   Dependencies: Task: Upgrade PHP Version
    *   Acceptance: `composer update` completes successfully. All major Laravel packages are at v11 compatible versions.

*   **Task: Align Project Structure with Laravel 11**
    *   Description: Introduce new `bootstrap/app.php`. Update `app/Console/Kernel.php`, `app/Http/Kernel.php`, `app/Exceptions/Handler.php`. Review and migrate `config/` files. Remove Laravel 8 deprecations. Adjust storage symlink.
    *   Code Touchpoints: `bootstrap/app.php`, `app/Console/Kernel.php`, `app/Http/Kernel.php`, `app/Exceptions/Handler.php`, `config/*.php`, `storage/`.
    *   Dependencies: Task: Update Composer Dependencies
    *   Acceptance: `php artisan --version` shows Laravel 11. No framework-level errors.

*   **Task: Re-validate Auth & Sanctum Configuration**
    *   Description: Verify `config/auth.php` and `config/sanctum.php` are compatible. Test login/logout.
    *   Code Touchpoints: `config/auth.php`, `config/sanctum.php`, `app/Http/Controllers/Auth/*.php`
    *   Dependencies: Task: Align Project Structure
    *   Acceptance: Login/Logout works.

*   **Task: Run & Fix Test Suites (V1 Regression & PHPUnit)**
    *   Description: Execute the Phase 0 V1 regression tests and all PHPUnit tests. Resolve failures due to Laravel 11 changes.
    *   Code Touchpoints: `tests/**/*.php`, `phpunit.xml`, `dusk.php`
    *   Dependencies: Task: Re-validate Auth & Sanctum Configuration
    *   Acceptance: All tests pass. V1 critical flows are manually verified.

### Phase 2: SPA Bootstrap & Vite Consolidation

*   **Task: Remove Laravel Mix Configuration**
    *   Description: Delete `webpack.mix.js` and remove `laravel-mix` from `package.json`.
    *   Code Touchpoints: `webpack.mix.js`, `package.json`
    *   Dependencies: Phase 1 Complete
    *   Acceptance: `webpack.mix.js` is gone. `npm install` runs without Mix errors.

*   **Task: Configure Vite as Sole Build Tool**
    *   Description: Create/update `vite.config.js`. Add `@vitejs/plugin-react`, `postcss`, `autoprefixer` to `package.json`. Update `scripts` in `package.json`. Delete old generated asset files.
    *   Code Touchpoints: `vite.config.js`, `package.json`, `public/css/*`, `public/js/*`
    *   Dependencies: Task: Remove Laravel Mix Configuration
    *   Acceptance: `npm run dev` and `npm run build` run successfully using Vite.

*   **Task: Setup Single SPA Blade Bootstrap (`app.blade.php`)**
    *   Description: Ensure `resources/views/app.blade.php` is the only SPA bootstrap. Use `@vite` directive. Include meta tags. Add `<div id="root"></div>`. Remove all `mix()` calls.
    *   Code Touchpoints: `resources/views/app.blade.php`
    *   Dependencies: Task: Configure Vite
    *   Acceptance: `app.blade.php` uses only `@vite`.

*   **Task: Implement Web Routes Guardrails & SPA Catch-all**
    *   Description: Simplify `routes/web.php` to include only Laravel 11 auth routes and the SPA catch-all at the *very end*. Disable/redirect legacy Blade routes. Mark them `LEGACY_DISABLED`.
    *   Code Touchpoints: `routes/web.php`, `routes/api.php`, `app/Http/Middleware/*`
    *   Dependencies: Task: Setup Single SPA Blade Bootstrap
    *   Acceptance: Accessing any non-auth authenticated URL serves `app.blade.php`. Legacy Blade routes are inaccessible.

*   **Task: Implement Initial React Entry & Auth Context**
    *   Description: Set up React Router in `resources/js/app.jsx`. Implement `AuthContext` to fetch user data from `/api/user`. Render a basic placeholder React component. Clean up `resources/js`.
    *   Code Touchpoints: `resources/js/app.jsx`, `resources/js/components/*`, `routes/api.php`
    *   Dependencies: Task: Implement Web Routes Guardrails
    *   Acceptance: React SPA loads in browser. User data is fetched and available in `AuthContext`.

### Phase 3: SPA Shell, Navigation & Role-Aware Layout

*   **Task: Backend: Refine Roles & Permissions**
    *   Description: Add role constants to `User` model. Implement new middleware and start defining fine-grained Laravel Policies for V2.1 models. Update `config/connected.php` with role mappings.
    *   Code Touchpoints: `app/Models/User.php`, `app/Http/Middleware/*`, `app/Policies/*`, `config/connected.php`
    *   Dependencies: Phase 2 Complete
    *   Acceptance: `User` model has role constants. Policies defined for key models. Role config centralized.

*   **Task: Frontend: Global Layout & Navigation**
    *   Description: Implement `AppLayout`, `GlobalNavBar`, `Sidebar`, `PageHeader` React components. Ensure dynamic rendering based on `AuthContext` roles/permissions.
    *   Code Touchpoints: `resources/js/layouts/AppLayout.jsx`, `resources/js/components/Navigation/*.jsx`, `resources/js/components/Layout/*.jsx`
    *   Dependencies: Task: Backend: Refine Roles & Permissions
    *   Acceptance: All authenticated SPA routes display the new global layout with dynamic navigation.

*   **Task: Frontend: React Router & Protected Routes**
    *   Description: Refine React Router configuration in `app.jsx`. Implement client-side Route Guards using `AuthContext`. Add `NotFoundPage`.
    *   Code Touchpoints: `resources/js/app.jsx`, `resources/js/hooks/useAuth.js` (new)
    *   Dependencies: Task: Frontend: Global Layout
    *   Acceptance: Unauthorized users are redirected. 404s handled gracefully.

*   **Task: Frontend: Core UI Component Library**
    *   Description: Build essential reusable UI primitives (buttons, inputs, data table, modals, cards) adhering to CC2 design.
    *   Code Touchpoints: `resources/js/components/UI/*.jsx`
    *   Dependencies: Task: Frontend: Global Layout
    *   Acceptance: A documented, testable library of core UI components exists.

*   **Task: Backend: Replace Legacy Dashboard Controllers with API Endpoints**
    *   Description: Disable/redirect legacy Blade dashboard routes. Create new API endpoints (`api/v2/dashboards/*`) returning data via Laravel API Resources. Refactor dashboard logic into `App\Services\DashboardService`.
    *   Code Touchpoints: `routes/web.php`, `routes/api_v2.php` (new), `app/Http/Controllers/Legacy/DashboardController.php` (modified), `app/Http/Controllers/Api/V2/DashboardController.php` (new), `app/Http/Resources/V2/*.php` (new), `app/Services/DashboardService.php` (new)
    *   Dependencies: Task: Backend: Refine Roles; Task: Frontend: Core UI Component Library (for React Dashboards)
    *   Acceptance: Legacy Blade dashboards are inaccessible. React dashboards consume new API endpoints.

### Phase 4: TNP (Transition Review) & AI Integration

*   **Task: Domain: Create TransitionNeedsProfile Model & Migration**
    *   Description: Create `TransitionNeedsProfile` model with `clinical_flags`, `narrative_summary`, etc. Create database migration to create the table and establish `patient` relationship.
    *   Code Touchpoints: `app/Models/TransitionNeedsProfile.php`, `database/migrations/*_create_transition_needs_profiles_table.php`
    *   Dependencies: Phase 3 Complete
    *   Acceptance: `TransitionNeedsProfile` model and database table exist.

*   **Task: Database Migration: Migrate `triage_summary` & `risk_flags` Data**
    *   Description: Write a dedicated, idempotent database migration to move existing `triage_summary` and `risk_flags` data from `patients` table to the new `transition_needs_profiles` table.
    *   Code Touchpoints: `database/migrations/*_migrate_patient_tnp_data.php` (new)
    *   Dependencies: Task: Create TransitionNeedsProfile Model
    *   Acceptance: Data successfully migrated. `Patient` model attributes removed.

*   **Task: Implement TNP Backend (Controllers, Policies, Services)**
    *   Description: Create `App\Http\Controllers\Api\V2\TnpController.php` (CRUD endpoints). Define `TransitionNeedsProfilePolicy`. Create `App\Services\TnpService`.
    *   Code Touchpoints: `app/Http/Controllers/Api/V2/TnpController.php`, `app/Policies/TransitionNeedsProfilePolicy.php`, `app/Services/TnpService.php`, `routes/api_v2.php`
    *   Dependencies: Task: Database Migration: Migrate `triage_summary`
    *   Acceptance: TNP API endpoints are functional and policies correctly enforce access.

*   **Task: Implement GeminiService (Refined & Queued)**
    *   Description: Create `App\Services\GeminiService.php` with methods for summarization, risk analysis. Implement queue integration using Laravel Jobs for long-running AI calls. Handle config and error handling.
    *   Code Touchpoints: `app/Services/GeminiService.php`, `app/Jobs/ProcessTnpAi.php` (new), `config/connected.php`
    *   Dependencies: Task: Implement TNP Backend
    *   Acceptance: `GeminiService` can make API calls and process results asynchronously via queues.

*   **Task: Develop React TNP Screens (List & Detail)**
    *   Description: Build `TnpReviewListPage.jsx` (using `DataTable`) and `TnpReviewDetailPage.jsx` (using `AppLayout`, `PageHeader`, tabs, interactive flags, narrative input, AI integration).
    *   Code Touchpoints: `resources/js/pages/TnpReviewListPage.jsx`, `resources/js/pages/TnpReviewDetailPage.jsx`, `resources/js/components/Tnp/*.jsx` (new)
    *   Dependencies: Task: Implement GeminiService; Task: Implement TNP Backend
    *   Acceptance: Full TNP workflow in React, with AI summarization.

*   **Task: Propagate TNP UX Patterns & Document**
    *   Description: Apply TNP UX patterns to other relevant new screens. Document patterns in `resources/js/components/docs/DesignSystem.md`.
    *   Code Touchpoints: `resources/js/components/docs/DesignSystem.md`
    *   Dependencies: Task: Develop React TNP Screens
    *   Acceptance: Consistent UX across new V2.1 features.

*   **Task: Remove Legacy TNP/Transition Views & Routes**
    *   Description: Delete all Blade views related to `TriageResult` or `AssessmentForm`. Remove their routes.
    *   Code Touchpoints: `resources/views/patients/assessment-form.blade.php`, `resources/views/patients/patient-assessment-detail-view.blade.php` (and related), `routes/web.php`
    *   Dependencies: Task: Develop React TNP Screens
    *   Acceptance: No Blade views related to TNP are accessible.

### Phase 5: Care Operations & Mobile Field App API

*   **Task: Domain: Implement CareOps & Assignment Entities**
    *   Description: Create/refine models (`CareAssignment`, `Visit`, `Task`, `Note`) and their relationships.
    *   Code Touchpoints: `app/Models/CareAssignment.php`, `Visit.php`, `Task.php`, `Note.php`
    *   Dependencies: Phase 4 Complete
    *   Acceptance: CareOps domain models are established.

*   **Task: Backend: Implement CareOps APIs & Policies**
    *   Description: Create `App\Http\Controllers\Api\V2\CareOpsController.php` (assignments, scheduling). Define policies for these entities. Create `App\Services\CareOpsService`.
    *   Code Touchpoints: `app/Http/Controllers/Api/V2/CareOpsController.php`, `app/Policies/CareAssignmentPolicy.php`, `app/Services/CareOpsService.php`, `routes/api_v2.php`
    *   Dependencies: Task: Implement CareOps Entities
    *   Acceptance: CareOps API endpoints are functional and secured.

*   **Task: Develop React Care Dashboards & Worklists**
    *   Description: Build `CareDashboardPage.jsx` (SPO/SSPO) with metrics, SSPO performance, AI forecast. Implement `FieldStaffWorklistPage.jsx`.
    *   Code Touchpoints: `resources/js/pages/CareDashboardPage.jsx`, `resources/js/pages/FieldStaffWorklistPage.jsx`, `resources/js/components/CareOps/*.jsx`
    *   Dependencies: Task: Implement CareOps APIs
    *   Acceptance: Care Dashboards and Worklists are functional in React.

*   **Task: Implement Mobile Field App API (v1)**
    *   Description: Create `routes/api_mobile.php`. Implement specific controllers (`App\Http\Controllers\Api\Mobile\V1\*`) and services (`MobileVisitService`, `MobileNoteService`) for mobile endpoints (visits, clock-in/out, tasks, notes).
    *   Code Touchpoints: `routes/api_mobile.php`, `app/Http/Controllers/Api/Mobile/V1/*`, `app/Services/Mobile*Service.php`
    *   Dependencies: Task: Implement CareOps Entities
    *   Acceptance: Mobile API endpoints are functional, authenticated, and lightweight.

*   **Task: Finalize Legacy Placement Isolation**
    *   Description: Review and refactor any remaining legacy placement logic into isolated services/domains (`Domain/LegacyPlacement`, `App\Services\LegacyPlacementService`). Ensure no coupling to V2.1 flows.
    *   Code Touchpoints: `app/Http/Controllers/Legacy/*`, `app/Models/OldPlacementModel.php`, `app/Services/LegacyPlacementService.php`
    *   Dependencies: Phase 4 Complete
    *   Acceptance: Legacy placement code is clearly isolated and ready for future removal.

### Phase 6: Full Legacy Removal, Cleanup & Hardening

*   **Task: Aggressive Legacy Blade UI & Route Deletion**
    *   Description: Delete *all* remaining Blade views (except `app.blade.php`, auth, error pages). Remove their associated routes. Delete `app/Http/Controllers/Legacy` if empty.
    *   Code Touchpoints: `resources/views/**/*.blade.php`, `routes/**/*.php`, `app/Http/Controllers/Legacy/*`
    *   Dependencies: Phase 5 Complete
    *   Acceptance: No legacy Blade UI accessible. `resources/views` contains only SPA bootstrap, auth, error.

*   **Task: Thorough CSS & Asset Cleanup**
    *   Description: Remove all non-Vite generated assets from `public/`. Delete Bootstrap, jQuery, etc., from `public/` and `package.json`. Verify `tailwind.config.js`.
    *   Code Touchpoints: `public/*`, `package.json`, `tailwind.config.js`
    *   Dependencies: Task: Aggressive Legacy Blade UI Deletion
    *   Acceptance: `public/` contains only Vite assets. `package.json` clean.

*   **Task: Finalize Routing Structure**
    *   Description: Ensure `routes/web.php` is minimal (auth + SPA catch-all). `routes/api.php` is clean and versioned. Remove `routes/cc2.php` if superseded.
    *   Code Touchpoints: `routes/web.php`, `routes/api.php`, `routes/cc2.php`
    *   Dependencies: Task: Aggressive Legacy Blade UI Deletion
    *   Acceptance: Routing files are lean and logically structured.

*   **Task: Comprehensive RBAC & Security Audit**
    *   Description: Full audit of Policies. Verify frontend-backend permission enforcement. Review Laravel 11 security headers. Implement API rate limiting.
    *   Code Touchpoints: `app/Policies/*`, `app/Http/Middleware/*`, `config/cors.php`, `config/hashing.php`, `bootstrap/app.php`
    *   Dependencies: All previous tasks
    *   Acceptance: Security posture documented and validated.

*   **Task: Final E2E Testing & QA**
    *   Description: Execute all PHPUnit, Dusk, Playwright tests. Ensure 100% pass rate.
    *   Perform extensive manual E2E testing across all user roles.
    *   Code Touchpoints: `tests/**/*.php`, `tests/e2e/*.spec.js`
    *   Dependencies: All previous tasks
    *   Acceptance: All tests pass. No UI/UX inconsistencies or bugs. Application is stable.

*   **Task: Update Documentation**
    *   Description: Update `README.md`, `CODE_REVIEW.md` and any internal documentation to reflect the final Laravel 11 + React SPA architecture.
    *   Code Touchpoints: `README.md`, `CODE_REVIEW.md`, `IMPLEMENTATION_PLAN_v3_Laravel11.md`
    *   Dependencies: All previous tasks
    *   Acceptance: Documentation accurately reflects the final architecture.

---

## (5) Confirm Implementation-Readiness

The revised plan, with its enhanced detail, aggression against legacy components, and explicit leveraging of Laravel 11 features, is now **implementation-ready** for an AI coding agent. Each task has clear objectives, code touchpoints, and acceptance criteria.

**Areas still carrying high risk and likely needing human judgment:**

1.  **Complex Data Migrations:** The data migration from `Patient` attributes to `TransitionNeedsProfile` (Phase 4) is critical. While steps are outlined, any unforeseen data edge cases or constraints will require human intervention to ensure integrity. The initial definition of `TransitionNeedsProfile` fields based on `triage_summary` and `risk_flags` needs careful review to prevent data loss.
2.  **Unforeseen Composer Conflicts:** While a Laravel 8 to 11 upgrade plan is outlined, specific third-party package conflicts that are not immediately apparent could derail Phase 1 and require human expertise to resolve or find alternatives.
3.  **UI/UX Edge Cases:** While a core component library and design system are planned, edge cases in complex forms, accessibility, or highly dynamic interactive components might require human design judgment to ensure a truly unified and intuitive user experience that goes beyond the wireframes.
4.  **AI Prompt Engineering:** The `GeminiService` integration will involve prompt engineering. Achieving optimal AI responses (SBAR summarization, risk analysis) will likely require iterative human-guided refinement of prompts and response parsing.
5.  **Sensitive Data Handling:** Any tasks involving sensitive patient data, especially during migration or when integrating with external APIs (like Gemini), carry inherent risks that demand human oversight and validation for PHIPA/HIPAA compliance.
6.  **Performance Optimization:** As the SPA grows and API usage increases, identifying and resolving performance bottlenecks (N+1 queries, slow API responses, large React bundles) will need human analysis and optimization strategies.
