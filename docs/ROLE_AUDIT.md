# Role Audit - November 21, 2025

This document audits all distinct role strings found in the codebase to ensure a safe refactor to `User` model constants.

## 1. Role Constants vs. Usage

The following roles are identified. Checkmarks indicate if a `User::ROLE_*` constant exists.

| Raw String | User Constant | Used In | Notes |
| :--- | :--- | :--- | :--- |
| `admin` | `ROLE_ADMIN` | Controllers, Tests, Policies | Legacy Admin |
| `hospital` | `ROLE_HOSPITAL` | Controllers, Tests | Legacy Hospital User |
| `retirement-home` | `ROLE_RETIREMENT_HOME` | Controllers, Tests | Legacy Retirement Home |
| `patient` | **MISSING** | Controllers, Tests | Used for Patient Users (Auth) |
| `SPO_ADMIN` | `ROLE_SPO_ADMIN` | Tests, Middleware, Dashboard | CC2 Admin |
| `FIELD_STAFF` | `ROLE_FIELD_STAFF` | Tests, Dashboard | CC2 Field Staff |
| `SPO_COORDINATOR` | **MISSING** | Tests, Dashboard | CC2 Role |
| `SSPO_COORDINATOR` | **MISSING** | Tests, Dashboard | CC2 Role |
| `SSPO_ADMIN` | **MISSING** | Requests | CC2 Role |
| `ORG_ADMIN` | **MISSING** | Requests | CC2 Role (likely alias/duplicate?) |
| `COORDINATOR` | **MISSING** | Tests (`OrganizationProfileTest`) | Likely typo for `SPO_COORDINATOR`? |

## 2. Detailed Usage Inventory

### `admin`
*   `app/Policies/ReferralPolicy.php`
*   `app/Policies/TriageResultPolicy.php`
*   `app/Http/Middleware/AdminRoutes.php`
*   `app/Http/Middleware/EnsureOrganizationRole.php`
*   `app/Http/Requests/CC2/UpdateOrganizationProfileRequest.php`
*   `app/Http/Controllers/UserController.php`
*   `app/Http/Controllers/Legacy/MyProfileController.php`
*   `app/Http/Controllers/Legacy/BookingsController.php`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `tests/Unit/AuditLogTest.php`
*   `tests/Feature/LoginFlowTest.php`

### `hospital`
*   `app/Http/Controllers/Legacy/PatientsController.php`
*   `app/Http/Controllers/Legacy/MyProfileController.php`
*   `app/Http/Controllers/Legacy/BookingsController.php`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `app/Http/Controllers/Legacy/HospitalsController.php`
*   `app/Http/Controllers/Api/PatientController.php`
*   `tests/Unit/PatientTest.php`
*   `tests/Unit/NewHospitalTest.php`
*   `tests/Unit/HospitalTest.php`
*   `tests/Feature/PatientListTest.php`
*   `tests/Feature/CC2/OrganizationContextMiddlewareTest.php`
*   `tests/Feature/BookingCreationTest.php`

### `retirement-home`
*   `app/Http/Controllers/Legacy/PatientsController.php`
*   `app/Http/Controllers/Legacy/MyProfileController.php`
*   `app/Http/Controllers/Legacy/BookingsController.php`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `app/Http/Controllers/Legacy/RetirementHomeController.php`
*   `app/Http/Controllers/Api/PatientController.php`
*   `tests/Feature/BookingCreationTest.php`

### `patient`
*   `app/Http/Controllers/Legacy/PatientsController.php` (Creating users with this role)
*   `tests/Unit/PatientTest.php`
*   `tests/Unit/AuditLogTest.php`
*   `tests/Unit/HospitalTest.php`
*   `tests/Feature/PatientListTest.php`

### `SPO_ADMIN`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `tests/Browser/CC2/SpoAdminCrawlTest.php`
*   `tests/Feature/CC2/OrganizationContextMiddlewareTest.php`
*   `tests/Feature/CC2/OrganizationProfileTest.php`
*   `app/Http/Requests/CC2/UpdateOrganizationProfileRequest.php`

### `FIELD_STAFF`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `tests/Browser/CC2/FieldStaffCrawlTest.php`

### `SPO_COORDINATOR`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `tests/Browser/CC2/SpoCoordinatorCrawlTest.php`

### `SSPO_COORDINATOR`
*   `app/Http/Controllers/Legacy/DashboardController.php`
*   `tests/Browser/CC2/SspoCoordinatorCrawlTest.php`

### `SSPO_ADMIN`
*   `app/Http/Requests/CC2/UpdateOrganizationProfileRequest.php`

### `ORG_ADMIN`
*   `app/Http/Requests/CC2/UpdateOrganizationProfileRequest.php`

### `COORDINATOR`
*   `tests/Feature/CC2/OrganizationProfileTest.php`

## 3. Missing Constants

The following roles appear in code but lack `User::ROLE_*` constants:

1.  **`patient`**: Used extensively in legacy controllers and tests.
2.  **`SPO_COORDINATOR`**: Used in Dashboard logic and Dusk tests.
3.  **`SSPO_COORDINATOR`**: Used in Dashboard logic and Dusk tests.
4.  **`SSPO_ADMIN`**: Used in `UpdateOrganizationProfileRequest`.
5.  **`ORG_ADMIN`**: Used in `UpdateOrganizationProfileRequest`.

## 4. Test Dependency Summary (CC2)

Running `php artisan test --testsuite=Feature --filter=CC2` passed (5 tests).

*   `OrganizationContextMiddlewareTest` uses:
    *   `role` => `'hospital'` (as a non-org user)
    *   `organization_role` => `'SPO_ADMIN'`
*   `OrganizationProfileTest` uses:
    *   `organization_role` => `'SPO_ADMIN'`
    *   `organization_role` => `'COORDINATOR'` (Note: This constant does not exist, but the test passes, likely because the test setup expects denial for non-admin, and any string works.)