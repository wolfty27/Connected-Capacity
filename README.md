# Connected Capacity (V2.1) - Laravel 11 + React SPA

## Project Overview
Connected Capacity 2.1 is a **Service Orchestration Platform** designed to support the **Bundled High Intensity Home Care – LTC** model for Ontario Health atHome.

It serves as the operational engine for **Service Provider Organizations (SPOs)** and **Specialized Service Provider Organizations (SSPOs)** to manage:
*   **Intake & Referrals:** Transitioning patients from hospitals/community.
*   **Transition Reviews:** Assessing patient needs (Transition Needs Profile - TNP).
*   **Care Orchestration:** Coordinating care bundles between SPOs and SSPOs.

## Technology Stack
The platform has been modernized to a robust **Laravel 11** backend serving a **React Single Page Application (SPA)** frontend.

*   **Backend:** Laravel 11 (PHP 8.2+)
*   **Frontend:** React 18+, Vite, Tailwind CSS
*   **API:** RESTful API (v2), authenticated via Laravel Sanctum
*   **AI Integration:** Google Gemini (for risk assessment & summarization)

## Architecture & Structure
The codebase has moved away from legacy "split-brain" Blade views to a unified React SPA.

-   **`resources/js`**: Contains the entire React SPA source code.
    -   `components/`: Reusable UI components (Button, Card, Modal, etc.).
    -   `pages/`: Page-level components (TnpReviewListPage, CareDashboardPage).
    -   `contexts/`: React contexts (AuthContext).
    -   `app.jsx`: Main entry point and router configuration.
-   **`app/Http/Controllers/Api/V2`**: **V2.1 API Logic**. Serves data to the React frontend.
-   **`app/Models`**: Shared domain models. Key V2 entities include `TransitionNeedsProfile`, `CareAssignment`, `Visit`, `Task`.
-   **`app/Services`**: Business logic layer (`TnpService`, `CareOpsService`, `GeminiService`).
-   **`routes/api.php`**: Defines all API endpoints used by the SPA and mobile apps.

## Key Terminology (Spec 2.1)
*   **SPO:** Service Provider Organization (Primary care holder).
*   **SSPO:** Specialized Service Provider Organization (Sub-contracted for specific needs like Dementia/Behavioural support).
*   **TNP:** Transition Needs Profile (The assessment artifact).
*   **Care Ops:** Managing assignments, visits, and tasks for field staff.

## Getting Started

### Prerequisites
- PHP 8.2+
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
    php artisan storage:link
    # Configure your database settings in .env
    # Add GEMINI_API_KEY and DEFAULT_PATIENT_IMAGE (see config/connected.php)
    ```

3.  **Database Migration**
    ```bash
    php artisan migrate
    ```

4.  **Serve Application**
    ```bash
    # Backend
    php artisan serve

    # Frontend (Vite Dev Server)
    npm run dev
    ```
    Access the application at `http://127.0.0.1:8000`.

## Testing
*   **PHPUnit:** `php artisan test` (Backend Unit & Feature tests).
*   **Playwright:** `npx playwright test` (E2E tests - *Note: Currently skipped in CI pending full environment setup*).

## Security
*   **RBAC:** Role-Based Access Control is enforced via `App\Policies` and Middleware.
*   **Roles:** `MASTER`, `ADMIN`, `SPO_ADMIN`, `FIELD_STAFF`, `HOSPITAL`, `RETIREMENT_HOME` (See `App\Models\User` constants).

## CC 2.1 Long-Running Harness

This project uses a long-running engineering harness that allows Claude Code to
contribute safely and incrementally over many sessions.

### How to use it:

1. **Before each Claude Code session:**
   - Ask Claude to read `harness/feature_list.json` and read `harness/progress.md`.

2. **During each session:**
   - Claude picks 1–2 features with status `"not_started"` or `"in_progress"`.
   - Implements them fully:
     - backend models/services
     - front-end components
     - tests
     - database seeding adjustments
   - Ensures no partial or broken state is left behind.

3. **After implementing:**
   - Claude updates:
     - `feature_list.json` (status)
     - `progress.md` (summary of what changed)
     - `session_log.md` (session notes)
   - Runs tests and ensures build passes.
   - Commits cleanly.

This harness allows CC2.1 to be built steadily despite LLM statelessness.