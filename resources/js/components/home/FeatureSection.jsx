import React from 'react';

const features = [
    {
        title: 'Zero Missed Care',
        description: 'Real-time alerts ensure every patient visit is completed or triaged immediately.',
        icon: (
            <svg className="w-6 h-6 text-teal-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
        )
    },
    {
        title: 'Dynamic Marketplace',
        description: 'Access a vetted network of SSPOs (Mental Health, RPM, Dementia) to fill gaps.',
        icon: (
            <svg className="w-6 h-6 text-teal-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
        )
    },
    {
        title: 'PHIPA Compliant',
        description: "Secure, role-based access control designed for Ontario's health privacy standards.",
        icon: (
            <svg className="w-6 h-6 text-teal-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
        )
    }
];

const FeatureSection = () => {
    return (
        <section id="features" className="py-20 bg-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div className="flex flex-col lg:flex-row items-center gap-16">
                    <div className="w-full lg:w-1/2">
                        <div className="bg-teal-50 rounded-3xl p-8 border border-teal-100 relative">
                            <div className="absolute -right-4 -top-4 bg-white p-4 rounded-xl shadow-lg border border-slate-100 max-w-xs z-10">
                                <div className="flex items-center gap-3">
                                    <div className="w-2 h-2 rounded-full bg-green-500"></div>
                                    <span className="text-sm font-bold text-slate-700">OH Compliance: 100%</span>
                                </div>
                            </div>
                            {/* Abstract Screen rep */}
                            <div className="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
                                <div className="h-4 bg-slate-100 rounded w-3/4"></div>
                                <div className="h-4 bg-slate-100 rounded w-1/2"></div>
                                <div className="grid grid-cols-3 gap-2 mt-4">
                                    <div className="h-20 bg-slate-50 rounded border border-slate-200"></div>
                                    <div className="h-20 bg-slate-50 rounded border border-slate-200"></div>
                                    <div className="h-20 bg-indigo-50 rounded border border-indigo-200"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="w-full lg:w-1/2">
                        <h2 className="text-3xl font-bold text-slate-900 mb-6">Total Visibility for SPOs.</h2>
                        <ul className="space-y-4">
                            {features.map((feature, index) => (
                                <li key={index} className="flex items-start gap-3">
                                    {feature.icon}
                                    <div>
                                        <h4 className="font-bold text-slate-800">{feature.title}</h4>
                                        <p className="text-sm text-slate-500">{feature.description}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

            </div>
        </section>
    );
};

export default FeatureSection;
