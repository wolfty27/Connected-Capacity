# Blade View & Route Inventory - November 21, 2025

This document provides a comprehensive inventory of all Blade views found in the codebase and their usage within Routes and Controllers.

## 1. Summary

*   **Total Blade Files Found:** 53
*   **Broken/Missing References Found:** 3 (Views referenced in code but file not found)

## 2. Inventory

| File Path | View Name | Referenced In (Controller/Route) | Status |
| :--- | :--- | :--- | :--- |
| `resources/views/app.blade.php` | `app` | `routes/web.php` | **SPA Bootstrap** |
| `resources/views/welcome.blade.php` | `welcome` | `routes/web.php` | Legacy Landing |
| `resources/views/login.blade.php` | `login` | `UserController.php` | **Auth** |
| `resources/views/demo.blade.php` | `demo` | *(No direct view() reference found)* | Unknown |
| `resources/views/layouts/app.blade.php` | `layouts.app` | *(Used via @extends)* | Layout |
| `resources/views/patients/read.blade.php` | `patients.read` | `Legacy/PatientsController.php` | Legacy List |
| `resources/views/patients/create.blade.php` | `patients.create` | `Legacy/PatientsController.php` | Legacy Form |
| `resources/views/patients/patient-assesment-detail-view.blade.php` | `patients.patient-assesment-detail-view` | `Legacy/PatientsController.php` | Legacy TNP |
| `resources/views/patients/confirm-patient.blade.php` | `patients.confirm-patient` | `Legacy/PatientsController.php` | Legacy Action |
| `resources/views/patients/edit.blade.php` | `patients.edit` | `Legacy/PatientsController.php` | Legacy Form |
| `resources/views/patients/assessment-form.blade.php` | `patients.assessment-form` | `Legacy/PatientsController.php` | Legacy TNP |
| `resources/views/patients/placed-patients.blade.php` | `patients.placed-patients` | `Legacy/PatientsController.php` | Legacy List |
| `resources/views/patients/view.blade.php` | `patients.view` | *(No direct view() reference found)* | Legacy Detail |
| `resources/views/profiles/admin.blade.php` | `profiles.admin` | `Legacy/MyProfileController.php` | Legacy Profile |
| `resources/views/profiles/hospital.blade.php` | `profiles.hospital` | `Legacy/MyProfileController.php` | Legacy Profile |
| `resources/views/profiles/retirement_home.blade.php` | `profiles.retirement_home` | `Legacy/MyProfileController.php` | Legacy Profile |
| `resources/views/profiles/change_password.blade.php` | `profiles.change_password` | `Legacy/MyProfileController.php` | Legacy Auth |
| `resources/views/bookings/hospital.blade.php` | `bookings.hospital` | `Legacy/BookingsController.php` | Legacy Booking |
| `resources/views/bookings/retirement_home.blade.php` | `bookings.retirement_home` | `Legacy/BookingsController.php` | Legacy Booking |
| `resources/views/bookings/admin.blade.php` | `bookings.admin` | `Legacy/BookingsController.php` | Legacy Booking |
| `resources/views/bookings/in_person_assessment.blade.php` | `bookings.in_person_assessment` | `Legacy/BookingsController.php` | Legacy Assessment |
| `resources/views/bookings/view.blade.php` | `bookings.view` | `Legacy/BookingsController.php` | Legacy Detail |
| `resources/views/bookings/hospital_appointments.blade.php` | `bookings.hospital_appointments` | `Legacy/BookingsController.php` | Legacy List |
| `resources/views/hospitals/dashboard.blade.php` | `hospitals.dashboard` | `Legacy/DashboardController.php` | **Legacy Dashboard** |
| `resources/views/hospitals/read.blade.php` | `hospitals.read` | `Legacy/HospitalsController.php` | Legacy List |
| `resources/views/hospitals/view.blade.php` | `hospitals.view` | `Legacy/HospitalsController.php` | Legacy Detail |
| `resources/views/hospitals/edit.blade.php` | `hospitals.edit` | `Legacy/HospitalsController.php` | Legacy Form |
| `resources/views/hospitals/create.blade.php` | `hospitals.create` | *(No direct view() reference found)* | Legacy Form |
| `resources/views/hospitals/calendly_ui.blade.php` | `hospitals.calendly_ui` | `Legacy/CalendlyController.php` | Legacy Feature |
| `resources/views/retirement_homes/dashboard.blade.php` | `retirement_homes.dashboard` | `Legacy/DashboardController.php` | **Legacy Dashboard** |
| `resources/views/retirement_homes/read.blade.php` | `retirement_homes.read` | `Legacy/RetirementHomeController.php` | Legacy List |
| `resources/views/retirement_homes/create.blade.php` | `retirement_homes.create` | `Legacy/RetirementHomeController.php` | Legacy Form |
| `resources/views/retirement_homes/edit.blade.php` | `retirement_homes.edit` | `Legacy/RetirementHomeController.php` | Legacy Form |
| `resources/views/retirement_homes/view.blade.php` | `retirement_homes.view` | `Legacy/RetirementHomeController.php` | Legacy Detail |
| `resources/views/retirement_homes/my_patients.blade.php` | `retirement_homes.my_patients` | `Legacy/RetirementHomeController.php` | Legacy List |
| `resources/views/retirement_homes/files.blade.php` | `retirement_homes.files` | *(No direct view() reference found)* | Legacy Partial? |
| `resources/views/retirement_homes/gallery.blade.php` | `retirement_homes.gallery` | *(No direct view() reference found)* | Legacy Partial? |
| `resources/views/components/*.blade.php` | `components.*` | *(Included via @include or x-components)* | Components |
| `resources/views/datatables/*.blade.php` | `datatables.*` | *(Used by DataTables libraries)* | Library Assets |

## 3. Missing or Broken View References

These views are referenced in the controllers but were **not found** in the `resources/views` directory. This indicates missing files, dead code, or views loaded from a package.

| Referenced View Name | Found In Controller | Note |
| :--- | :--- | :--- |
| `dashboard.dashboard` | `Legacy/DashboardController.php` | **Likely Missing/Broken** |
| `cc2.landing` | `CC2/LandingController.php` | **Missing V2 Landing** |
| `cc2.organizations.profile` | `CC2/Organizations/ProfileController.php` | **Missing V2 Profile** |

## 4. Components & Partials (Unused Check)

The following files were found but no direct `view('name')` usage was detected. They are likely `@include` partials or Blade Components.

*   `bookings/in_person_assessment_style.blade.php`
*   `components/calendly_script.blade.php`
*   `components/code_highlight_js.blade.php`
*   `components/google_map_script.blade.php`
*   `components/head.blade.php`
*   `components/input_mask_js.blade.php`
*   `components/multiple_rows_script.blade.php`
*   `components/navbar.blade.php`
*   `components/progress_bar_script.blade.php`
*   `datatables/additional_head.blade.php`
*   `datatables/css.blade.php`
*   `datatables/scripts.blade.php`
*   `hospitals/dashboard_scripts.blade.php`
*   `retirement_homes/edit_script.blade.php`
*   `retirement_homes/edit_style.blade.php`
*   `retirement_homes/gallery_for_admin.blade.php`
*   `retirement_homes/gallery_for_hospital.blade.php`
