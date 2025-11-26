# Connected Capacity V2.1 – Laravel 11 + Unified SPA Implementation Plan

## 0. Non-Negotiable Requirements (Read First)

These are **hard constraints**. Any implementation work that violates these is considered incorrect:

1. **Single Front-End Application**
   - The app must behave as **one React SPA**, not “legacy + new” stitched together.
   - No page should ever render as a standalone Blade UI, except for the bare SPA bootstrap (and temporary auth/exception pages as needed).

2. **No Split-Brain UI Loading**
   - We must **eliminate** all cases where:
     - Legacy Blade routes and React routes both exist for the same/overlapping flows.
     - React is injected into Blade “sub-pages” or partials.
   - At the end of this plan, there is exactly **one** user-facing HTML entrypoint for the app: `resources/views/app.blade.php` (or equivalent) that bootstraps the React SPA.

3. **All User-Facing Screens in React**
   - All interactive UI flows (dashboards, lists, detail views, forms, wizards, reporting, TNP builder, care dashboards, etc.) must be implemented as React pages.
   - Blade is **only** allowed for:
     - SPA shell (`app.blade.php`)
     - Auth scaffolding (until migrated to SPA)
     - Rare exception/error pages.

4. **Unified Design System**
   - The front-end uses **Tailwind CSS + Headless UI (or simple, consistent custom components)**.
   - Legacy Bootstrap, jQuery plugins, and ad hoc CSS must be systematically removed or isolated and then deleted.
   - The UX patterns defined in the wireframes/notes (e.g., for TNP, dashboards) must be **generalized and applied to all pages in the app**, not just those example screens.

5. **Backend on Laravel 11**
   - The **target framework** is **Laravel 11** on **PHP 8.2+**.
   - Laravel 11’s:
     - Native Vite integration
     - Modern routing & middleware structure
     - Refined queue, cache, and HTTP layers  
     must be used as the base for the new architecture.

6. **Backend-as-API**
   - Laravel 11 remains the backend and system of record:
     - Auth & authorization (policies/guards)
     - Domain models & services (Patients, Bundles, Providers, TNP, CareOps)
     - Queues, jobs, notifications, integrations
   - The API and SPA must be versioned, documented, and testable.

7. **Incremental but Safe Migration**
   - The app must remain deployable and functional as we migrate.
   - Use feature flags and route-level toggles to stage the SPA adoption and legacy removal.
   - Upgrade from Laravel 8 → 11 must be done in controlled steps with clear acceptance criteria.

---

## 1. Current-State Architectural Assessment (Condensed)

- **Framework:** Laravel **8.x** (current), legacy structure.
- **PHP:** 8.0/8.1 (assumed).
- **Front-End:**
  - Legacy Blade views with Bootstrap + custom CSS.
  - React + Vite components with Tailwind.
  - Some React screens rendered inside Blade pages → hydration issues, double rendering, CSS conflicts.
- **Routing:**
  - `routes/web.php` mixes:
    - Blade-rendered pages.
    - SPA entrypoints.
    - React “drop-ins” inside Blade containers.
- **Domain:**
  - Historical “placement” logic (hospitals → retirement homes).
  - Emerging V2.1 “care orchestration” logic (bundled home/community care).
  - Responsibilities blurred across controllers and Eloquent models.

Primary smells:
- **Split-brain UI** (Blade vs React).
- **Aging core** (Laravel 8) with long-term maintenance risk.

---

## 2. Target Architecture (Laravel 11 + SPA)

### 2.1 High-Level

- **Backend:** Laravel **11** monolith, API-first mindset.
- **Front-End:** Single React SPA served by:
  - `resources/views/app.blade.php` → `<div id="root"></div>` → React mounts.
- **Routing:**
  - Laravel `routes/web.php`:
    - SPA catch-all route (e.g., `/app`).
    - Auth routes (until SPA-driven auth implemented).
    - Minimal non-auth public routes if needed.
  - `routes/api.php`:
    - Versioned API, including mobile/field app endpoints.
- **Domain Modules (Namespaced):**
  - `Domain/Patients`
  - `Domain/Bundles`
  - `Domain/Providers`
  - `Domain/CareOps`
  - `Domain/Tnp` (Transition & Navigation Planning)
  - `Domain/LegacyPlacement` (temporary, isolated, removable)

Each module exposes:
- Eloquent models (or domain models + mappers).
- Services.
- Policies.
- Thin controllers.
- Tests.

---

## 3. Migration Principles

