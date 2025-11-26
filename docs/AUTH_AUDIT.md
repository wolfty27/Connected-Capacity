# Authentication & Authorization Audit - November 21, 2025

This document details the current authentication and authorization mechanisms within the Connected Capacity application.

## 1. Authentication Configuration (`config/auth.php`)

*   **Default Guard:** `web` (Session-based)
*   **Default Password Reset:** `users`
*   **Guards:**
    *   `web`: Uses `session` driver and `users` provider.
    *   *Note:* `sanctum` is NOT explicitly defined in the `guards` array in `config/auth.php`, though `User` model uses `HasApiTokens` trait. This suggests default Sanctum configuration is likely relying on its own service provider defaults or is not fully configured for API auth yet.
*   **Providers:**
    *   `users`: Uses `eloquent` driver with `App\Models\User::class`.

## 2. User Model (`App\Models\User`)

*   **Role Definitions:**
    *   The `User` model has a `role` attribute in `$fillable`.
    *   **CRITICAL:** There are **NO** class constants (e.g., `ROLE_ADMIN`, `ROLE_HOSPITAL`) or helper methods (e.g., `isAdmin()`, `hasRole()`) defined in the `User` model. Role logic appears to be loose strings handled in controllers/middleware.
    *   Relationships: `hospitals` (hasOne), `organization` (belongsTo), `memberships` (hasMany), `coordinatedPatients` (hasMany), `assignedServiceAssignments` (hasMany).

## 3. Authorization Policies (`App\Providers\AuthServiceProvider`)

*   **Registered Policies:**
    *   `App\Models\Referral` => `App\Policies\ReferralPolicy`
    *   `App\Models\TriageResult` => `App\Policies\TriageResultPolicy`

*   **Policy Files (`app/Policies/`):**
    *   `ReferralPolicy.php`
    *   `TriageResultPolicy.php`

*   **Usage:**
    *   A search for `Gate::`, `$user->can()`, and `$this->authorize()` in `app/` returned **NO RESULTS**.
    *   *Implication:* The defined policies might be unused or used implicitly via Resource Controllers (though unlikely given the search results). Authorization logic is likely hardcoded in controllers using `if ($user->role == '...')` checks.

## 4. Middleware (`app/Http/Middleware/`)

Several custom middleware classes suggest role/context-based access control:

*   `AdminRoutes.php`: Likely enforces admin role access.
*   `AuthenticatedRoutes.php`: Likely a custom auth check (redundant with `auth` middleware?).
*   `UserAuthenticate.php`: Another potential custom auth middleware.
*   `EnsureOrganizationContext.php`: Sets context based on organization?
*   `EnsureOrganizationRole.php`: Checks user's role within an organization.
*   `EnsureFeatureEnabled.php`: Feature flag implementation.

## 5. Findings & Migration Risks

1.  **Lack of Centralized Role Definitions:** The absence of constants in `User` model means "magic strings" for roles (e.g., 'admin', 'hospital') are likely scattered throughout the codebase. This needs to be standardized in Phase 3.
2.  **Underutilized Policies:** Policies exist but appear unused in standard ways. The migration to Laravel 11 is the perfect time to adopt standard Policy-based authorization.
3.  **Custom Middleware Redundancy:** The existence of `AuthenticatedRoutes` and `UserAuthenticate` alongside Laravel's standard `Authenticate` middleware suggests potential technical debt or "split-brain" auth logic that needs consolidation.
4.  **Sanctum Configuration:** Explicit Sanctum guard configuration is missing in `config/auth.php`, which will be necessary for the API-driven React SPA.

