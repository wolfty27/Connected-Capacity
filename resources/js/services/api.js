import axios from 'axios';

/**
 * Configured axios instance for API calls
 *
 * This ensures all API calls include:
 * - withCredentials for session-based auth
 * - CSRF token handling
 * - JSON headers
 */
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
});

// Add CSRF token from meta tag if available
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    api.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

export default api;