1. **Upgrade the Foundation Before Building**
   - Laravel 11 upgrade comes early, so that:
     - New SPA work is done on a supported, stable framework.
     - Vite and SPA tooling align with Laravel 11’s defaults.

2. **Inventory Before Editing**
   - Before any large deletions, create inventories of:
     - Blade views and their routes.
     - Front-end entrypoints and asset pipelines.
     - Domain/business-critical flows.

3. **One Route, One Owner**
   - For any given user-facing use case (e.g., patient listing, care dashboard, TNP review), there must be **exactly one primary UI**.
   - Once React takes over the use case, the Blade equivalent is marked `LEGACY_BLADE` and then removed in a later phase.

4. **Feature Flags & Phased Cutovers**
   - Use config/feature toggles and/or environment flags to:
     - Gradually direct roles or flows into the SPA.
     - Maintain fallbacks while migrating, then permanently switch and remove fallbacks.

5. **Shared Layout & Components**
   - The SPA must use a **single layout system** and a **shared UI component library**.
   - All pages, including those not present in original wireframes, must consume these patterns.

6. **Strict Deletion Passes**
   - After each migration milestone, delete:
     - Redundant Blade views.
     - Associated routes.
     - Legacy CSS/JS assets.
   - Don’t leave “temporary” duplication in place.

---

## 4. Phase Plan (Including Laravel 11 Upgrade)

### Phase 0 – Preflight Assessment & Safety Net (Week 0–1)

**Goal:**  
Understand the current Laravel 8 system well enough to safely upgrade and migrate.

**Tasks:**

1. **Codebase Survey**
   - Confirm Laravel version and PHP version.
   - Identify all:
     - Blade views (and their filenames).
     - Mix/Vite build configs and asset pipelines.
     - Auth setup (guards, providers, Sanctum/Passport).

2. **Blade View & Route Inventory**
   - Generate a machine-readable inventory:
     - Blade view path
     - Related route name and URL
     - Purpose (dashboard, listing, detail, form, admin, etc.)
   - Tag them with comments in controllers/routes:
     - `// LEGACY_BLADE_VIEW: <short description>`

3. **Test Baseline**
   - Run existing tests (if any) and note:
     - Failing suites.
     - Critical smoke tests that must pass after upgrade.

**Definition of Done (Phase 0):**
- We have a **Blade + routes inventory**.
- We have a clear understanding of:
  - Auth configuration.
  - Build tooling configuration.
- Baseline test suite status is documented.

---

### Phase 1 – Upgrade from Laravel 8 → 11 (Week 1–2)

**Goal:**  
Upgrade the backend to **Laravel 11** while keeping behavior functionally equivalent.

**Tasks:**

1. **Environment & Composer Updates**
   - Upgrade PHP to **8.2+**.
   - Update `composer.json`:
     - `laravel/framework` to `^11.0`.
     - Update first-party packages to Laravel 11-compatible versions.
   - Run `composer update` and resolve conflicts.

2. **Laravel 11 Structure & Config**
   - Align file structure and configuration with Laravel 11 conventions:
     - `bootstrap/app.php` (new style) vs `config/app.php` changes.
     - Update `app/Providers` as needed.
     - Ensure HTTP kernel, console kernel, exception handler follow Laravel 11 patterns.
   - Migrate any deprecated helpers or APIs to their Laravel 11 equivalents.

3. **Auth & Sanctum/Passport Review**
   - Confirm guard configuration still valid under Laravel 11.
   - Update Sanctum/Passport (if used) to supported versions.
   - Ensure login/logout flows still work.

4. **Tests & Manual Smoke Testing**
   - Run unit/feature tests.
   - Manually verify:
     - Login
     - Dashboard access
     - A couple of representative flows (e.g. patient view, simple forms)

**Definition of Done (Phase 1):**
- App runs on Laravel 11 + PHP 8.2+.
- Critical flows work as before.
- No major framework deprecation errors remain in logs.
- Mix/Vite situation can still be messy at this stage (handled in Phase 2).

---

### Phase 2 – SPA Bootstrap & Vite Consolidation (Week 2–3)

**Goal:**  
Standardize on Laravel 11’s Vite & SPA bootstrap, with a single React entrypoint.

**Tasks:**

1. **Vite as the Single Build Tool**
   - Remove old Laravel Mix configuration and references.
   - Ensure `vite.config.js` defines:
     - Single main entry (`resources/js/app.tsx` or `app.jsx`).
     - Tailwind config integration.
   - Update `package.json` scripts (`dev`, `build`, `preview`).

