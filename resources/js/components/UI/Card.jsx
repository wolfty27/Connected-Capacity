import React from 'react';

const Card = ({ title, children, className = '', ...props }) => {
    return (
        <div className={`bg-white border border-gray-200 rounded-lg shadow-sm ${className}`} {...props}>
            {title && (
                <div className="px-6 py-4 border-b border-gray-200">
                    <h5 className="text-lg font-bold text-gray-900">{title}</h5>
                </div>
            )}
            <div className="p-6">
                {children}
            </div>
        </div>
    );
};

export default Card;
