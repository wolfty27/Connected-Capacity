# Connected Capacity (V2.1)

## Project Overview
Connected Capacity 2.1 is a **Service Orchestration Platform** designed to support the **Bundled High Intensity Home Care – LTC** model for Ontario Health atHome.

It serves as the operational engine for **Service Provider Organizations (SPOs)** and **Specialized Service Provider Organizations (SSPOs)** to manage:
*   **Intake & Referrals:** Transitioning patients from hospitals/community.
*   **Transition Reviews:** Assessing patient needs (Transition Needs Profile - TNP).
*   **Care Orchestration:** Coordinating care bundles between SPOs and SSPOs.

The platform features a modern, interactive **React frontend** for V2.1 features, leveraging **AI (Gemini)** integrations for tasks like risk assessment, clinical narrative summarization, and capacity forecasting. A dedicated **mobile field app** is also part of the V2.1 experience.

## Architecture & Structure
The codebase is split between the **Legacy V1** system (Retirement Home Placement) and the **New V2.1** system (High-Intensity Care Orchestration). V2.1 features are being developed with an **API-first backend** approach, consumed by the React UI.

-   **`app/Http/Controllers/CC2`**: **V2.1 Logic**. Contains controllers for SPO/SSPO workflows, Transition Reviews, and Care Orchestration.
-   **`app/Http/Controllers/Legacy`**: **V1 Logic**. Contains legacy controllers for Hospitals and Retirement Homes.
-   **`app/Models`**: Shared domain models. Key V2 entities include `ServiceProviderOrganization`, `ServiceAssignment`, `Referral` (Intake), and `Patient`.
-   **`app/Services/GeminiService` (Planned):** Dedicated layer for AI integrations.
-   **`app/Http/Controllers/Api/V2` (Planned):** Dedicated API endpoints for V2.1 frontend.

## Key Terminology (Spec 2.1)
*   **SPO:** Service Provider Organization (Primary care holder).
*   **SSPO:** Specialized Service Provider Organization (Sub-contracted for specific needs like Dementia/Behavioural support).
*   **TNP:** Transition Needs Profile (The assessment artifact).
*   **Transition Review:** The process of triaging a patient.

## Getting Started

### Prerequisites
- PHP 8.1+
- Composer
- Node.js (Latest LTS)

### Installation

1.  **Install Dependencies**
    ```bash
    composer install
    npm install
    ```

2.  **Environment Setup**
    ```bash
    cp .env.example .env
    php artisan key:generate
    # Configure your database settings in .env
    # Add GEMINI_API_KEY and DEFAULT_PATIENT_IMAGE (see config/connected.php)
    ```

3.  **Database Migration**
    ```bash
    php artisan migrate
    ```

4.  **Serve**
    ```bash
    php artisan serve
    # For frontend assets (currently Laravel Mix, but planning Vite migration)
    npm run dev 
    ```

## Code Quality
*   **Linting:** `npm run lint` (ESLint for JS) and `./vendor/bin/phpcs` (PHPCS for PHP).
*   **Testing:** `php artisan test` (Unit/Feature) and `php artisan dusk` (Browser).