2. **SPA Blade Bootstrap**
   - Create/confirm `resources/views/app.blade.php` as the **only** SPA bootstrap view.
   - It must:
     - Load the Vite-built React bundle.
     - Provide CSRF token and base API URL.
     - Option A: embed the authenticated user/roles as JSON.
     - Option B: use a `/api/me` call on SPA load.

3. **Web Routes Guardrails**
   - In `routes/web.php`:
     - Add SPA route group:
       ```php
       Route::middleware(['auth'])->group(function () {
           Route::view('/app/{any?}', 'app')->where('any', '.*');
       });
       ```
     - Clearly mark any legacy Blade app routes:
       - Prefix route names with `legacy.` or add `// LEGACY_BLADE` comments.

4. **Initial React Entry & Basic Auth Context**
   - Implement the React entry file (`app.tsx`/`app.jsx`) that:
     - Renders a basic `<App />` component.
     - Initializes a basic `AuthContext` wired to `/api/me`.

**Definition of Done (Phase 2):**
- Laravel 11 app builds and runs with Vite as the sole asset pipeline.
- There is exactly one SPA Blade bootstrap view.
- `/app` (or equivalent) renders a basic React shell authenticated via Laravel 11.
- Legacy Blade app routes are clearly marked but still functional.

---

### Phase 3 – SPA Shell, Navigation & Role-Aware Layout (Week 3–5)

**Goal:**  
Build the unified SPA shell and replace top-level legacy dashboards and navigation.

**Tasks:**

1. **Role & Permission System**
   - Confirm app roles: `Admin`, `SPO`, `SSPO`, `FieldStaff`, etc.
   - Backend:
     - Introduce improved configuration for roles and permissions (could be enums/consts or tables).
     - Add/validate policies for key actions (view patients, manage bundles, view care dashboards).
   - Frontend:
     - Enhance `AuthContext` with roles and permissions.
     - Provide helpers: `hasRole(role)`, `can(permission)`.

2. **SPA Layout & Navigation**
   - Implement:
     - `AppLayout`: sidebar + topbar + content region.
     - `PageHeader`: standard title/description/actions area.
   - Build a single **navigation config** for authorized routes:
     - Path (e.g. `/patients`, `/tnp`, `/care-dashboard`).
     - Label & icon.
     - Visible roles.

3. **React Router Setup**
   - Use React Router (v6+):
     - Root routes under `/app`.
     - Route guards enforced from `AuthContext`.
   - Implement SPA versions of top-level dashboards (Admin/SPO/SSPO as applicable).

4. **Core UI Component Library**
   - Build reusable components:
     - `DataTable` (pagination, sort, filters).
     - `Modal`.
     - `PrimaryButton`, `SecondaryButton`.
     - `FormField`, `FormSection`.
   - These components must be flexible enough to support pages that have not yet been migrated.

5. **Legacy Dashboard Deletion**
   - Once SPA dashboards reach parity with Blade:
     - Remove corresponding Blade views.
     - Remove/redirect old routes.

**Definition of Done (Phase 3):**
- Authenticated users land in `/app` SPA, not a Blade dashboard.
- SPA navigation sidebar controls all in-app navigation.
- Legacy Blade dashboards are removed or redirected.
- No double-layout (Blade wrapping React) for dashboards.

---

### Phase 4 – TNP (Transition Review) & AI Integration (Week 5–7)

**Goal:**  
Implement Transition & Navigation Planning (TNP) in Laravel 11 domain + React SPA, with Gemini-based summarization.

**Tasks:**

1. **Domain: TNP in Laravel 11**
   - Create `Domain/Tnp`:
     - Entities: `TnpReview`, `TnpItem`, `TnpSummary` (adjust names to actual).
     - Relationships to `Patient`, `Bundle`, `Provider`.
   - Controllers:
     - `TnpReviewController`:
       - `index`, `show`, `store`, `update`, `complete`.
   - Policies:
     - Who can create/view/complete TNPs (role-based).

2. **GeminiService Integration**
   - Build `App\Services\GeminiService` that:
     - Accepts structured patient data and context.
     - Calls Gemini API.
     - Returns summary text/sections.
   - Queue integration:
     - For long-running summaries, offload to jobs and notify SPA when ready.

