# Connected Capacity V2.1 Design System

This document outlines the core UI components and patterns established during the V2.1 migration. All new React development should adhere to these standards.

## 1. Core UI Components

Located in `resources/js/components/UI/`.

### Layout & Containers

*   **`AppLayout`** (`resources/js/components/Layout/AppLayout.jsx`)
    *   The main shell for authenticated pages.
    *   Includes `GlobalNavBar` and `Sidebar`.
    *   Renders page content via `<Outlet />`.

*   **`Section`**
    *   **Usage:** Use to divide a page into major logical areas (e.g., "Patient Info", "AI Analysis").
    *   **Props:** `title` (string), `description` (string).
    *   **Example:** `<Section title="Details">...</Section>`

*   **`Card`**
    *   **Usage:** Use to group related information within a Section.
    *   **Props:** `title` (string).
    *   **Example:** `<Card title="Clinical Flags">...</Card>`

### Interaction

*   **`Button`**
    *   **Usage:** Standard action button.
    *   **Props:** `variant` ('primary', 'secondary', 'danger', 'link'), `onClick` (func), `disabled` (bool).
    *   **Example:** `<Button variant="primary" onClick={save}>Save</Button>`

*   **`Input` / `Select` / `Checkbox`**
    *   Standard form controls with consistent Tailwind styling (`bg-gray-50`, `rounded-lg`, etc.).

### Feedback & Data

*   **`Spinner`**
    *   **Usage:** Display during async data fetching.
    *   **Pattern:** `if (loading) return <Spinner />;`

*   **`DataTable`**
    *   **Usage:** (Placeholder) For displaying tabular data. Currently a simple stub, intended to be replaced by a robust table library.

## 2. Development Patterns

### Data Fetching
*   **Hook:** Use `useEffect` to fetch data on component mount.
*   **State:** Manage `data` and `loading` state locally.
*   **Endpoint:** Use `axios` to call API endpoints (e.g., `/api/patients/{id}`).

### Error Handling
*   **API Errors:** Catch errors in `try/catch` blocks.
*   **Display:** For now, use `console.error` or simple alerts. Future iteration should introduce a `Toast` notification system.

### Authentication
*   **Context:** Access user user via `useAuth()` hook.
*   **Protection:** Wrap protected routes in `ProtectedRoute` and specific role-based routes in `RoleRoute`.
