# Security Audit - November 21, 2025

This document summarizes the security audit conducted for the Connected Capacity V2.1 migration.

## 1. Authentication & Authorization

*   **Guards:**
    *   `web`: Standard session-based auth for the SPA/Web routes.
    *   `api`: Defined as `sanctum` driver in `config/auth.php` to support API token authentication.
*   **Middleware:**
    *   `auth:sanctum` is applied to all API routes in `routes/api.php`.
    *   `auth` (web) is applied to authenticated web routes serving the SPA.
    *   Custom middleware like `EnsureOrganizationContext` and `EnsureFeatureEnabled` are correctly aliased in `bootstrap/app.php` and applied to `cc2` routes.
*   **Policies:**
    *   `CareAssignmentPolicy`: Enforces role-based access (Admin, SPO Admin, Field Staff).
    *   `TransitionNeedsProfilePolicy`: Enforces access for creation (Hospital) and viewing/updating (SPO).
    *   Master User bypass is implemented in `EnsureOrganizationContext` and Policies.

## 2. Frontend Security

*   **Route Guards:**
    *   `ProtectedRoute.jsx`: Redirects unauthenticated users to `/login`.
    *   `RoleRoute.jsx`: Restricts access to specific routes based on user role (e.g., `/care-dashboard` for Admins/SPOs).
*   **Context:** `AuthContext` securely manages user state derived from the `/api/user` endpoint.

## 3. API Security

*   **Rate Limiting:**
    *   `throttle:api` middleware is applied to all API routes, limited to 60 requests per minute per user/IP.
*   **Sanctum:**
    *   Configured to use standard Laravel 11 middleware (`VerifyCsrfToken`, `EncryptCookies`).
    *   Stateful domains configured for local development and production APP_URL.

## 4. Recommendations for Future Hardening

*   **CSP Headers:** Implement Content Security Policy (CSP) headers in `bootstrap/app.php` or via a middleware to mitigate XSS.
*   **Strict Transport Security (HSTS):** Ensure HSTS is enabled in production environment configuration (Nginx/Apache).
*   **Audit Logs:** Ensure `AuditLogTest` covers new V2 actions (TNP creation, Care Assignment updates).
*   **Input Validation:** Continue to rigorously validate all API inputs using Form Requests or validation logic in controllers.
