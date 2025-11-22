# Codebase Review & Architecture (V2.1 Aligned)

## 1. Architectural Overview

The Connected Capacity platform has successfully transitioned from a Laravel 8/Blade legacy system to a modern **Laravel 11** backend serving a **React Single Page Application (SPA)** frontend. This architecture supports the "Bundled High Intensity Home Care – LTC" model with a focus on API-driven interactions, role-based access, and AI integration.

### Core Components

*   **Backend:** Laravel 11 (PHP 8.2+) acts as the API layer, handling data persistence, business logic, authentication, and authorization.
*   **Frontend:** React 18+ (via Vite) provides a unified SPA experience for all user roles. Legacy Blade views have been aggressively removed.
*   **Database:** MySQL/PostgreSQL (implied) utilizing Eloquent ORM for data modeling.
*   **AI Integration:** A dedicated `GeminiService` manages interactions with Google Gemini for TNP summarization and risk analysis.

## 2. Domain Modeling & Data Structure

### Role Management (`App\Models\User`)
User roles are now strictly defined via class constants in the `User` model, eliminating "magic strings":
*   `ROLE_MASTER` ('MASTER'): Platform superuser, bypasses org checks.
*   `ROLE_ADMIN` ('admin'): Legacy/System Admin.
*   `ROLE_SPO_ADMIN` ('SPO_ADMIN'): Service Provider Organization Admin.
*   `ROLE_FIELD_STAFF` ('FIELD_STAFF'): Front-line care providers.
*   `ROLE_HOSPITAL`, `ROLE_RETIREMENT_HOME`: Legacy roles supported for compatibility.

### Key Domain Models
*   **`TransitionNeedsProfile` (TNP):** Central artifact for patient assessment. Stores `clinical_flags`, `narrative_summary`, and AI-generated insights. Linked 1:1 with `Patient`.
*   **`CareAssignment`:** Represents a care bundle assigned to a provider/user for a patient.
*   **`Visit`:** Individual care instances (scheduled vs. actual).
*   **`Task`:** Granular actions required during a visit or assignment.
*   **`Patient`:** Core entity, now enhanced with relationships to TNP and CareOps models.

## 3. API Architecture (`routes/api.php`)

The application exposes a versioned RESTful API (`/api/v2`) authenticated via Laravel Sanctum.

*   **`TnpController`:** Manages creating, viewing, and updating Transition Needs Profiles. Includes endpoints for triggering AI analysis jobs (`POST /tnp/{id}/analyze`).
*   **`CareOpsController`:** Handles assignments, visits, and tasks for the operational dashboard.
*   **`DashboardController`:** Aggregates metrics for different user roles (Hospital, Admin, SPO).
*   **`Mobile/V1` API:** A dedicated namespace for the mobile field app (`/mobile/v1/visits`, `/clock-in`, `/notes`), optimized for field staff workflows.

## 4. Frontend Architecture (React SPA)

### Entry & Routing
*   **`app.blade.php`:** The single HTML entry point, loading Vite assets.
*   **`App.jsx`:** Main React component configuring `react-router-dom`.
*   **`ProtectedRoute`:** Wraps authenticated routes, redirecting guests to `/login`.
*   **`RoleRoute`:** Enforces RBAC on client-side routes (e.g., restricting `/care-dashboard` to SPO Admins).

### Components & Context
*   **`AuthContext`:** Manages global user state (fetched from `/api/user`).
*   **`AppLayout`:** Provides the persistent sidebar/navbar shell.
*   **`UI` Directory:** Reusable atoms like `Button`, `Card`, `Section`, `DataTable`.

## 5. Security & Hardening

*   **RBAC:** Enforced at multiple layers:
    *   **Middleware:** `EnsureOrganizationContext` (with Master bypass), `auth:sanctum`.
    *   **Policies:** Granular Laravel Policies (`CareAssignmentPolicy`, `TransitionNeedsProfilePolicy`) checking user capabilities.
*   **Rate Limiting:** `throttle:api` applied to API routes.
*   **Clean Routing:** Legacy Blade routes have been removed (`routes/web.php` is minimal).

## 6. Legacy Status

*   **Blade Views:** All feature-related Blade views have been deleted.
*   **Legacy Controllers:** `Legacy\PatientsController`, etc., have been stripped of view-rendering logic and now redirect to the dashboard (SPA) or serve as historical reference.
*   **Mix:** Completely replaced by Vite.

This architecture provides a stable, scalable foundation for V2.1 feature expansion.