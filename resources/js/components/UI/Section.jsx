import React from 'react';

const Section = ({ title, description, children, className = '', ...props }) => {
    return (
        <section className={`mb-8 ${className}`} {...props}>
            {(title || description) && (
                <div className="mb-4">
                    {title && <h2 className="text-xl font-semibold text-gray-900">{title}</h2>}
                    {description && <p className="text-gray-500 text-sm mt-1">{description}</p>}
                </div>
            )}
            <div className="bg-white p-4 rounded-lg shadow-sm">
                {children}
            </div>
        </section>
    );
};

export default Section;
