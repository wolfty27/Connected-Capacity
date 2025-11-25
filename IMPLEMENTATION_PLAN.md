# Implementation Plan - UI/UX Retrofit

**Goal**: Transform the current generic React admin dashboard into the premium "Connected Capacity" medical interface defined in `UI_UX_System_Spec_v1.md`.

## User Review Required
> [!IMPORTANT]
> This retrofit involves a complete visual overhaul (Dark Mode -> Light Mode).
> All current "Dark Mode" styles will be removed.
> The navigation structure will be reorganized by Role (SPO vs SSPO).

## Proposed Changes

### Phase 1: Foundation & Theming
#### [MODIFY] [tailwind.config.js](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/tailwind.config.js)
- Define `teal` (Primary) and `indigo` (Secondary) color palettes.
- Add `Inter` font family.
- Add custom utilities (`.glass-nav`, `.card-shadow`).

#### [MODIFY] [app.css](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/css/app.css)
- Set global body background to `slate-50`.
- Remove legacy dark mode overrides.

#### [MODIFY] [DashboardLayout.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/layouts/DashboardLayout.jsx)
- Switch sidebar to White background.
- Update main content area to Light Gray.

### Phase 2: Core Components
#### [MODIFY] [Card.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/components/UI/Card.jsx)
- Update styling to `bg-white`, `rounded-xl`, `shadow-sm`.
- Add support for "KPI" variants (colored borders).

#### [MODIFY] [DataTable.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/components/UI/DataTable.jsx)
- Style headers with uppercase/tracking.
- Add support for Status Badges and Avatars in cells.

#### [NEW] [Wizard.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/components/UI/Wizard.jsx)
- Create a reusable Stepper component.
- Implement "Next/Back" navigation logic.

### Phase 3: Navigation & Shell
#### [MODIFY] [Sidebar.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/components/Navigation/Sidebar.jsx)
- Implement new "Teal" active state.
- Group links by Role (Care Ops, Intake, Network).

#### [NEW] [TopBar.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/components/Navigation/TopBar.jsx)
- Create sticky top navigation.
- Add Breadcrumbs and User Profile dropdown.

### Phase 4: Feature Implementation
#### [NEW] [CareDashboardPage.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/pages/CareOps/CareDashboardPage.jsx)
- Rebuild using new KPI Cards and Partner Table.
- Add "Gemini Forecast" placeholder.

#### [NEW] [CreateReferral.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/pages/Referrals/CreateReferral.jsx)
- Implement the 3-step Referral Wizard.

#### [NEW] [ReviewList.jsx](file:///Users/williamegi/Documents/Connected Capacity/Technical:Code/connected-backup/resources/js/pages/Tnp/ReviewList.jsx)
- Implement the Transition Review list with Acuity scores.

## Verification Plan

### Automated Tests
- Run `npm run dev` to ensure build passes.
- Verify no console errors on navigation.

### Manual Verification
1.  **Theme Check**: Verify app is Light Mode with Teal branding.
2.  **Navigation Check**: Verify Sidebar links change based on logged-in Role.
3.  **Component Check**:
    -   Cards have rounded corners and shadows.
    -   Tables have clean headers and aligned columns.
4.  **Flow Check**:
    -   Navigate to "Care Dashboard" -> Verify Widgets.
    -   Navigate to "Intake" -> Verify Wizard steps.
