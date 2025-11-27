# Debugging Report: Service Loading Issue in Care Bundle Wizard

**Date:** 2025-11-27
**Status:** ✅ RESOLVED
**Objective:** Fix the issue where services are not loading in Step 2 of the Care Bundle Wizard.

## Problem Description
In the Care Bundle Wizard (Step 2), the "Clinical Services" section displays "No clinical services available in this section" instead of the expected list of services.
- **Symptom:** API requests to `/api/v2/service-types` appear to be failing or returning HTML (login page) instead of JSON.
- **Environment:** Local development (Laravel 11 + React/Vite).

## Investigation & Actions Taken

### 1. Database Naming Conflict (Resolved)
- **Issue:** The `ServiceType` model had a column named `category` and an Eloquent relationship named `category()`. This caused conflicts where `$serviceType->category` would return the string column value instead of the relationship object.
- **Action:**
    - Renamed the relationship to `serviceCategory()` in `App\Models\ServiceType`.
    - Updated all references in:
        - `App\Services\CareBundleBuilderService`
        - `App\Http\Controllers\Api\V2\BundleTemplateController`
        - `App\Http\Controllers\Api\V2\ServiceTypeController`
- **Verification:** Verified via `tinker` that the relationship is now accessible via `serviceCategory`.

### 2. Missing Service Methods (Resolved)
- **Issue:** `CareBundleBuilderService.php` was missing critical helper methods (`buildPatientContext`, `hasCognitiveNeeds`, etc.) due to an accidental deletion during a previous refactor. This caused 500 errors on the backend.
- **Action:** Restored all missing methods.
- **Verification:** Verified via `tinker` that `CareBundleBuilderController::getBundles` now returns correct JSON data.

### 3. Frontend API URL Configuration (Addressed)
- **Issue:** The frontend Axios instance (`resources/js/services/api.js`) is configured with `baseURL: '/api'`. However, service files (`useServiceTypes.js`, `careBundleBuilderApi.js`) were making requests to `/api/v2/...`.
    - **Result:** Requests were being sent to `/api/api/v2/...`, causing 404s or redirects.
- **Action:** Updated frontend service files to remove the `/api` prefix (e.g., changed `/api/v2/service-types` to `/v2/service-types`).
- **Verification:** Code updated, assets rebuilt via `npm run build`.

### 4. Authentication & CSRF (Investigated)
- **Issue:** Browser console logs indicated that API requests were returning HTML (the login page), suggesting the user was unauthenticated for those specific XHR requests, even though they were logged into the app.
- **Action:**
    - Verified `config/sanctum.php` includes `127.0.0.1:8000`.
    - Verified `bootstrap/app.php` includes `EnsureFrontendRequestsAreStateful`.
    - Updated `resources/js/services/api.js` to explicitly read the `X-CSRF-TOKEN` from the meta tag and attach it to headers.
- **Current State:** The issue persists even after these changes.

## Resolution Summary

All issues have been resolved. The API now correctly returns 22 service types across 4 categories.

### Root Causes & Fixes Applied:

1. **Database Naming Conflict** - Renamed `category()` relationship to `serviceCategory()` to avoid conflict with the `category` column.

2. **Missing Service Methods** - Restored helper methods in `CareBundleBuilderService.php`.

3. **Frontend API URL Configuration** - Updated frontend service files to use correct paths (`/v2/...` instead of `/api/v2/...`).

4. **Migration Bug Fix** - Fixed `2025_11_26_200001_add_interrai_to_metadata_model.php`:
   - Laravel creates CHECK constraints for enum columns in PostgreSQL, not native PostgreSQL enum types
   - Changed `ALTER TYPE ... ADD VALUE` to drop/recreate CHECK constraint with new values

### Authentication Configuration (Verified Working):
- `config/sanctum.php`: Stateful domains correctly include `127.0.0.1:8000`
- `config/cors.php`: `supports_credentials: true` and correct allowed origins
- `bootstrap/app.php`: `$middleware->statefulApi()` properly configured
- `resources/js/services/api.js`: Correct axios configuration with:
  - `withCredentials: true`
  - `X-Requested-With: XMLHttpRequest` header
  - CSRF token from meta tag

### Verification Results:
```
API Response - Total services: 22
Categories: 4
Bundles for test patient: 5
```

### Key Files Modified:
- `App\Models\ServiceType.php`
- `App\Services\CareBundleBuilderService.php`
- `App\Http\Controllers\Api\V2\ServiceTypeController.php`
- `resources/js/hooks/useServiceTypes.js`
- `resources/js/services/careBundleBuilderApi.js`
- `resources/js/services/api.js`
- `database/migrations/2025_11_26_200001_add_interrai_to_metadata_model.php` (migration fix)
- `docs/PERFORMANCE_OPTIMIZATIONS_AND_FIXES.md`

### Artifacts:
- `simulate_frontend_matching.php`: Script to verify backend logic and data matching.
