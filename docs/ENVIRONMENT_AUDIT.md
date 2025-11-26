# Environment Audit - November 21, 2025

This document details the current environment configuration and dependencies as found on November 21, 2025.

## 1. PHP & Composer Dependencies (from `composer.json`)

*   **PHP Requirement:** `^8.1`
*   **Laravel Framework Requirement:** `^10.0`
*   **Other key dependencies:**
    *   `akaunting/laravel-apexcharts`: `^1.0`
    *   `guzzlehttp/guzzle`: `^7.5`
    *   `laravel/sanctum`: `^3.2`
    *   `laravel/tinker`: `^2.5`
*   **Dev dependencies:**
    *   `fakerphp/faker`: `^1.9.1`
    *   `laravel/sail`: `^1.0.1`
    *   `mockery/mockery`: `^1.4.4`
    *   `nunomaduro/collision`: `^7.0`
    *   `phpunit/phpunit`: `^10.5`
    *   `spatie/laravel-ignition`: `^2.0`

## 2. Node.js & NPM Dependencies (from `package.json`)

*   **Frontend Build Tool:** `laravel-mix` (`^6.0.49`)
*   **React:** `^19.2.0`
*   **React DOM:** `^19.2.0`
*   **Tailwind CSS:** `^3.4.18`
*   **Other dev dependencies:**
    *   `@babel/preset-react`: `^7.28.5`
    *   `@types/react`: `^19.2.6`
    *   `@types/react-dom`: `^19.2.3`
    *   `autoprefixer`: `^10.4.22`
    *   `axios`: `^1.13.2`
    *   `lodash`: `^4.17.19`
    *   `postcss`: `^8.5.6`
    *   `react-router-dom`: `^7.9.6`

## 3. Actual System Versions (from shell commands)

*   **PHP Version:** `8.4.13`
*   **Composer Version:** `2.8.12`
*   **Laravel Artisan Version:** `10.49.1`
*   **Node.js Version:** `v22.19.0`
*   **NPM Version:** `11.6.2`
