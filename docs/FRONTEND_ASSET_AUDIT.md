# Frontend Asset Audit - November 21, 2025

This document audits the current frontend build pipeline, specifically focusing on `laravel-mix` usage and legacy library dependencies.

## 1. Webpack Mix Configuration (`webpack.mix.js`)

The project currently uses `laravel-mix` to compile React components and PostCSS.

*   **Entry Points:**
    *   JS: `resources/js/app.jsx` -> `public/js/app.js` (React enabled)
    *   CSS: `resources/css/app.css` -> `public/css/app.css` (PostCSS enabled)

## 2. `mix()` Helper Usage in Blade

The `mix()` helper is used exclusively in the main SPA shell.

*   `resources/views/app.blade.php`:
    *   `<link href="{{ mix('css/app.css') }}" rel="stylesheet">`
    *   `<script src="{{ mix('js/app.js') }}"></script>`

**Note:** No other Blade views appear to use `mix()`, suggesting that legacy pages likely use hardcoded paths or do not rely on the Mix pipeline (potentially using CDN links or raw asset paths if they exist).

## 3. Frontend Dependencies & Entry Analysis

### `resources/js/app.jsx` (Main Entry)
*   Imports `./bootstrap`
*   Imports `React` and `createRoot` from `react-dom/client`
*   Mounts `<App />` to `#app` container.

### `resources/js/bootstrap.js`
*   **Lodash:** `window._ = require('lodash');`
*   **Axios:** `window.axios = require('axios');` (Configured for CSRF & XHR)
*   **Pusher/Echo:** Currently commented out.

### Legacy Libraries
*   **Bootstrap/jQuery:** NOT explicitly imported in the main `app.jsx` or `bootstrap.js`.
*   *Investigation Note:* If legacy Blade views use Bootstrap or jQuery, they are likely included via CDN links in layout files (e.g., `resources/views/layouts/app.blade.php`) or `resources/views/components/head.blade.php`. The `BLADE_INVENTORY.md` showed a `resources/views/layouts/app.blade.php` which should be checked during the legacy removal phase.

## 4. Migration Strategy to Vite

1.  **Delete** `webpack.mix.js`.
2.  **Update** `package.json` to remove `laravel-mix` and add `vite`, `laravel-vite-plugin`.
3.  **Create** `vite.config.js` mirroring the Mix setup:
    *   Input: `['resources/js/app.jsx', 'resources/css/app.css']`
    *   Plugins: `react()`.
4.  **Update** `resources/views/app.blade.php`:
    *   Replace `mix()` calls with `@vite(['resources/js/app.jsx', 'resources/css/app.css'])`.
