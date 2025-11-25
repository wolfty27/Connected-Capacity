import React from 'react';
import { Minus, Plus, Info } from 'lucide-react';

const PROVIDERS = [
    'Assign...',
    'CarePartners',
    'VHA Home HealthCare',
    'Paramed',
    'SE Health'
];

const ServiceCard = ({ service, onUpdate }) => {
    const handleFreqChange = (delta) => {
        const newVal = Math.max(0, service.currentFrequency + delta);
        onUpdate(service.id, 'currentFrequency', newVal);
    };

    const handleDurationChange = (e) => {
        onUpdate(service.id, 'currentDuration', parseInt(e.target.value, 10));
    };

    const handleProviderChange = (e) => {
        onUpdate(service.id, 'provider', e.target.value);
    };

    return (
        <div className={`border rounded-lg p-5 mb-4 ${service.isAutoFilled ? 'bg-teal-50 border-teal-200' : 'bg-white border-gray-200'}`}>
            {service.isAutoFilled && (
                <div className="flex justify-end mb-2">
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800">
                        <Info className="w-3 h-3 mr-1" />
                        Auto-filled based on clinical rules
                    </span>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Icon & Description */}
                <div className="md:col-span-1">
                    <div className="flex items-start">
                        <div className={`flex-shrink-0 h-10 w-10 rounded flex items-center justify-center mr-4 ${service.category === 'CLINICAL' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-600'}`}>
                            <span className="font-bold text-sm">{service.code}</span>
                        </div>
                        <div>
                            <h4 className="text-sm font-bold text-slate-900">{service.name} ({service.code})</h4>
                            <p className="text-sm text-slate-600 mt-1 max-w-sm">{service.description}</p>
                        </div>
                    </div>
                </div>

                {/* Controls */}
                <div className="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">

                    {/* Frequency Control */}
                    <div className="md:col-span-1">
                        <div className="flex justify-between items-center mb-1">
                            <label className="text-xs font-semibold text-slate-700">Frequency (visits/week): <span className="text-slate-900 font-bold">{service.currentFrequency}</span></label>
                        </div>
                        <div className="flex items-center">
                            <button
                                onClick={() => handleFreqChange(-1)}
                                className="p-1 rounded-l-md border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-600"
                            >
                                <Minus className="w-4 h-4" />
                            </button>
                            <div className="w-10 text-center border-t border-b border-slate-300 py-1 text-sm bg-white font-medium">
                                {service.currentFrequency}
                            </div>
                            <button
                                onClick={() => handleFreqChange(1)}
                                className="p-1 rounded-r-md border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-600"
                            >
                                <Plus className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Provider Dropdown */}
                    <div className="flex flex-col justify-end md:col-span-2">
                        <label className="text-xs font-semibold text-slate-700 mb-1">Provider:</label>
                        <select
                            value={service.provider}
                            onChange={handleProviderChange}
                            className="block w-full rounded-md border-slate-300 border shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm py-1.5 px-3"
                        >
                            {PROVIDERS.map(p => (
                                <option key={p} value={p}>{p}</option>
                            ))}
                        </select>
                    </div>

                    {/* Duration Slider */}
                    <div className="md:col-span-3">
                        <div className="flex justify-between items-center mb-1">
                            <label className="text-xs font-semibold text-slate-700">Duration (weeks): <span className="text-slate-900 font-bold">{service.currentDuration}</span></label>
                        </div>
                        <div className="flex items-center gap-4">
                            <input
                                type="range"
                                min="1"
                                max="24"
                                value={service.currentDuration}
                                onChange={handleDurationChange}
                                className="w-full h-2 bg-blue-200 rounded-lg appearance-none cursor-pointer"
                                style={{
                                    background: `linear-gradient(to right, #2563eb 0%, #2563eb ${(service.currentDuration / 24) * 100}%, #bfdbfe ${(service.currentDuration / 24) * 100}%, #bfdbfe 100%)`
                                }}
                            />
                            <span className="text-sm text-slate-500 w-8">{service.currentDuration}</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    );
};

export default ServiceCard;
