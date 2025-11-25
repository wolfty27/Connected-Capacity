import React from 'react';

const Section = ({ title, description, children, className = '', actions, action, ...props }) => {
    return (
        <section className={`mb-8 ${className}`} {...props}>
            {(title || description || actions) && (
                <div className="mb-4 flex justify-between items-start">
                    <div>
                        {title && <h2 className="text-xl font-semibold text-gray-900">{title}</h2>}
                        {description && <p className="text-gray-500 text-sm mt-1">{description}</p>}
                    </div>
                    {actions && <div>{actions}</div>}
                </div>
            )}
            <div className="bg-white p-4 rounded-lg shadow-sm">
                {children}
            </div>
        </section>
    );
};

export default Section;
