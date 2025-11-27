# Performance Optimizations and Bug Fixes (November 2025)

This document details the performance optimizations, bug fixes, and code improvements applied to the Connected Capacity application.

## 1. Service Loading Fix in Care Bundle Wizard

**Issue:**
Clinical services were failing to load in Step 2 of the Care Bundle Wizard, despite being present in the database.

**Root Cause:**
A naming conflict existed in the `ServiceType` model. The model had both a string column named `category` and an Eloquent relationship method named `category()`. When accessing `$serviceType->category`, Laravel returned the value of the column (which was often null or a simple string) instead of the relationship object, causing downstream logic to fail when trying to access properties like `->code` on a non-object.

**Resolution:**
- **Renamed Relationship:** The `category()` relationship in `App\Models\ServiceType` was renamed to `serviceCategory()`.
- **Updated References:** All references to the relationship were updated in:
    - `App\Services\CareBundleBuilderService`
    - `App\Http\Controllers\Api\V2\BundleTemplateController`
    - `App\Http\Controllers\Api\V2\ServiceTypeController`
- **Restored Methods:** Restored accidentally deleted helper methods in `CareBundleBuilderService.php` (`buildPatientContext`, `hasCognitiveNeeds`, etc.) which were causing API failures.

**Outcome:**
Services now correctly eager load their category data and populate the Care Bundle Wizard interface.

## 2. Performance Optimizations

### N+1 Query Resolution
**Location:** `App\Http\Controllers\Api\PatientController`
**Issue:** The `index` method was iterating through patients and individually querying `Hospital` and `User` models for each record, leading to N+1 query performance degradation.
**Fix:** Implemented eager loading using `with(['hospital.user'])` and refactored the data mapping logic to use the loaded relationships.

### Lazy Loading Prevention
**Location:** `App\Services\CareBundleBuilderService`
**Issue:** The service was lazy-loading `latestInterraiAssessment` inside loops when processing bundles.
**Fix:** Added `latestInterraiAssessment` to the eager load array in `getAvailableBundles` and `getBundleForPatient` methods.

### Metadata Caching
**Location:** `App\Services\MetadataEngine`
**Issue:** Metadata definitions were being fetched from the database on every request.
**Fix:** Implemented persistent caching using `Cache::remember` to store object definitions for 60 minutes, significantly reducing database load for metadata-heavy operations.

### Bulk Data Fetching
**Location:** `App\Http\Controllers\Api\V2\CarePlanController`
**Issue:** The `store` method was querying `ServiceType` individually inside a loop.
**Fix:** Refactored to use a single `whereIn` query to fetch all required `ServiceType` records at once.

## 3. Legacy Code Maintenance

**Issue:**
Several legacy controllers contained duplicate `use` statements, causing IDE errors and potential runtime conflicts.

**Fix:**
Removed duplicate `use App\Models\Hospital;` statements from:
- `App\Http\Controllers\Legacy\BookingsController.php`
- `App\Http\Controllers\Legacy\DashboardController.php`
- `App\Http\Controllers\Legacy\PatientsController.php`
