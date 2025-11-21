# Codebase Review & Refactoring Plan (V2.1 Aligned)

## 1. Domain Modeling & Role Management (Priority High)

**Context:** The application is transitioning from a "Retirement Home Placement" system (V1) to a "High-Intensity Home & Community Care Orchestration" platform (V2.1). The codebase currently contains a mix of legacy and new logic.

### A. User Roles & Constants
**Issue:** Magic strings for user roles are scattered throughout the application. The V2 roles (`SPO_ADMIN`, `SPO_COORDINATOR`, `SSPO_COORDINATOR`, `FIELD_STAFF`) coexist with V1 roles (`admin`, `hospital`, `retirement-home`).
**Solution:** Centralize all roles in the `User` model as class constants to ensure type safety and easy reference.

**Refactoring Plan:**
1.  Update `App\Models\User.php`:
    ```php
    class User extends Authenticatable
    {
        // Legacy Roles (Potentially deprecated in V2.1 future, but kept for V1 compatibility)
        const ROLE_ADMIN = 'admin';
        const ROLE_HOSPITAL = 'hospital'; 
        const ROLE_RETIREMENT_HOME = 'retirement-home';
        const ROLE_PATIENT = 'patient';

        // V2.1 SPO/SSPO/Field Staff Roles
        const ROLE_SPO_ADMIN = 'SPO_ADMIN';
        const ROLE_SPO_COORDINATOR = 'SPO_COORDINATOR';
        const ROLE_SSPO_COORDINATOR = 'SSPO_COORDINATOR';
        const ROLE_FIELD_STAFF = 'FIELD_STAFF';
        
        // Helper method to check for V2 access
        public function isV2User(): bool
        {
            return in_array($this->role, [
                self::ROLE_SPO_ADMIN,
                self::ROLE_SPO_COORDINATOR,
                self::ROLE_SSPO_COORDINATOR,
                self::ROLE_FIELD_STAFF
            ]);
        }
    }
    ```

### B. Configuration Management
**Issue:** Hardcoded asset paths and potentially environment-specific logic.
**Solution:** Create `config/connected.php` to manage V2.1 defaults and feature flags.

**Draft `config/connected.php`:**
```php
return [
    'version' => '2.1',
    
    // Defaults for patient intake/profiles
    'defaults' => [
        'patient_image' => env('DEFAULT_PATIENT_IMAGE', '/assets/images/patients/default.png'),
    ],

    // Feature Flags for progressive migration
    'features' => [
        'legacy_dashboard' => env('ENABLE_LEGACY_DASHBOARD', true),
        'v2_intake' => env('ENABLE_V2_INTAKE', true),
        'ai_integrations' => env('ENABLE_AI_INTEGRATIONS', true),
    ],
    
    // Organization Types defined in Spec 2.1
    'organization_types' => [
        'SPO' => 'Service Provider Organization',
        'SSPO' => 'Specialized Service Provider Organization',
    ],
    
    'ai' => [
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash-preview-09-2025'),
    ]
];
```

## 2. Refactoring for Clarity: Dashboard & Controllers

### A. `DashboardController` Refactor
**Observation:** The existing `DashboardController` in `App\Http\Controllers\Legacy` attempts to route traffic for *all* users, including V2 users (`Redirect::route('cc2.dashboard')`).
**Strategy:** 
1.  Keep the Legacy controller focused *only* on Legacy roles.
2.  Ensure the V2 routes are handled by a dedicated `CC2\DashboardController`.
3.  Use a Middleware or a "Router" service to handle the initial login redirection, rather than polluting the controller index method.

### B. `PatientsController` Refactor
**Observation:** This controller is heavily coupled to the V1 "Hospital/Retirement Home" logic.
**Strategy:** 
1.  Do not hack V2 logic into this controller.
2.  Create new controllers under `App\Http\Controllers\CC2` (e.g., `CC2\PatientController`) to handle the "Transition Needs Profile" (TNP) and "Transition Review" workflows defined in Spec 2.1.
3.  Leave `Legacy\PatientsController` for backward compatibility until V1 is fully retired.

## 3. Frontend Migration (Vite)

**Context:** The project uses `laravel-mix` (Webpack). V2.1 is a major evolution and should use modern tooling.
**Recommendation:** Migrate to **Vite**.
1.  Remove `laravel-mix`.
2.  Install `vite` and `laravel-vite-plugin`.
3.  Update `package.json` scripts.
4.  Replace `mix()` helpers in Blade templates with `@vite(['resources/css/app.css', 'resources/js/app.js'])`.

## 4. UI/UX Implications & API Strategy (New Section)

**Observation:** The provided wireframes (`Referral Dashboard`, `Care Dashboard (SPO Role)`, `Transition Review Detail`, `Mobile Field App`) showcase a modern, highly interactive, and role-specific user experience built with React and Tailwind CSS.
**Key Implications:**
*   **API-First Approach:** The backend for V2.1 features should primarily expose **RESTful APIs** (likely JSON-based) consumed by the React frontend. This moves away from heavy server-side rendering for complex V2 screens.
*   **Dedicated API Controllers:** Consider creating `app/Http/Controllers/Api/V2/` to host dedicated API endpoints for V2.1 features.
*   **Laravel API Resources:** Utilize Laravel's [API Resources](https://laravel.com/docs/10.x/eloquent-resources) to format data consistently for the frontend.
*   **AI Service Layer:** The ubiquitous "Gemini" features (forecasting, summarization, root cause analysis) necessitate a dedicated **`App\Services\GeminiService`** (or similar) to abstract AI API calls, handle prompts, and parse responses. This service will be consumed by API controllers.
*   **Mobile Field App Support:** The mobile wireframe highlights the need for robust, potentially offline-capable APIs for `FIELD_STAFF` users, focusing on visit management and voice notes.
*   **Data Model Alignment:** Ensure existing and new models (e.g., `AssessmentForm` evolving into `TransitionNeedsProfile`) can support the specific data points and flags present in the UI (e.g., "Clinical Flags" for Dementia, Mobility/Falls).

## 5. Dependency Review

*   **Laravel 10:** Excellent.
*   **React 19:** Excellent, now clearly the primary frontend framework for V2.1.
*   **`akaunting/laravel-apexcharts`:** Verify this supports the data visualization needs for V2 (e.g., patient flow analytics). If not, consider a more modern charting library compatible with React.
*   **Action:** Ensure `phpunit/phpunit` and `laravel/dusk` are configured to test the new V2 workflows (SPO Intake -> Transition Review).

## 6. Next Steps (Phase 0-1 of Spec 2.1)

1.  **Standardize Roles:** Apply the `User` model constants.
2.  **Config Setup:** Create `config/connected.php` (including AI API keys).
3.  **SPO/SSPO Implementation:** Ensure `ServiceProviderOrganization` model accurately reflects the "Capabilities" and "Regions" defined in the RFP/Spec.
4.  **AI Service Stub:** Create a basic `App\Services\GeminiService` with methods for the AI features identified in the UI/UX.
5.  **Vite Migration:** Prioritize the migration from Laravel Mix to Vite to support the modern React frontend efficiently.
