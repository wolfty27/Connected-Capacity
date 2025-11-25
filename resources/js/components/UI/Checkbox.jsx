import React from 'react';

const Checkbox = ({ label, id, checked, onChange, className = '', ...props }) => {
    return (
        <div className={`flex items-center mb-4 ${className}`}>
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={onChange}
                className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                {...props}
            />
            {label && (
                <label htmlFor={id} className="ml-2 text-sm font-medium text-gray-900">
                    {label}
                </label>
            )}
        </div>
    );
};

export default Checkbox;
