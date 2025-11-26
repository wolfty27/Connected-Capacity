import React from 'react';

const Input = ({ label, type = 'text', id, value, onChange, placeholder = '', className = '', ...props }) => {
    return (
        <div className={`mb-4 ${className}`}>
            {label && (
                <label htmlFor={id} className="block mb-2 text-sm font-medium text-gray-900">
                    {label}
                </label>
            )}
            <input
                type={type}
                id={id}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                {...props}
            />
        </div>
    );
};

export default Input;
