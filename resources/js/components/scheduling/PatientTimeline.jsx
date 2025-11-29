import React, { useMemo } from 'react';
import { Clock, User, MapPin, Phone, Calendar } from 'lucide-react';

/**
 * PatientTimeline - Time-based view of a patient's care schedule
 *
 * Displays a chronological timeline of all visits for a selected patient,
 * organized by day. Unlike the staff-centric grid view, this shows:
 * - All visits in time order
 * - Staff assigned to each visit
 * - Service details and duration
 *
 * This is the correct patient-centric view (not staff rows for a patient).
 */
const PatientTimeline = ({
    assignments = [],
    weekDays = [],
    patientName = '',
    onEditAssignment,
}) => {
    // Group assignments by date
    const assignmentsByDate = useMemo(() => {
        const grouped = {};

        weekDays.forEach((date) => {
            const dateStr = date.toISOString().split('T')[0];
            grouped[dateStr] = [];
        });

        assignments.forEach((assignment) => {
            if (grouped[assignment.date]) {
                grouped[assignment.date].push(assignment);
            }
        });

        // Sort each day's assignments by start time
        Object.keys(grouped).forEach((date) => {
            grouped[date].sort((a, b) => {
                return a.start_time.localeCompare(b.start_time);
            });
        });

        return grouped;
    }, [assignments, weekDays]);

    // Get category color
    const getCategoryColor = (category) => {
        const colors = {
            nursing: { bg: '#DBEAFE', border: '#3B82F6', text: '#1E40AF' },
            psw: { bg: '#D1FAE5', border: '#10B981', text: '#065F46' },
            personal_support: { bg: '#D1FAE5', border: '#10B981', text: '#065F46' },
            homemaking: { bg: '#FEF3C7', border: '#F59E0B', text: '#92400E' },
            behaviour: { bg: '#FEE2E2', border: '#EF4444', text: '#991B1B' },
            behavioral: { bg: '#FEE2E2', border: '#EF4444', text: '#991B1B' },
            rehab: { bg: '#E9D5FF', border: '#8B5CF6', text: '#5B21B6' },
            therapy: { bg: '#E9D5FF', border: '#8B5CF6', text: '#5B21B6' },
        };
        return colors[category?.toLowerCase()] || { bg: '#F3F4F6', border: '#9CA3AF', text: '#374151' };
    };

    // Format date for display
    const formatDate = (date) => {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${days[date.getDay()]}, ${months[date.getMonth()]} ${date.getDate()}`;
    };

    // Check if date is today
    const isToday = (date) => {
        const today = new Date();
        return date.toDateString() === today.toDateString();
    };

    // Calculate total hours for a day
    const getTotalHours = (dayAssignments) => {
        return dayAssignments.reduce((sum, a) => sum + (a.duration_minutes || 60) / 60, 0);
    };

    return (
        <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
            {/* Header */}
            <div className="bg-slate-50 border-b border-slate-200 px-4 py-3">
                <div className="flex items-center gap-2">
                    <Calendar className="w-5 h-5 text-teal-600" />
                    <h2 className="text-sm font-bold text-slate-700">
                        Care Timeline for {patientName}
                    </h2>
                </div>
                <p className="text-xs text-slate-500 mt-1">
                    All scheduled care visits in chronological order
                </p>
            </div>

            {/* Timeline Content */}
            <div className="p-4 space-y-6">
                {weekDays.map((date) => {
                    const dateStr = date.toISOString().split('T')[0];
                    const dayAssignments = assignmentsByDate[dateStr] || [];
                    const totalHours = getTotalHours(dayAssignments);
                    const today = isToday(date);

                    return (
                        <div key={dateStr} className={`${today ? 'ring-2 ring-teal-400 ring-offset-2 rounded-lg' : ''}`}>
                            {/* Day Header */}
                            <div className={`flex items-center justify-between px-3 py-2 rounded-t-lg ${
                                today ? 'bg-teal-50' : 'bg-slate-100'
                            }`}>
                                <div className="flex items-center gap-2">
                                    <span className={`text-sm font-medium ${today ? 'text-teal-700' : 'text-slate-700'}`}>
                                        {formatDate(date)}
                                    </span>
                                    {today && (
                                        <span className="px-2 py-0.5 text-xs font-medium bg-teal-100 text-teal-700 rounded-full">
                                            Today
                                        </span>
                                    )}
                                </div>
                                <span className="text-xs text-slate-500">
                                    {dayAssignments.length} visits &middot; {totalHours.toFixed(1)}h
                                </span>
                            </div>

                            {/* Day's Appointments */}
                            <div className="border-l-2 border-slate-200 ml-4 pl-4 py-2 space-y-3">
                                {dayAssignments.length === 0 ? (
                                    <div className="text-sm text-slate-400 py-4 text-center">
                                        No visits scheduled
                                    </div>
                                ) : (
                                    dayAssignments.map((assignment) => {
                                        const colors = getCategoryColor(assignment.category);
                                        return (
                                            <div
                                                key={assignment.id}
                                                className="relative"
                                            >
                                                {/* Timeline dot */}
                                                <div
                                                    className="absolute -left-[21px] w-3 h-3 rounded-full border-2 border-white"
                                                    style={{ backgroundColor: colors.border }}
                                                />

                                                {/* Appointment card */}
                                                <button
                                                    onClick={() => onEditAssignment?.(assignment)}
                                                    className="w-full text-left p-3 rounded-lg border transition-all hover:shadow-md"
                                                    style={{
                                                        backgroundColor: colors.bg,
                                                        borderColor: colors.border,
                                                    }}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="flex-1">
                                                            {/* Time and Service */}
                                                            <div className="flex items-center gap-2 mb-1">
                                                                <Clock className="w-4 h-4" style={{ color: colors.text }} />
                                                                <span
                                                                    className="font-medium text-sm"
                                                                    style={{ color: colors.text }}
                                                                >
                                                                    {assignment.start_time} - {assignment.end_time}
                                                                </span>
                                                                <span className="text-xs text-slate-500">
                                                                    ({assignment.duration_minutes || 60} min)
                                                                </span>
                                                            </div>

                                                            {/* Service Name */}
                                                            <div className="text-sm font-medium text-slate-800 mb-1">
                                                                {assignment.service_type_name}
                                                            </div>

                                                            {/* Staff Info */}
                                                            <div className="flex items-center gap-1.5 text-xs text-slate-600">
                                                                <User className="w-3.5 h-3.5" />
                                                                <span>{assignment.staff_name || 'Unassigned'}</span>
                                                                {assignment.staff_role && (
                                                                    <span className="px-1.5 py-0.5 bg-white/50 rounded text-xs">
                                                                        {assignment.staff_role}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>

                                                        {/* Status Badge */}
                                                        <div className="flex-shrink-0">
                                                            <StatusBadge status={assignment.status} verificationStatus={assignment.verification_status} />
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Summary Footer */}
            <div className="bg-slate-50 border-t border-slate-200 px-4 py-3">
                <div className="flex items-center justify-between text-sm text-slate-600">
                    <span>
                        Total: {assignments.length} visits this week
                    </span>
                    <span>
                        {assignments.reduce((sum, a) => sum + (a.duration_minutes || 60), 0) / 60} hours of care
                    </span>
                </div>
            </div>
        </div>
    );
};

/**
 * Status badge component
 */
const StatusBadge = ({ status, verificationStatus }) => {
    const getStatusStyle = () => {
        if (status === 'completed') {
            if (verificationStatus === 'VERIFIED') {
                return 'bg-emerald-100 text-emerald-700';
            } else if (verificationStatus === 'MISSED') {
                return 'bg-red-100 text-red-700';
            }
            return 'bg-emerald-100 text-emerald-700';
        }
        if (status === 'planned' || status === 'pending') {
            return 'bg-blue-100 text-blue-700';
        }
        if (status === 'in_progress') {
            return 'bg-amber-100 text-amber-700';
        }
        if (status === 'cancelled') {
            return 'bg-slate-100 text-slate-500';
        }
        return 'bg-slate-100 text-slate-600';
    };

    const getStatusLabel = () => {
        if (status === 'completed') {
            if (verificationStatus === 'VERIFIED') return 'Verified';
            if (verificationStatus === 'MISSED') return 'Missed';
            return 'Completed';
        }
        if (status === 'planned') return 'Scheduled';
        if (status === 'pending') return 'Pending';
        if (status === 'in_progress') return 'In Progress';
        if (status === 'cancelled') return 'Cancelled';
        return status;
    };

    return (
        <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${getStatusStyle()}`}>
            {getStatusLabel()}
        </span>
    );
};

export default PatientTimeline;
