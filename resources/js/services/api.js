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
    baseURL: '/',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
});

export default api;
