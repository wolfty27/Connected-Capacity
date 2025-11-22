import React from 'react';

const Button = ({ children, onClick, type = 'button', variant = 'primary', className = '', ...props }) => {
    const baseStyles = "font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none transition-colors duration-200";
    
    const variants = {
        primary: "text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300",
        secondary: "text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200",
        danger: "text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300",
        link: "text-blue-600 hover:underline bg-transparent px-0 py-0",
    };

    return (
        <button
            type={type}
            onClick={onClick}
            className={`${baseStyles} ${variants[variant] || variants.primary} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
};

export default Button;
