# Current Application Functionality Summary

**Purpose:** This document describes the functional capabilities, user flows, and interactive elements of the `Connected Capacity` React application.
**Audience:** External AI / Design Analysis.

---

## 1. Authentication Flow (`Login.jsx`)
*   **Entry Point**: Users land on a dedicated Login page (`/login`).
*   **Mechanism**: Standard Email/Password authentication.
*   **Security**: Uses Laravel Sanctum (CSRF cookie protection).
*   **Feedback**: Displays error messages (red box) for invalid credentials.
*   **Success**: Redirects to the main dashboard (`/`) upon successful login.

## 2. Core Dashboards

### 2.1 Care Operations Dashboard (`CareDashboardPage.jsx`)
*   **Role**: Primary landing page for SPO/SSPO Admins.
*   **Key Metrics (Widgets)**:
    1.  **Patients**: Total count of active patients (Blue).
    2.  **Appointments**: Count of scheduled visits (Green).
    3.  **Offers/Placements**: Count of active placement offers (Purple).
*   **Data Source**: Fetches real-time data from `/api/v2/dashboard`.
*   **Interactivity**: Currently read-only widgets. "Recent Activity" is a placeholder.

## 3. Patient Management

### 3.1 Patient List (`PatientsList.jsx`)
*   **View**: Grid layout of Patient Cards.
*   **Card Content**:
    *   **Identity**: Name avatar (initials) + Full Name.
    *   **Status Badge**: Color-coded (Green=Available, Yellow=Other).
    *   **Demographics**: Gender, Hospital Origin.
*   **Actions**:
    *   **View Details**: Link to `/patients/:id` for full record.
    *   **Add Patient**: Button exists (currently logs to console).
*   **Empty State**: Displays a "No patients found" message if the list is empty.

## 4. Navigation & Routing Structure (`App.jsx`)
The app uses a hierarchical routing system protected by guards:

*   **Public**: Login.
*   **Protected (Authenticated)**:
    *   **Layout**: Sidebar + Top Bar wrapper.
    *   **Redirects**: Root (`/`) redirects to `/dashboard`.
*   **Role-Based Access**:
    *   **Admin/SPO**: Access to Care Dashboard, Patient Lists, Org Profile.
    *   **Field Staff**: Restricted access to "My Worklist" (`/worklist`).

## 5. Key Interactive Elements
*   **Buttons**: Standard clickable actions (e.g., "+ Add Patient").
*   **Links**: Navigation between List -> Detail views.
*   **Loaders**: Spinners displayed while fetching API data.
*   **Error States**: Inline error messages for failed API calls.

## 6. Functional Gaps (vs. Intended UX)
*   **Missing Workflows**:
    *   **Referral Wizard**: No interface to create new referrals.
    *   **TNP Builder**: No "Transition Needs Profile" creation flow.
    *   **Scheduling**: No calendar or drag-and-drop scheduling board.
*   **Limited Interactivity**: Most pages are currently "Read Only" lists or dashboards.
