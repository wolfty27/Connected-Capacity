import React from 'react';

/**
 * PatientTimeline - Patient-centric timeline view
 *
 * This component implements the scheduling.patient_timeline_correctness feature:
 * - Shows a single vertical list of visits ordered by start time
 * - No staff rows or multi-row clutter
 * - Spacing and sequencing reflect real-world workflow
 *
 * The backend (SchedulingEngine) ensures no overlapping visits exist,
 * so this component simply displays the data in a clean format.
 */
export default function PatientTimeline({
    patient = null,
    days = [],
    onEditAssignment = null,
    onDeleteAssignment = null,
    isLoading = false,
}) {
    if (isLoading) {
        return (
            <div className="p-6 text-center text-gray-500">
                Loading timeline...
            </div>
        );
    }

    if (!patient) {
        return (
            <div className="p-6 text-center text-gray-500">
                Select a patient to view their timeline.
            </div>
        );
    }

    const totalHoursThisWeek = days.reduce((sum, day) => sum + day.total_hours, 0);

    return (
        <div className="bg-white rounded-lg shadow">
            {/* Header */}
            <div className="px-6 py-4 border-b border-gray-200">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">
                            {patient.name}
                        </h2>
                        {patient.rug_category && (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mt-1">
                                {patient.rug_category}
                            </span>
                        )}
                    </div>
                    <div className="text-right">
                        <div className="text-sm text-gray-500">This Week</div>
                        <div className="text-2xl font-bold text-indigo-600">
                            {totalHoursThisWeek.toFixed(1)}h
                        </div>
                    </div>
                </div>
            </div>

            {/* Timeline Content */}
            <div className="divide-y divide-gray-100">
                {days.length === 0 ? (
                    <div className="p-6 text-center text-gray-500">
                        No visits scheduled for this period.
                    </div>
                ) : (
                    days.map((day) => (
                        <DaySection
                            key={day.date}
                            day={day}
                            onEditAssignment={onEditAssignment}
                            onDeleteAssignment={onDeleteAssignment}
                        />
                    ))
                )}
            </div>
        </div>
    );
}

/**
 * DaySection - A single day's assignments
 */
function DaySection({ day, onEditAssignment, onDeleteAssignment }) {
    const dateObj = new Date(day.date);
    const isToday = new Date().toDateString() === dateObj.toDateString();

    return (
        <div className={`${isToday ? 'bg-indigo-50/50' : ''}`}>
            {/* Day Header */}
            <div className="px-6 py-3 bg-gray-50 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="font-medium text-gray-900">
                        {day.day_name}
                    </span>
                    <span className="text-gray-500">
                        {dateObj.toLocaleDateString('en-CA')}
                    </span>
                    {isToday && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                            Today
                        </span>
                    )}
                </div>
                <span className="text-sm text-gray-600">
                    {day.total_hours}h scheduled
                </span>
            </div>

            {/* Assignments for this day - sorted by start time */}
            <div className="divide-y divide-gray-100">
                {day.assignments.map((assignment) => (
                    <AssignmentRow
                        key={assignment.id}
                        assignment={assignment}
                        onEdit={onEditAssignment}
                        onDelete={onDeleteAssignment}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * AssignmentRow - A single visit/assignment
 *
 * Format: "08:00-09:00 Physiotherapy (PT - Dr. X)"
 */
function AssignmentRow({ assignment, onEdit, onDelete }) {
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        planned: 'bg-blue-100 text-blue-800',
        active: 'bg-green-100 text-green-800',
        completed: 'bg-gray-100 text-gray-800',
        cancelled: 'bg-red-100 text-red-800',
        missed: 'bg-red-100 text-red-800',
    };

    return (
        <div className="px-6 py-3 flex items-center justify-between hover:bg-gray-50 group">
            <div className="flex items-center gap-4">
                {/* Time */}
                <div className="w-28 font-mono text-sm text-gray-700">
                    {assignment.time_range}
                </div>

                {/* Service color indicator */}
                <div
                    className="w-3 h-3 rounded-full"
                    style={{ backgroundColor: assignment.color || '#6366f1' }}
                />

                {/* Service info */}
                <div>
                    <div className="font-medium text-gray-900">
                        {assignment.service_type_name}
                        {assignment.visit_label && (
                            <span className="ml-2 text-sm text-gray-500">
                                ({assignment.visit_label})
                            </span>
                        )}
                    </div>
                    <div className="text-sm text-gray-500">
                        {assignment.service_type_code} · {assignment.duration_minutes} min
                    </div>
                </div>
            </div>

            <div className="flex items-center gap-3">
                {/* Status badge */}
                <span
                    className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                        statusColors[assignment.status] || 'bg-gray-100 text-gray-800'
                    }`}
                >
                    {assignment.status}
                </span>

                {/* Actions (visible on hover) */}
                <div className="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    {onEdit && (
                        <button
                            onClick={() => onEdit(assignment)}
                            className="text-indigo-600 hover:text-indigo-800 text-sm"
                        >
                            Edit
                        </button>
                    )}
                    {onDelete && (
                        <button
                            onClick={() => onDelete(assignment)}
                            className="text-red-600 hover:text-red-800 text-sm"
                        >
                            Delete
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
