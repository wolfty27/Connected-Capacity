import React from 'react';

const steps = [
    {
        number: '01',
        title: 'Referral Intake',
        description: 'Receive HPG referrals instantly. AI extracts patient data to start the 24h service clock.'
    },
    {
        number: '02',
        title: 'Transition Review',
        description: 'Assess risk factors (Dementia, Falls). Build a comprehensive Transition Needs Profile.'
    },
    {
        number: '03',
        title: 'Bundle Creation',
        description: 'Select the "High Intensity" care bundle. Customize frequency and modalities.'
    },
    {
        number: '04',
        title: 'Partner Assignment',
        description: 'Dispatch work to internal staff or SSPO partners based on real-time capacity.'
    }
];

const WorkflowSection = () => {
    return (
        <section id="how-it-works" className="py-20 bg-slate-50 border-t border-slate-200">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-16">
                    <h2 className="text-sm font-bold text-teal-600 uppercase tracking-wider mb-2">The Workflow</h2>
                    <h3 className="text-3xl font-bold text-slate-900">From Intake to Stable Care</h3>
                    <p className="text-slate-500 mt-4">Our platform automates the complex coordination between Hospitals, SPOs, and Community Providers.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    {steps.map((step, index) => (
                        <div key={step.number} className="relative group">
                            <div className="text-6xl font-bold text-slate-200 mb-4 group-hover:text-teal-100 transition-colors">
                                {step.number}
                            </div>
                            <h4 className="text-lg font-bold text-slate-800 mb-2">{step.title}</h4>
                            <p className="text-sm text-slate-500 leading-relaxed">{step.description}</p>

                            {/* Connecting Line (except for the last item) */}
                            {index < steps.length - 1 && (
                                <div className="hidden lg:block absolute top-8 right-0 w-full h-0.5 bg-slate-200 -z-10 translate-x-1/2"></div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
};

export default WorkflowSection;
