import React from 'react';

const FloatingCard = ({ children, className = '', delay = '0s' }) => {
    return (
        <div
            className={`absolute bg-white p-4 rounded-2xl shadow-xl border border-slate-100 animate-float ${className}`}
            style={{ animationDelay: delay }}
        >
            {children}
        </div>
    );
};

export default FloatingCard;
