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

// Request interceptor to ensure CSRF token is fresh
api.interceptors.request.use(
    (config) => {
        // Check for XSRF-TOKEN cookie (set by Sanctum) and use it
        const xsrfToken = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        if (xsrfToken) {
            config.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
        }

        // Also re-check meta tag in case it was refreshed
        const metaToken = document.head.querySelector('meta[name="csrf-token"]');
        if (metaToken && metaToken.content) {
            config.headers['X-CSRF-TOKEN'] = metaToken.content;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor to handle CSRF token mismatch
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // Handle 419 CSRF token mismatch - fetch new token and retry once
        if (error.response?.status === 419 && !originalRequest._retry) {
            originalRequest._retry = true;

            try {
                // Fetch fresh CSRF cookie from Sanctum
                await axios.get('/sanctum/csrf-cookie', { withCredentials: true });

                // Retry the original request
                return api(originalRequest);
            } catch (csrfError) {
                console.error('Failed to refresh CSRF token:', csrfError);
                return Promise.reject(error);
            }
        }

        return Promise.reject(error);
    }
);

export default api;
