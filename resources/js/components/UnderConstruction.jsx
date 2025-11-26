import React from 'react';

const UnderConstruction = ({ title = "Feature Under Construction" }) => {
    return (
        <div className="flex flex-col items-center justify-center h-[60vh] text-center p-8">
            <div className="bg-yellow-50 rounded-full p-6 mb-4">
                <svg className="w-12 h-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h1 className="text-3xl font-bold text-slate-800 mb-2">{title}</h1>
            <p className="text-slate-500 max-w-md">
                This module is part of the Connected Capacity V2.1 Contract Build. 
                Engineering is currently implementing this workflow.
            </p>
        </div>
    );
};

export default UnderConstruction;
