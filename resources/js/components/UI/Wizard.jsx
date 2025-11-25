import React from 'react';
import Button from './Button'; // Assuming Button exists, if not we'll use standard HTML button for now

const Wizard = ({ steps, currentStep, children, onNext, onBack, isNextDisabled, isSubmitting }) => {
    return (
        <div className="w-full max-w-4xl mx-auto">
            {/* Stepper Header */}
            <div className="mb-8">
                <div className="flex items-center justify-between relative">
                    <div className="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-slate-200 -z-10"></div>
                    {steps.map((step, index) => {
                        const isCompleted = index < currentStep;
                        const isCurrent = index === currentStep;

                        return (
                            <div key={index} className="flex flex-col items-center bg-slate-50 px-2">
                                <div
                                    className={`
                                        w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 border-2
                                        ${isCompleted ? 'bg-teal-600 border-teal-600 text-white' : ''}
                                        ${isCurrent ? 'bg-white border-teal-600 text-teal-600 shadow-md scale-110' : ''}
                                        ${!isCompleted && !isCurrent ? 'bg-white border-slate-300 text-slate-400' : ''}
                                    `}
                                >
                                    {isCompleted ? (
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                                    ) : (
                                        index + 1
                                    )}
                                </div>
                                <span className={`mt-2 text-xs font-semibold uppercase tracking-wide ${isCurrent ? 'text-teal-900' : 'text-slate-400'}`}>
                                    {step}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Content Area */}
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-8 min-h-[400px] relative animate-fade-in">
                {children}
            </div>

            {/* Footer Actions */}
            <div className="mt-6 flex justify-between items-center">
                <button
                    onClick={onBack}
                    disabled={currentStep === 0}
                    className={`
                        px-6 py-2.5 rounded-lg font-medium text-sm transition-colors
                        ${currentStep === 0
                            ? 'text-slate-300 cursor-not-allowed'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 bg-white border border-slate-200'}
                    `}
                >
                    Back
                </button>

                <button
                    onClick={onNext}
                    disabled={isNextDisabled || isSubmitting}
                    className={`
                        px-8 py-2.5 rounded-lg font-bold text-sm text-white shadow-md transition-all
                        ${isNextDisabled || isSubmitting
                            ? 'bg-slate-300 cursor-not-allowed'
                            : 'bg-teal-600 hover:bg-teal-700 hover:shadow-lg transform hover:-translate-y-0.5'}
                    `}
                >
                    {isSubmitting ? 'Processing...' : (currentStep === steps.length - 1 ? 'Complete' : 'Next Step')}
                </button>
            </div>
        </div>
    );
};

export default Wizard;
