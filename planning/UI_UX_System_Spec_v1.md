# UI/UX System Spec v1

**Version:** 1.0
**Status:** Draft
**Source:** `UI:UX/` Wireframes

---

## 1. Visual Language

### 1.1 Color Palette
The system uses a professional, high-trust medical palette dominated by Teal (Primary) and Indigo (Secondary), with Slate for neutrals.

*   **Primary (Teal)**: Used for branding, primary actions, and success states.
    *   `teal-900` (#0C6370): Brand headers, primary buttons, active navigation.
    *   `teal-600` (#148F9C): Icons, links, sub-headers.
    *   `teal-50` (#F0F9FA): Backgrounds for active items, light accents.
*   **Secondary (Indigo)**: Used for AI features, focus states, and "Smart" actions.
    *   `indigo-600` (#4F46E5): AI buttons, primary accents.
    *   `indigo-50` (#EEF2FF): AI containers, secondary backgrounds.
*   **Status Colors**:
    *   **Critical/Missed**: `rose-500` / `rose-50`
    *   **Warning/Risk**: `amber-500` / `amber-50`
    *   **Success/Stable**: `emerald-500` / `emerald-50`
*   **Neutrals**:
    *   **Background**: `slate-50` (#F8FAFC) - Global app background.
    *   **Surface**: `white` (#FFFFFF) - Cards, Sidebar, Header.
    *   **Text**: `slate-800` (Headings), `slate-600` (Body), `slate-400` (Meta).

### 1.2 Typography
*   **Font Family**: `Inter` (Google Fonts).
*   **Weights**: 300 (Light), 400 (Regular), 500 (Medium), 600 (SemiBold), 700 (Bold).
*   **Sizes**:
    *   `text-xs` (12px): Meta data, tags.
    *   `text-sm` (14px): Body text, table data.
    *   `text-base` (16px): Standard inputs, lead text.
    *   `text-lg` (18px): Card headers.
    *   `text-2xl` (24px): Page titles.

### 1.3 Effects
*   **Shadows**: Subtle, diffuse shadows (`shadow-sm`, `shadow-md`) to create depth on white cards against the slate background.
*   **Rounded Corners**:
    *   `rounded-xl` (12px): Cards, Modals, Containers.
    *   `rounded-lg` (8px): Buttons, Inputs.
    *   `rounded-full`: Badges, Avatars.
*   **Animations**:
    *   `fade-in`: Smooth entry for page content.
    *   `pulse`: For "Live" status or AI processing.
    *   `shimmer`: Loading states.

---

## 2. Layout Primitives

### 2.1 Global Shell (Authenticated)
*   **Sidebar (Left)**: Fixed width (`w-64`), white background, border-right. Contains Logo, Role-based Navigation, User Profile (bottom).
*   **Topbar (Top)**: Sticky, white/glass, border-bottom. Contains Page Title (Context), Breadcrumbs, Global Search, Notifications, User Menu.
*   **Main Content**: `flex-grow`, `bg-slate-50`, `p-6` or `p-8`. Max-width constrained (`max-w-7xl`) for readability on large screens.

### 2.2 Page Layouts
*   **Dashboard Grid**: 3-column grid for KPIs, followed by 2-column split (Main Table + Side Panel).
*   **List View**: Full-width card containing filters (top) and data table.
*   **Detail View**: Header with Actions + Tabbed Interface (Overview, Clinical, Timeline).
*   **Wizard**: Centered layout (`max-w-3xl`), Stepper Header, Form Content, Fixed Bottom Action Bar.

---

## 3. Components & Patterns

### 3.1 Cards & Containers
*   **Standard Card**: White bg, `rounded-xl`, `border border-slate-200`, `shadow-sm`.
*   **KPI Card**: Standard Card + Left Border (Color coded) + Icon Badge.
*   **AI Container**: `bg-indigo-50`, `border-indigo-100`.

### 3.2 Data Presentation
*   **Data Tables**:
    *   Header: `bg-slate-50`, `text-xs uppercase text-slate-500`.
    *   Rows: White bg, `hover:bg-slate-50`, border-bottom.
    *   Cells: Vertical alignment middle.
*   **Status Badges**: Pill shape (`rounded-full`), `px-2 py-0.5`, `text-xs font-bold`.
    *   Example: `<span class="bg-emerald-50 text-emerald-700">Active</span>`

### 3.3 Navigation
*   **Sidebar Links**:
    *   Default: `text-slate-600`, `hover:bg-slate-50`.
    *   Active: `bg-teal-50`, `text-teal-900`, `font-bold`, Left border strip (`teal-900`).
*   **Tabs**:
    *   Underline style: Text with bottom border. Active = `border-teal-900 text-teal-900`.

### 3.4 Feedback & AI
*   **AI Actions**: Buttons styled with `indigo` gradients or borders. Often labeled "Gemini: [Action]".
*   **Loaders**: Spinners or Skeleton screens (shimmer).

---

## 4. Navigation & Information Architecture (IA)

### 4.1 Role: SPO_ADMIN / SPO_COORDINATOR
*   **Dashboard**: Care Operations (KPIs, Staffing).
*   **Intake**:
    *   Referrals (List).
    *   Transition Reviews (TNP Builder).
*   **Care Management**:
    *   Active Patients (List).
    *   Schedules / Visits.
*   **Network**:
    *   Partner Marketplace (SSPO Directory).
*   **Analytics**: Compliance & Reports.

### 4.2 Role: SSPO_ADMIN (Service Provider)
*   **Dashboard**: My Referrals, Capacity.
*   **Marketplace**: Open Opportunities.
*   **My Patients**: Active assignments.

### 4.3 Role: FIELD_STAFF
*   **My Day**: Schedule / Route.
*   **My Patients**: Patient list.
*   **Tasks**: Care plan tasks.

---

## 5. Mobile Considerations
*   **Field App**: Bottom Navigation Bar (Home, Schedule, Patients, Profile).
*   **Responsive Dashboard**: Sidebar collapses to Hamburger menu. Grids stack to single column.
