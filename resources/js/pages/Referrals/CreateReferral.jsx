import React, { useState } from 'react';
import Wizard from '../../components/UI/Wizard';
import Card from '../../components/UI/Card';

const CreateReferral = () => {
    const [currentStep, setCurrentStep] = useState(0);
    const [formData, setFormData] = useState({
        patientName: '',
        hospital: '',
        urgency: 'Routine',
        notes: ''
    });

    const steps = ['Patient & Source', 'Clinical Context', 'Review & Submit'];

    const handleNext = () => {
        if (currentStep < steps.length - 1) {
            setCurrentStep(curr => curr + 1);
        } else {
            console.log('Submitting:', formData);
            // Submit logic here
        }
    };

    const handleBack = () => {
        if (currentStep > 0) {
            setCurrentStep(curr => curr - 1);
        }
    };

    return (
        <div className="animate-fade-in">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-800">New Patient Referral</h1>
                <p className="text-slate-500">Create a new transition request for a patient.</p>
            </div>

            <Wizard
                steps={steps}
                currentStep={currentStep}
                onNext={handleNext}
                onBack={handleBack}
            >
                {currentStep === 0 && (
                    <div className="space-y-6">
                        <div className="bg-indigo-50 border border-indigo-100 rounded-lg p-4 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-100 rounded-lg text-indigo-600">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <p className="text-sm font-bold text-indigo-900">Gemini Smart Paste</p>
                                    <p className="text-xs text-indigo-700">Paste raw patient notes here to auto-fill this form.</p>
                                </div>
                            </div>
                            <button className="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded shadow-sm hover:bg-indigo-700">
                                Try It
                            </button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Patient Name</label>
                                <input
                                    type="text"
                                    className="w-full rounded-lg border-slate-300 focus:border-teal-500 focus:ring-teal-500"
                                    placeholder="e.g. John Doe"
                                    value={formData.patientName}
                                    onChange={e => setFormData({ ...formData, patientName: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Hospital / Origin</label>
                                <select
                                    className="w-full rounded-lg border-slate-300 focus:border-teal-500 focus:ring-teal-500"
                                    value={formData.hospital}
                                    onChange={e => setFormData({ ...formData, hospital: e.target.value })}
                                >
                                    <option value="">Select Hospital...</option>
                                    <option value="City General">City General</option>
                                    <option value="Westside Clinic">Westside Clinic</option>
                                </select>
                            </div>
                        </div>
                    </div>
                )}

                {currentStep === 1 && (
                    <div className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Urgency Level</label>
                            <div className="flex gap-4">
                                {['Routine', 'Urgent', 'Critical'].map(level => (
                                    <button
                                        key={level}
                                        onClick={() => setFormData({ ...formData, urgency: level })}
                                        className={`flex-1 py-3 rounded-lg border text-sm font-bold transition-all ${formData.urgency === level
                                                ? 'border-teal-500 bg-teal-50 text-teal-700 ring-1 ring-teal-500'
                                                : 'border-slate-200 hover:border-slate-300 text-slate-600'
                                            }`}
                                    >
                                        {level}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Clinical Notes</label>
                            <textarea
                                className="w-full rounded-lg border-slate-300 focus:border-teal-500 focus:ring-teal-500 h-32"
                                placeholder="Enter relevant clinical context..."
                                value={formData.notes}
                                onChange={e => setFormData({ ...formData, notes: e.target.value })}
                            ></textarea>
                        </div>
                    </div>
                )}

                {currentStep === 2 && (
                    <div className="space-y-6">
                        <div className="bg-slate-50 p-6 rounded-lg border border-slate-200">
                            <h3 className="text-lg font-bold text-slate-800 mb-4">Summary</h3>
                            <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm font-medium text-slate-500">Patient</dt>
                                    <dd className="mt-1 text-sm text-slate-900 font-semibold">{formData.patientName || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-slate-500">Origin</dt>
                                    <dd className="mt-1 text-sm text-slate-900">{formData.hospital || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-slate-500">Urgency</dt>
                                    <dd className="mt-1 text-sm text-slate-900">
                                        <span className="px-2 py-1 rounded-full bg-slate-200 text-xs font-bold">{formData.urgency}</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div className="flex items-start gap-3 p-4 bg-yellow-50 text-yellow-800 rounded-lg text-sm">
                            <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <p>Please verify all patient information is HIPAA compliant before submitting.</p>
                        </div>
                    </div>
                )}
            </Wizard>
        </div>
    );
};

export default CreateReferral;
