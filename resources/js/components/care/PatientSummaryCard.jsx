import React from 'react';

/**
 * PatientSummaryCard - Shows patient details and RUG classification
 *
 * Displays patient info and their InterRAI HC-derived RUG classification
 * instead of the legacy TNP score.
 */
const PatientSummaryCard = ({ patient }) => {
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

    // Get RUG classification from patient data
    const rugGroup = patient.rug_group;
    const rugCategory = patient.rug_category;
    const rugDescription = patient.rug_description;
    const mapleScore = patient.maple_score;

    // Determine color based on RUG category complexity
    const getRugColor = () => {
        if (!rugGroup) return 'text-slate-600 bg-slate-50 border-slate-200';
        if (rugGroup.startsWith('SE') || rugGroup.startsWith('SS')) {
            return 'text-rose-700 bg-rose-50 border-rose-100'; // High complexity
        }
        if (rugGroup.startsWith('R') || rugGroup.startsWith('C')) {
            return 'text-amber-700 bg-amber-50 border-amber-100'; // Moderate complexity
        }
        if (rugGroup.startsWith('I') || rugGroup.startsWith('B')) {
            return 'text-purple-700 bg-purple-50 border-purple-100'; // Cognitive/Behavioural
        }
        return 'text-teal-700 bg-teal-50 border-teal-100'; // Physical function
    };

    return (
        <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
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
                    {mapleScore && (
                        <div>
                            <span className="font-semibold text-slate-900">MAPLe Score: </span>
                            <span className="text-slate-600">{mapleScore}</span>
                        </div>
                    )}
                </div>
            </div>

            {/* RUG Classification Card (replaces TNP) */}
            <div className={`p-4 border-t ${getRugColor()}`}>
                <p className="font-semibold text-slate-900 mb-1 text-xs uppercase">InterRAI HC / RUG Classification</p>
                {rugGroup ? (
                    <div>
                        <div className="flex items-baseline gap-1 flex-wrap">
                            <span className="text-2xl font-bold">{rugGroup}</span>
                            {rugCategory && (
                                <span className="text-sm font-medium opacity-80">– {rugCategory}</span>
                            )}
                        </div>
                        {rugDescription && (
                            <p className="text-sm mt-1 opacity-75">{rugDescription}</p>
                        )}
                    </div>
                ) : (
                    <div className="text-sm italic">
                        InterRAI HC Assessment Pending
                    </div>
                )}
            </div>
        </div>
    );
};

export default PatientSummaryCard;