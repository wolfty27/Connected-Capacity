import React from 'react';

const Card = ({ title, subtitle, children, className = '', variant = 'standard', action, actions, kpiColor, ...props }) => {
    const baseClasses = "bg-white border rounded-xl transition-all duration-200";

    const variants = {
        standard: "border-slate-200 shadow-sm",
        kpi: "border-slate-200 shadow-sm border-l-4",
        interactive: "border-slate-200 shadow-sm hover:shadow-md cursor-pointer hover:border-teal-200",
        flat: "border-transparent bg-slate-50"
    };

    // Handle KPI color coding if passed via props, otherwise default to teal
    const kpiColorClass = kpiColor ? `border-l-${kpiColor}-500` : 'border-l-teal-500';

    const finalClasses = `
        ${baseClasses}
        ${variants[variant] || variants.standard}
        ${variant === 'kpi' ? kpiColorClass : ''}
        ${className}
    `;

    return (
        <div className={finalClasses} {...props}>
            {(title || subtitle || action) && (
                <div className="px-6 py-5 border-b border-slate-100 flex justify-between items-start">
                    <div>
                        {title && <h5 className="text-lg font-bold text-slate-800 tracking-tight">{title}</h5>}
                        {subtitle && <p className="text-sm text-slate-500 mt-1">{subtitle}</p>}
                    </div>
                    {action && <div>{action}</div>}
                </div>
            )}
            <div className="p-6">
                {children}
            </div>
        </div>
    );
};

export default Card;
