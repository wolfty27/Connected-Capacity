import React from 'react';

/**
 * InterraiStatusBadge - Displays InterRAI assessment status
 *
 * Status values:
 * - current: Assessment <90 days old (green)
 * - stale: Assessment >90 days old (amber)
 * - missing: No assessment on file (red)
 * - pending_upload: IAR upload pending (gray)
 * - upload_failed: IAR upload failed (red outline)
 */
const InterraiStatusBadge = ({
    status,
    showLabel = true,
    size = 'md',
    daysUntilStale = null,
    className = '',
}) => {
    const statusConfig = {
        current: {
            label: 'Current',
            icon: (
                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                </svg>
            ),
            bgColor: 'bg-emerald-100',
            textColor: 'text-emerald-700',
            borderColor: 'border-emerald-200',
            dotColor: 'bg-emerald-500',
        },
        stale: {
            label: 'Stale',
            icon: (
                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
            ),
            bgColor: 'bg-amber-100',
            textColor: 'text-amber-700',
            borderColor: 'border-amber-200',
            dotColor: 'bg-amber-500',
        },
        missing: {
            label: 'Missing',
            icon: (
                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
            ),
            bgColor: 'bg-rose-100',
            textColor: 'text-rose-700',
            borderColor: 'border-rose-200',
            dotColor: 'bg-rose-500',
        },
        pending_upload: {
            label: 'IAR Pending',
            icon: (
                <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            ),
            bgColor: 'bg-slate-100',
            textColor: 'text-slate-600',
            borderColor: 'border-slate-200',
            dotColor: 'bg-slate-400',
        },
        upload_failed: {
            label: 'Upload Failed',
            icon: (
                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
            ),
            bgColor: 'bg-white',
            textColor: 'text-rose-600',
            borderColor: 'border-rose-400 border-2',
            dotColor: 'bg-rose-500',
        },
    };

    const config = statusConfig[status] || statusConfig.missing;

    const sizeClasses = {
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-1 text-sm',
        lg: 'px-3 py-1.5 text-base',
    };

    // Build subtitle for days info
    let subtitle = null;
    if (status === 'current' && daysUntilStale !== null) {
        subtitle = `${daysUntilStale}d until stale`;
    } else if (status === 'stale' && daysUntilStale !== null) {
        subtitle = `${Math.abs(daysUntilStale)}d overdue`;
    }

    return (
        <span
            className={`
                inline-flex items-center gap-1.5 rounded-full font-medium
                ${config.bgColor} ${config.textColor} ${config.borderColor}
                ${sizeClasses[size]}
                border
                ${className}
            `}
        >
            {config.icon}
            {showLabel && (
                <span className="flex flex-col leading-tight">
                    <span>{config.label}</span>
                    {subtitle && size !== 'sm' && (
                        <span className="text-xs opacity-75">{subtitle}</span>
                    )}
                </span>
            )}
        </span>
    );
};

/**
 * Compact dot indicator for use in tables
 */
export const InterraiStatusDot = ({ status, className = '' }) => {
    const dotColors = {
        current: 'bg-emerald-500',
        stale: 'bg-amber-500',
        missing: 'bg-rose-500',
        pending_upload: 'bg-slate-400',
        upload_failed: 'bg-rose-500 ring-2 ring-rose-200',
    };

    return (
        <span
            className={`inline-block w-2.5 h-2.5 rounded-full ${dotColors[status] || dotColors.missing} ${className}`}
            title={status?.replace('_', ' ')}
        />
    );
};

export default InterraiStatusBadge;
