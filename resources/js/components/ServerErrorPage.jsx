import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';

/**
 * ServerErrorPage - Display for 500 and other server errors
 *
 * FE-005: User-friendly error page for server-side failures
 */
const ServerErrorPage = ({ errorCode = 500, message = null }) => {
    const navigate = useNavigate();
    const location = useLocation();

    // Get error details from location state if available
    const errorDetails = location.state?.error || {};
    const displayCode = errorDetails.code || errorCode;
    const displayMessage = errorDetails.message || message;

    const errorMessages = {
        500: {
            title: 'Server Error',
            description: 'Something went wrong on our end. Please try again in a few minutes.',
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            ),
        },
        502: {
            title: 'Bad Gateway',
            description: 'The server received an invalid response. Please try again shortly.',
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M13 10V3L4 14h7v7l9-11h-7z"
                />
            ),
        },
        503: {
            title: 'Service Unavailable',
            description: 'The service is temporarily unavailable. We\'re working to restore it.',
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                />
            ),
        },
        504: {
            title: 'Gateway Timeout',
            description: 'The request took too long to process. Please try again.',
            icon: (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            ),
        },
    };

    const errorInfo = errorMessages[displayCode] || errorMessages[500];

    const handleRetry = () => {
        // Go back to the previous page and retry
        if (window.history.length > 2) {
            navigate(-1);
        } else {
            navigate('/dashboard');
        }
    };

    const handleGoHome = () => {
        navigate('/');
    };

    return (
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
            <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-8 text-center">
                {/* Error Icon */}
                <div className="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg
                        className="w-8 h-8 text-amber-600"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        {errorInfo.icon}
                    </svg>
                </div>

                {/* Error Code */}
                <div className="text-6xl font-bold text-slate-300 mb-2">{displayCode}</div>

                {/* Error Message */}
                <h1 className="text-xl font-bold text-slate-900 mb-2">{errorInfo.title}</h1>
                <p className="text-slate-600 mb-6">
                    {displayMessage || errorInfo.description}
                </p>

                {/* Action Buttons */}
                <div className="flex gap-3">
                    <button
                        onClick={handleRetry}
                        className="flex-1 px-4 py-2 bg-teal-600 text-white rounded-lg font-medium hover:bg-teal-700 transition-colors"
                    >
                        Try Again
                    </button>
                    <button
                        onClick={handleGoHome}
                        className="flex-1 px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-300 transition-colors"
                    >
                        Go Home
                    </button>
                </div>

                {/* Additional Help */}
                <div className="mt-8 pt-6 border-t border-slate-100">
                    <p className="text-sm text-slate-500 mb-3">Need help?</p>
                    <div className="flex justify-center gap-4 text-sm">
                        <a
                            href="/status"
                            className="text-teal-600 hover:text-teal-700 font-medium"
                        >
                            System Status
                        </a>
                        <span className="text-slate-300">|</span>
                        <a
                            href="mailto:support@connectedcapacity.ca"
                            className="text-teal-600 hover:text-teal-700 font-medium"
                        >
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ServerErrorPage;
