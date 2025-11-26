import React from 'react';

const KpiCard = ({
    label,
    value,
    icon,
    status = 'neutral', // neutral, success, warning, critical
    trend,
    trendLabel,
    actionLabel,
    onAction,
    className = ''
}) => {

    const statusConfig = {
        neutral: {
            border: 'border-slate-200',
            text: 'text-slate-400',
            bg: 'bg-slate-50',
            iconColor: 'text-slate-500',
            pulse: false
        },
        success: {
            border: 'border-emerald-200',
            text: 'text-emerald-600',
            bg: 'bg-emerald-50',
            iconColor: 'text-emerald-600',
            pulse: false
        },
        warning: {
            border: 'border-amber-300', // Hover effect handled in CSS usually, but base border here
            text: 'text-amber-500',
            bg: 'bg-amber-50',
            iconColor: 'text-amber-500',
            pulse: false
        },
        critical: {
            border: 'border-rose-500',
            text: 'text-rose-500',
            bg: 'bg-rose-50',
            iconColor: 'text-rose-600',
            pulse: true
        }
    };

    const config = statusConfig[status] || statusConfig.neutral;

    return (
        <div
            onClick={onAction || undefined}
            className={`bg-white p-5 rounded-xl shadow-sm border ${status === 'critical' ? 'border-l-4' : ''} ${config.border} relative overflow-hidden group ${className} ${onAction ? 'cursor-pointer hover:shadow-md transition-shadow' : ''}`}
        >
            <div className="flex justify-between items-start">
                <div>
                    <div className={`${config.text} text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5`}>
                        {config.pulse && (
                            <span className="w-2 h-2 rounded-full bg-rose-500 animate-pulse shadow-[0_0_0_0_rgba(239,68,68,0.7)]"></span>
                        )}
                        {label}
                    </div>
                    <div className={`text-3xl font-bold ${status === 'critical' ? 'text-slate-800' : 'text-slate-800'} mt-1`}>
                        {value}
                    </div>
                </div>
                <div className={`p-2 rounded-lg ${config.bg} ${config.iconColor}`}>
                    {icon}
                </div>
            </div>

            {(trend || actionLabel) && (
                <div className="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
                    {trend && (
                        <span className="text-slate-400">
                            {trend} <span className="text-slate-300 ml-1">{trendLabel}</span>
                        </span>
                    )}

                    {actionLabel && (
                        <button
                            onClick={onAction}
                            className={`font-bold hover:underline flex items-center gap-1 ${config.text}`}
                        >
                            {actionLabel} &rarr;
                        </button>
                    )}
                </div>
            )}
        </div>
    );
};

export default KpiCard;
