import React, { Component } from 'react';

/**
 * ErrorBoundary - Global error boundary for React application
 *
 * FE-005: Catches JavaScript errors anywhere in the child component tree,
 * logs those errors, and displays a user-friendly fallback UI.
 */
class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
            errorInfo: null,
            eventId: null,
        };
    }

    static getDerivedStateFromError(error) {
        // Update state so the next render will show the fallback UI
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        // Log error details
        this.setState({ errorInfo });

        // Log to console in development
        if (process.env.NODE_ENV === 'development') {
            console.error('ErrorBoundary caught an error:', error, errorInfo);
        }

        // Generate a unique event ID for support reference
        const eventId = `ERR-${Date.now().toString(36).toUpperCase()}`;
        this.setState({ eventId });

        // Report to backend error logging endpoint
        this.reportError(error, errorInfo, eventId);
    }

    reportError = async (error, errorInfo, eventId) => {
        try {
            await fetch('/api/client-errors', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    message: error.message,
                    stack: error.stack,
                    component_stack: errorInfo?.componentStack,
                    url: window.location.href,
                    user_agent: navigator.userAgent,
                    timestamp: new Date().toISOString(),
                }),
            });
        } catch (reportError) {
            // Silently fail - don't cause additional errors
            console.error('Failed to report error:', reportError);
        }
    };

    handleReload = () => {
        window.location.reload();
    };

    handleGoHome = () => {
        window.location.href = '/';
    };

    render() {
        if (this.state.hasError) {
            // Custom fallback UI if provided
            if (this.props.fallback) {
                return this.props.fallback;
            }

            // Default error UI
            return (
                <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
                    <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-8 text-center">
                        {/* Error Icon */}
                        <div className="w-16 h-16 bg-rose-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg
                                className="w-8 h-8 text-rose-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>

                        {/* Error Message */}
                        <h1 className="text-2xl font-bold text-slate-900 mb-2">
                            Something went wrong
                        </h1>
                        <p className="text-slate-600 mb-6">
                            We're sorry, but something unexpected happened. Our team has been notified and is working to fix the issue.
                        </p>

                        {/* Event ID for Support */}
                        {this.state.eventId && (
                            <div className="bg-slate-100 rounded-lg p-3 mb-6">
                                <p className="text-xs text-slate-500 mb-1">Reference ID</p>
                                <code className="text-sm font-mono text-slate-700">
                                    {this.state.eventId}
                                </code>
                            </div>
                        )}

                        {/* Development Error Details */}
                        {process.env.NODE_ENV === 'development' && this.state.error && (
                            <details className="text-left mb-6 bg-slate-900 rounded-lg p-4 overflow-auto max-h-48">
                                <summary className="text-rose-400 cursor-pointer font-medium text-sm">
                                    Error Details (Dev Only)
                                </summary>
                                <pre className="mt-2 text-xs text-slate-300 whitespace-pre-wrap">
                                    {this.state.error.toString()}
                                    {this.state.errorInfo?.componentStack}
                                </pre>
                            </details>
                        )}

                        {/* Action Buttons */}
                        <div className="flex gap-3">
                            <button
                                onClick={this.handleReload}
                                className="flex-1 px-4 py-2 bg-teal-600 text-white rounded-lg font-medium hover:bg-teal-700 transition-colors"
                            >
                                Try Again
                            </button>
                            <button
                                onClick={this.handleGoHome}
                                className="flex-1 px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-300 transition-colors"
                            >
                                Go Home
                            </button>
                        </div>

                        {/* Support Contact */}
                        <p className="text-xs text-slate-400 mt-6">
                            If this problem persists, please contact support with the reference ID above.
                        </p>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
