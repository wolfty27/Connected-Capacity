import React from 'react';

const PatientSummaryCard = ({ patient, tnp }) => {
    if (!patient) {
        return (
            <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-5 animate-pulse">
                <div className="h-4 bg-slate-200 rounded w-1/2 mb-4"></div>
                <div className="flex gap-4 mb-4">
                    <div className="w-16 h-16 bg-slate-200 rounded-md"></div>
                    <div className="space-y-2 flex-1">
                        <div className="h-3 bg-slate-200 rounded w-3/4"></div>
                        <div className="h-3 bg-slate-200 rounded w-1/2"></div>
                    </div>
                </div>
                <div className="h-20 bg-slate-100 rounded"></div>
            </div>
        );
    }

    // Use TNP score from prop or patient data, fallback to mock
    const tnpScore = tnp?.score || patient.tnp_score || 82;
    const tnpLabel = tnpScore > 80 ? 'High' : tnpScore > 50 ? 'Moderate' : 'Low';
    const tnpColor = tnpScore > 80 ? 'text-rose-700 bg-rose-50 border-rose-100' : 'text-amber-700 bg-amber-50 border-amber-100';

    return (
        <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden sticky top-6">
            <div className="p-5 border-b border-slate-100">
                <h2 className="text-lg font-bold mb-4 text-slate-800">Patient Summary</h2>
                
                <div className="flex items-start gap-4 mb-4">
                    <div className="w-16 h-16 rounded-md bg-slate-200 flex items-center justify-center text-slate-400 text-2xl font-bold">
                        {patient.user?.name ? patient.user.name.charAt(0) : 'P'}
                    </div>
                    <div>
                        <p className="text-xs font-bold text-slate-500 uppercase tracking-wide">Patient</p>
                        <p className="font-semibold text-slate-900">{patient.user?.name || 'Unknown'}</p>
                        <p className="text-sm text-slate-600">DOB: {patient.date_of_birth || 'N/A'}</p>
                    </div>
                </div>
                
                <div className="space-y-3 text-sm">
                    <div>
                        <span className="font-semibold text-slate-900">OHIP: </span>
                        <span className="text-slate-600">{patient.ohip || 'N/A'}</span>
                    </div>
                    <div>
                        <span className="font-semibold text-slate-900">Condition: </span>
                        <span className="text-slate-600 block mt-1">
                            {patient.diagnosis || 'Post-Acute Assessment pending'}
                        </span>
                    </div>
                </div>
            </div>
            
            <div className={`p-5 border-t ${tnpColor}`}>
                <p className="font-semibold text-slate-900 mb-1 text-xs uppercase">Transition Needs Profile (TNP)</p>
                <div className="flex items-center gap-2">
                    <span className="text-2xl font-bold">{tnpLabel} ({tnpScore}/100)</span>
                </div>
            </div>
        </div>
    );
};

export default PatientSummaryCard;