3. **React Screens**
   - `TnpReviewListPage`:
     - Uses `DataTable`.
     - Filter by status, SPO, patient.
   - `TnpReviewDetailPage`:
     - Uses `AppLayout` + `PageHeader`.
     - Tabs: “Overview”, “Inputs”, “AI Summary”, “Recommended Plan”.
     - Call-to-action: “Generate / Refresh AI Summary”.

4. **UX Pattern Propagation**
   - Take UX patterns from TNP wireframes (header, metadata, primary actions, tabs) and:
     - Apply the same patterns to other critical pages (patients list/detail, care dashboard).

5. **Legacy TNP/Transition Views Removal**
   - Identify any Blade views for TNP/transition.
   - When SPA parity is achieved, delete those views and routes.

**Definition of Done (Phase 4):**
- TNP is fully operable via SPA on Laravel 11.
- AI summary integration works via GeminiService.
- Legacy TNP/transition Blade views are removed.
- TNP UX patterns are reused across at least a few other screens.

---

### Phase 5 – Care Operations & Mobile Field App API (Week 7–9)

**Goal:**  
Implement Care Operations module (assignments, visits, tasks) and mobile/field app APIs.

**Tasks:**

1. **Domain: CareOps**
   - Create `Domain/CareOps`:
     - Entities: `CareAssignment`, `Visit`, `Task`, `Note` (refine to actual model names).
   - Controllers & Services:
     - `CareAssignmentController`, `VisitController`, `TaskController` (as needed).
     - Support for assign SPO/SSPO, schedule visits, update status, record notes.

2. **SPA: Care Dashboards**
   - `CareDashboardPage` (SPO/SSPO):
     - High-level metrics & lists.
   - `FieldStaffWorklistPage`:
     - “My Visits Today”, “My Tasks”, “Recently Completed”.
   - All use shared layout and `DataTable`/forms.

3. **Mobile API (Laravel 11)**
   - Add API group in `routes/api.php`:
     - `Route::prefix('mobile')->middleware('auth:sanctum')` etc.
   - Endpoints for:
     - `GET /mobile/visits`
     - `PATCH /mobile/visits/{id}`
     - `POST /mobile/notes`
   - Document response shapes in OpenAPI or Markdown.

4. **Legacy Placement Isolation**
   - Move any still-required legacy placement logic into `Domain/LegacyPlacement`.
   - Gate via feature flags or admin-only routes.
   - Ensure new CareOps flows aren’t coupled to legacy placement code.

**Definition of Done (Phase 5):**
- SPO and field staff can manage care operations via SPA.
- Mobile API endpoints exist, are authenticated, and have tests.
- Legacy placement logic is isolated and clearly removable.

---

### Phase 6 – Full Legacy Removal, Cleanup & Hardening (Week 9+)

**Goal:**  
Fully remove split-brain architecture, delete Blade UIs, clean assets, and harden on Laravel 11.

**Tasks:**

1. **Legacy Blade Removal**
   - Using the inventory from Phase 0 and `LEGACY_BLADE` markers:
     - Remove all Blade views replaced by SPA pages.
     - Remove related routes from `routes/web.php`.

2. **CSS & Asset Cleanup**
   - Remove:
     - Bootstrap and related styles.
     - Obsolete JS libraries.
     - Old Mix-generated assets.
   - Verify only Vite-built assets are used.

3. **Routing Finalization**
   - `routes/web.php`:
     - Minimal: auth + SPA + static/public pages.
   - `routes/api.php`:
     - Versioned, clean, and documented.

4. **RBAC & Security Review**
   - Confirm policies are applied to all domain controllers.
   - Confirm SPA route guards mirror backend authorization.

5. **Testing & QA**
   - Feature tests for:
     - TNP flows.
     - CareOps flows.
     - Mobile API.
   - Manual end-to-end: ensure:
     - No app journey requires Blade UI pages.
     - No React-in-Blade partials remain.
     - No double CSS or hydration errors.

**Definition of Done (Phase 6):**
- Connected Capacity runs on **Laravel 11** with a **single React SPA** front-end.
- No split-brain UI remains.
- Domain modules and API are clean and test-backed.
- The system is ready for ongoing feature development on a modern, maintainable foundation.

---

## 5. Working Agreement for AI-Assisted Implementation

- Implementation agents (Gemini/Codex) must:
  - Respect all **Non-Negotiable Requirements**.
  - Work **phase-by-phase**, keeping the app deployable.
  - Never introduce new Blade-based application UIs.
  - Where new features are built, always reuse core SPA layouts and components.
- Any deviation should be explicitly commented and treated as a defect.