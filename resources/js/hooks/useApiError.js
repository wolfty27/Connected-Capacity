import { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';

/**
 * useApiError - Hook for handling API errors consistently
 *
 * FE-005: Provides consistent error handling across components
 * with automatic navigation for server errors.
 */
const useApiError = () => {
    const navigate = useNavigate();
    const [error, setError] = useState(null);
    const [isError, setIsError] = useState(false);

    /**
     * Handle an API error response
     * @param {Error|object} err - The error object from axios or fetch
     * @param {object} options - Options for error handling
     * @param {boolean} options.silent - Don't show any UI feedback
     * @param {boolean} options.redirect - Redirect to error page for server errors
     * @param {function} options.onError - Custom error handler callback
     */
    const handleError = useCallback((err, options = {}) => {
        const { silent = false, redirect = true, onError } = options;

        // Extract error details
        const status = err?.response?.status || err?.status || 500;
        const message = err?.response?.data?.message || err?.message || 'An unexpected error occurred';
        const errors = err?.response?.data?.errors || {};

        const errorData = {
            status,
            message,
            errors,
            timestamp: new Date().toISOString(),
        };

        setError(errorData);
        setIsError(true);

        // Call custom error handler if provided
        if (onError) {
            onError(errorData);
            return errorData;
        }

        // Handle specific status codes
        if (!silent) {
            switch (status) {
                case 401:
                    // Unauthorized - redirect to login
                    window.location.href = '/login';
                    break;

                case 403:
                    // Forbidden - show access denied
                    if (redirect) {
                        navigate('/access-denied', { state: { error: errorData } });
                    }
                    break;

                case 404:
                    // Not found - show 404 page
                    if (redirect) {
                        navigate('/not-found', { state: { error: errorData } });
                    }
                    break;

                case 419:
                    // CSRF token mismatch - reload page
                    window.location.reload();
                    break;

                case 422:
                    // Validation error - don't redirect, let component handle
                    break;

                case 429:
                    // Too many requests - show rate limit message
                    console.warn('Rate limited:', message);
                    break;

                case 500:
                case 502:
                case 503:
                case 504:
                    // Server errors - redirect to error page
                    if (redirect) {
                        navigate('/server-error', {
                            state: { error: { code: status, message } }
                        });
                    }
                    break;

                default:
                    console.error('Unhandled error:', errorData);
            }
        }

        return errorData;
    }, [navigate]);

    /**
     * Clear the current error state
     */
    const clearError = useCallback(() => {
        setError(null);
        setIsError(false);
    }, []);

    /**
     * Wrap an async function with error handling
     * @param {function} asyncFn - The async function to wrap
     * @param {object} options - Options for error handling
     */
    const withErrorHandling = useCallback((asyncFn, options = {}) => {
        return async (...args) => {
            try {
                clearError();
                return await asyncFn(...args);
            } catch (err) {
                handleError(err, options);
                throw err;
            }
        };
    }, [handleError, clearError]);

    return {
        error,
        isError,
        handleError,
        clearError,
        withErrorHandling,
    };
};

export default useApiError;
