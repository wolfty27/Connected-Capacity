import React from 'react';

/**
 * UnscheduledPanel - Shows patients with unscheduled care needs
 *
 * This component implements the bundles.unscheduled_care_correctness feature:
 * - Shows patients with remaining units > 0
 * - Displays required, scheduled, and remaining for each service
 * - Shows "All required care scheduled" when API returns empty result
 * - Sorted by priority (high-risk patients first)
 */
export default function UnscheduledPanel({
    requirements = [],
    summary = null,
    onAssign = null,
    isLoading = false,
}) {
    if (isLoading) {
        return (
            <div className="bg-white rounded-lg shadow p-6">
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                    <div className="h-20 bg-gray-200 rounded mb-3"></div>
                    <div className="h-20 bg-gray-200 rounded mb-3"></div>
                </div>
            </div>
        );
    }

    // Filter to only patients with unscheduled needs
    const patientsWithNeeds = requirements.filter((r) => r.has_unscheduled_needs);

    return (
        <div className="bg-white rounded-lg shadow">
            {/* Header */}
            <div className="px-4 py-3 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">
                    Unscheduled Care
                </h3>
                {summary && (
                    <div className="mt-1 flex gap-4 text-sm text-gray-600">
                        <span>{summary.patients_with_needs} patients</span>
                        <span>{summary.total_remaining_hours}h remaining</span>
                        {summary.total_remaining_visits > 0 && (
                            <span>{summary.total_remaining_visits} visits</span>
                        )}
                    </div>
                )}
            </div>

            {/* Content */}
            <div className="divide-y divide-gray-100 max-h-[calc(100vh-200px)] overflow-y-auto">
                {patientsWithNeeds.length === 0 ? (
                    <EmptyState />
                ) : (
                    patientsWithNeeds.map((patient) => (
                        <PatientCard
                            key={patient.patient_id}
                            patient={patient}
                            onAssign={onAssign}
                        />
                    ))
                )}
            </div>
        </div>
    );
}

/**
 * EmptyState - Shown when all care is scheduled
 */
function EmptyState() {
    return (
        <div className="p-8 text-center">
            <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                <svg
                    className="w-6 h-6 text-green-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M5 13l4 4L19 7"
                    />
                </svg>
            </div>
            <p className="text-gray-600 font-medium">All required care scheduled</p>
            <p className="text-sm text-gray-500 mt-1">
                No patients have outstanding scheduling needs.
            </p>
        </div>
    );
}

/**
 * PatientCard - A patient with unscheduled services
 */
function PatientCard({ patient, onAssign }) {
    const servicesWithNeeds = patient.services.filter(
        (s) => s.remaining > 0
    );

    const priorityColors = {
        1: 'border-l-red-500 bg-red-50',
        2: 'border-l-yellow-500 bg-yellow-50',
        3: 'border-l-gray-300',
    };

    return (
        <div
            className={`p-4 border-l-4 ${
                priorityColors[patient.priority_level] || 'border-l-gray-300'
            }`}
        >
            {/* Patient Header */}
            <div className="flex items-start justify-between mb-3">
                <div>
                    <div className="font-medium text-gray-900">
                        {patient.patient_name}
                    </div>
                    <div className="flex items-center gap-2 mt-1">
                        {patient.rug_category && (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                {patient.rug_category}
                            </span>
                        )}
                        {/* Risk flags */}
                        {patient.risk_flags?.map((flag) => (
                            <RiskFlag key={flag} flag={flag} />
                        ))}
                    </div>
                </div>
                <div className="text-right text-sm">
                    {patient.total_remaining_hours > 0 && (
                        <div className="text-gray-600">
                            {patient.total_remaining_hours}h remaining
                        </div>
                    )}
                    {patient.total_remaining_visits > 0 && (
                        <div className="text-gray-600">
                            {patient.total_remaining_visits} visit(s) remaining
                        </div>
                    )}
                </div>
            </div>

            {/* Services needing scheduling */}
            <div className="space-y-2">
                {servicesWithNeeds.map((service) => (
                    <ServiceRow
                        key={service.service_type_id}
                        service={service}
                        patientId={patient.patient_id}
                        carePlanId={patient.care_plan_id}
                        onAssign={onAssign}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * RiskFlag - Displays a risk flag badge
 */
function RiskFlag({ flag }) {
    const flagConfig = {
        high_fall_risk: { label: 'Fall Risk', color: 'bg-red-100 text-red-800' },
        cognitive_impairment: { label: 'Cognitive', color: 'bg-orange-100 text-orange-800' },
        clinical_instability: { label: 'Unstable', color: 'bg-red-100 text-red-800' },
        wandering: { label: 'Wandering', color: 'bg-yellow-100 text-yellow-800' },
        ED_risk: { label: 'ED Risk', color: 'bg-red-100 text-red-800' },
        caregiver_burden: { label: 'Caregiver', color: 'bg-blue-100 text-blue-800' },
    };

    const config = flagConfig[flag] || { label: flag, color: 'bg-gray-100 text-gray-800' };

    return (
        <span
            className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${config.color}`}
        >
            {config.label}
        </span>
    );
}

/**
 * ServiceRow - A service needing scheduling
 */
function ServiceRow({ service, patientId, carePlanId, onAssign }) {
    const completionPct = service.completion_percentage || 0;

    return (
        <div className="flex items-center justify-between p-2 bg-white rounded border border-gray-200">
            <div className="flex items-center gap-3">
                {/* Service color indicator */}
                <div
                    className="w-2.5 h-2.5 rounded-full"
                    style={{ backgroundColor: service.color || '#6366f1' }}
                />
                <div>
                    <div className="text-sm font-medium text-gray-900">
                        {service.service_type_name}
                    </div>
                    <div className="text-xs text-gray-500">
                        {service.scheduled} / {service.required} {service.unit_type}
                        <span className="mx-1">·</span>
                        <span className="text-amber-600 font-medium">
                            {service.remaining} {service.unit_type} remaining
                        </span>
                    </div>
                </div>
            </div>

            <div className="flex items-center gap-3">
                {/* Progress bar */}
                <div className="w-20 bg-gray-200 rounded-full h-1.5">
                    <div
                        className="bg-indigo-600 h-1.5 rounded-full transition-all"
                        style={{ width: `${Math.min(completionPct, 100)}%` }}
                    />
                </div>
                <span className="text-xs text-gray-500 w-10 text-right">
                    {completionPct.toFixed(0)}%
                </span>

                {/* Assign button */}
                {onAssign && (
                    <button
                        onClick={() =>
                            onAssign({
                                patientId,
                                carePlanId,
                                serviceTypeId: service.service_type_id,
                                serviceTypeName: service.service_type_name,
                            })
                        }
                        className="px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 transition-colors"
                    >
                        Assign
                    </button>
                )}
            </div>
        </div>
    );
}
