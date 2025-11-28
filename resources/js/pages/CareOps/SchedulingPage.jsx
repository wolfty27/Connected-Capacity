import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import api from '../../services/api';
import Button from '../../components/UI/Button';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

/**
 * Staff Scheduling Dashboard
 *
 * Features:
 * - Week grid view with staff rows and day columns
 * - Unscheduled care panel showing patients with unmet service needs
 * - Filters by role, employment type, staff, patient
 * - Navigation examples card with deep links
 * - Assign Care Service modal integration
 * - Edit Assignment modal for modifying/canceling assignments
 *
 * Routes:
 * - /spo/scheduling - SPO scheduling dashboard
 * - /sspo/scheduling - SSPO scheduling dashboard (scoped)
 */
const SchedulingPage = ({ isSspoMode = false }) => {
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();

    // URL params for deep links
    const staffIdParam = searchParams.get('staff_id');
    const patientIdParam = searchParams.get('patient_id');

    // State
    const [loading, setLoading] = useState(true);
    const [gridData, setGridData] = useState({ staff: [], assignments: [], week: {} });
    const [requirements, setRequirements] = useState({ data: [], summary: {} });
    const [navExamples, setNavExamples] = useState({ staff: null, patient: null });
    const [roles, setRoles] = useState([]);
    const [employmentTypes, setEmploymentTypes] = useState([]);

    // Filters
    const [weekOffset, setWeekOffset] = useState(0);
    const [roleFilter, setRoleFilter] = useState('');
    const [empTypeFilter, setEmpTypeFilter] = useState('');

    // Modals
    const [assignModalOpen, setAssignModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [selectedAssignment, setSelectedAssignment] = useState(null);
    const [selectedPatient, setSelectedPatient] = useState(null);
    const [selectedServiceType, setSelectedServiceType] = useState(null);
    const [selectedStaff, setSelectedStaff] = useState(null);
    const [selectedDate, setSelectedDate] = useState(null);

    // Calculate week range (Monday start - ISO week standard)
    const weekRange = useMemo(() => {
        const today = new Date();
        const dayOfWeek = today.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
        // Calculate Monday: if Sunday (0), go back 6 days; otherwise go back (day - 1) days
        const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
        const startOfWeek = new Date(today);
        startOfWeek.setDate(today.getDate() - daysToMonday + (weekOffset * 7));
        startOfWeek.setHours(0, 0, 0, 0);
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);
        endOfWeek.setHours(23, 59, 59, 999);
        return {
            start: startOfWeek.toISOString().split('T')[0],
            end: endOfWeek.toISOString().split('T')[0],
            startDate: startOfWeek,
            endDate: endOfWeek,
        };
    }, [weekOffset]);

    // Week days array
    const weekDays = useMemo(() => {
        return Array.from({ length: 7 }, (_, i) => {
            const date = new Date(weekRange.startDate);
            date.setDate(weekRange.startDate.getDate() + i);
            return date;
        });
    }, [weekRange.startDate]);

    // Fetch grid data
    const fetchGridData = useCallback(async () => {
        try {
            const params = new URLSearchParams({
                start_date: weekRange.start,
                end_date: weekRange.end,
                ...(staffIdParam && { staff_id: staffIdParam }),
                ...(patientIdParam && { patient_id: patientIdParam }),
                ...(roleFilter && { 'role_codes[]': roleFilter }),
                ...(empTypeFilter && { 'employment_type_codes[]': empTypeFilter }),
            });
            const response = await api.get(`/v2/scheduling/grid?${params}`);
            setGridData(response.data.data);
        } catch (error) {
            console.error('Failed to fetch grid data:', error);
        }
    }, [weekRange.start, weekRange.end, staffIdParam, patientIdParam, roleFilter, empTypeFilter]);

    // Fetch requirements
    const fetchRequirements = useCallback(async () => {
        try {
            const params = new URLSearchParams({
                start_date: weekRange.start,
                end_date: weekRange.end,
                ...(patientIdParam && { patient_id: patientIdParam }),
            });
            const response = await api.get(`/v2/scheduling/requirements?${params}`);
            setRequirements(response.data);
        } catch (error) {
            console.error('Failed to fetch requirements:', error);
        }
    }, [weekRange.start, weekRange.end, patientIdParam]);

    // Fetch metadata (includes navigation examples with current context)
    const fetchMetadata = useCallback(async () => {
        try {
            const params = new URLSearchParams();
            if (staffIdParam) params.set('current_staff_id', staffIdParam);
            if (patientIdParam) params.set('current_patient_id', patientIdParam);

            const [rolesRes, empTypesRes, navRes] = await Promise.all([
                api.get('/v2/workforce/metadata/roles'),
                api.get('/v2/workforce/metadata/employment-types'),
                api.get(`/v2/scheduling/navigation-examples?${params}`),
            ]);
            setRoles(rolesRes.data.data || []);
            setEmploymentTypes(empTypesRes.data.data || []);
            setNavExamples(navRes.data.data || {});
        } catch (error) {
            console.error('Failed to fetch metadata:', error);
        }
    }, [staffIdParam, patientIdParam]);

    // Initial load
    useEffect(() => {
        const loadAll = async () => {
            setLoading(true);
            await Promise.all([fetchGridData(), fetchRequirements(), fetchMetadata()]);
            setLoading(false);
        };
        loadAll();
    }, [fetchGridData, fetchRequirements, fetchMetadata]);

    // Open assign modal
    const openAssignModal = (patient, serviceTypeId, staff = null, date = null) => {
        setSelectedPatient(patient);
        setSelectedServiceType(serviceTypeId);
        setSelectedStaff(staff);
        setSelectedDate(date);
        setAssignModalOpen(true);
    };

    // Open edit modal
    const openEditModal = (assignment) => {
        setSelectedAssignment(assignment);
        setEditModalOpen(true);
    };

    // Handle assignment creation
    const handleCreateAssignment = async (data) => {
        try {
            await api.post('/v2/scheduling/assignments', data);
            setAssignModalOpen(false);
            fetchGridData();
            fetchRequirements();
        } catch (error) {
            console.error('Failed to create assignment:', error);
            alert(error.response?.data?.message || 'Failed to create assignment');
        }
    };

    // Handle assignment update
    const handleUpdateAssignment = async (id, data) => {
        try {
            await api.patch(`/v2/scheduling/assignments/${id}`, data);
            setEditModalOpen(false);
            fetchGridData();
        } catch (error) {
            console.error('Failed to update assignment:', error);
            alert(error.response?.data?.message || 'Failed to update assignment');
        }
    };

    // Handle assignment cancellation
    const handleCancelAssignment = async (id) => {
        if (!confirm('Are you sure you want to cancel this assignment?')) return;
        try {
            await api.delete(`/v2/scheduling/assignments/${id}`);
            setEditModalOpen(false);
            fetchGridData();
            fetchRequirements();
        } catch (error) {
            console.error('Failed to cancel assignment:', error);
        }
    };

    // Clear filters and navigate to base URL
    const clearFilters = () => {
        setRoleFilter('');
        setEmpTypeFilter('');
        // Navigate to the base path without any search params
        navigate(window.location.pathname);
    };

    // Get assignments for a staff member on a specific date
    const getAssignmentsForCell = (staffId, dateString) => {
        return gridData.assignments.filter(
            a => a.staff_id === staffId && a.date === dateString && a.status !== 'cancelled'
        );
    };

    // Check if staff is available on a day
    const isStaffAvailable = (staff, dayOfWeek) => {
        return staff.availability?.some(a => a.day_of_week === dayOfWeek);
    };

    // Get category color
    const getCategoryColor = (category) => {
        const colors = {
            nursing: '#DBEAFE',
            psw: '#D1FAE5',
            personal_support: '#D1FAE5',
            homemaking: '#FEF3C7',
            behaviour: '#FEE2E2',
            behavioral: '#FEE2E2',
            rehab: '#E9D5FF',
            therapy: '#E9D5FF',
        };
        return colors[category?.toLowerCase()] || '#F3F4F6';
    };

    // Format date for display
    const formatDate = (date) => {
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${days[date.getDay()]} ${months[date.getMonth()]} ${date.getDate()}`;
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <Spinner size="lg" />
            </div>
        );
    }

    const hasFilters = staffIdParam || patientIdParam || roleFilter || empTypeFilter;

    return (
        <div className="min-h-screen bg-slate-50">
            {/* Header */}
            <div className="bg-white border-b border-slate-200 px-6 py-4">
                <div className="max-w-7xl mx-auto flex justify-between items-center">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            {isSspoMode ? 'SSPO' : 'Staff'} Scheduling Dashboard
                        </h1>
                        <p className="text-sm text-slate-500">
                            Schedule and manage care assignments
                        </p>
                    </div>
                    <div className="flex items-center gap-4">
                        {/* Week Navigation */}
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setWeekOffset(prev => prev - 1)}
                            >
                                &larr; Prev
                            </Button>
                            <span className="text-sm font-medium px-3">
                                {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
                            </span>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setWeekOffset(prev => prev + 1)}
                            >
                                Next &rarr;
                            </Button>
                            {weekOffset !== 0 && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setWeekOffset(0)}
                                >
                                    Today
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Filters Bar */}
            <div className="bg-white border-b border-slate-200 px-6 py-3">
                <div className="max-w-7xl mx-auto flex items-center gap-4">
                    <Select
                        value={roleFilter}
                        onChange={(e) => setRoleFilter(e.target.value)}
                        options={[
                            { value: '', label: 'All Roles' },
                            ...roles.map(r => ({ value: r.code, label: `${r.code} - ${r.name}` })),
                        ]}
                        className="w-48"
                    />
                    <Select
                        value={empTypeFilter}
                        onChange={(e) => setEmpTypeFilter(e.target.value)}
                        options={[
                            { value: '', label: 'All Employment Types' },
                            ...employmentTypes.map(t => ({ value: t.code, label: t.name })),
                        ]}
                        className="w-48"
                    />
                    {hasFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters}>
                            Clear Filters
                        </Button>
                    )}
                    {(staffIdParam || patientIdParam) && (
                        <span className="text-sm text-slate-500 ml-4 flex items-center gap-2">
                            {staffIdParam && (
                                <span className="bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                                    Staff: {gridData.staff?.find(s => s.id === parseInt(staffIdParam))?.name || `ID ${staffIdParam}`}
                                </span>
                            )}
                            {patientIdParam && (
                                <span className="bg-green-100 text-green-700 px-2 py-0.5 rounded">
                                    Patient: {navExamples.patient?.name || `ID ${patientIdParam}`}
                                </span>
                            )}
                        </span>
                    )}
                </div>
            </div>

            {/* Navigation Examples Card */}
            <div className="max-w-7xl mx-auto px-6 py-4">
                <div className="bg-white rounded-lg border border-slate-200 p-4 mb-4">
                    <h3 className="text-sm font-bold text-slate-700 mb-3">Quick Navigation</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Staff-centric view */}
                        <div className={`p-3 rounded-lg border ${staffIdParam ? 'bg-blue-100 border-blue-400' : 'bg-blue-50 border-blue-200'}`}>
                            <div className="text-xs font-bold text-blue-800 mb-1">
                                Staff-Centric View
                                {staffIdParam && <span className="ml-1 text-blue-600">(Active)</span>}
                            </div>
                            {navExamples.staff ? (
                                <button
                                    onClick={() => setSearchParams({ staff_id: navExamples.staff.id })}
                                    className="text-sm text-blue-600 hover:underline text-left"
                                >
                                    View {navExamples.staff.name}'s schedule
                                    {navExamples.staff.role && (
                                        <span className="block text-xs text-blue-400">({navExamples.staff.role})</span>
                                    )}
                                </button>
                            ) : (
                                <span className="text-sm text-slate-400">No staff available</span>
                            )}
                        </div>

                        {/* Patient-centric view */}
                        <div className={`p-3 rounded-lg border ${patientIdParam ? 'bg-green-100 border-green-400' : 'bg-green-50 border-green-200'}`}>
                            <div className="text-xs font-bold text-green-800 mb-1">
                                Patient-Centric View
                                {patientIdParam && <span className="ml-1 text-green-600">(Active)</span>}
                            </div>
                            {navExamples.patient ? (
                                <button
                                    onClick={() => setSearchParams({ patient_id: navExamples.patient.id })}
                                    className="text-sm text-green-600 hover:underline text-left"
                                >
                                    View {navExamples.patient.name}'s care
                                </button>
                            ) : (
                                <span className="text-sm text-slate-400">No patient available</span>
                            )}
                        </div>

                        {/* Full dashboard view */}
                        <div className={`p-3 rounded-lg border ${!hasFilters ? 'bg-slate-100 border-slate-400' : 'bg-slate-50 border-slate-200'}`}>
                            <div className="text-xs font-bold text-slate-700 mb-1">
                                Full Dashboard View
                                {!hasFilters && <span className="ml-1 text-slate-500">(Active)</span>}
                            </div>
                            <button
                                onClick={clearFilters}
                                className="text-sm text-slate-600 hover:underline"
                            >
                                {hasFilters ? 'Clear filters & view all' : 'Viewing all schedules'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className="max-w-7xl mx-auto px-6 pb-8">
                <div className="flex gap-6">
                    {/* Left Panel - Unscheduled Care */}
                    <div className="w-80 flex-shrink-0">
                        <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
                            <div className="bg-slate-50 border-b border-slate-200 px-4 py-3">
                                <h2 className="text-sm font-bold text-slate-700">Unscheduled Care</h2>
                                <p className="text-xs text-slate-500">
                                    {requirements.summary?.patients_with_needs || 0} patients need scheduling
                                </p>
                            </div>
                            <div className="max-h-[600px] overflow-y-auto p-4 space-y-4">
                                {requirements.data?.map((item) => (
                                    <div
                                        key={item.patient_id}
                                        className="bg-slate-50 rounded-lg border border-slate-200 p-3"
                                    >
                                        <div className="flex items-start justify-between mb-2">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium">{item.patient_name}</span>
                                                    {item.risk_flags?.includes('dangerous') && (
                                                        <span className="text-xs text-red-600 font-bold">!</span>
                                                    )}
                                                    {item.risk_flags?.includes('warning') && (
                                                        <span className="text-xs text-amber-600 font-bold">!</span>
                                                    )}
                                                </div>
                                                <div className="text-xs text-slate-500">RUG: {item.rug_category}</div>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            {item.services?.map((service) => (
                                                <div key={service.service_type_id} className="flex items-center justify-between">
                                                    <div className="flex-1">
                                                        <div className="text-xs font-medium">{service.service_type_name}</div>
                                                        <div className="text-xs text-slate-500">
                                                            {service.scheduled}/{service.required} {service.unit_type} scheduled
                                                        </div>
                                                    </div>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openAssignModal(item, service.service_type_id)}
                                                        className="text-xs text-blue-600"
                                                    >
                                                        Assign
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {(!requirements.data || requirements.data.length === 0) && (
                                    <div className="text-center py-8 text-slate-400">
                                        <div className="text-2xl mb-2">&#10003;</div>
                                        <div className="text-sm">All required care scheduled</div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right Panel - Schedule Grid */}
                    <div className="flex-1 bg-white rounded-lg border border-slate-200 overflow-hidden">
                        <div className="overflow-x-auto">
                            {/* Header Row */}
                            <div className="grid grid-cols-8 border-b border-slate-200 bg-slate-50 sticky top-0 z-10 min-w-[1000px]">
                                <div className="px-4 py-3 border-r border-slate-200">
                                    <div className="text-xs font-bold text-slate-600">Staff Member</div>
                                </div>
                                {weekDays.map((date, idx) => (
                                    <div key={idx} className="px-4 py-3 border-r border-slate-200 text-center">
                                        <div className="text-xs font-bold text-slate-600">{formatDate(date)}</div>
                                    </div>
                                ))}
                            </div>

                            {/* Staff Rows */}
                            {gridData.staff?.map((staff) => {
                                const isHighlighted = staffIdParam && parseInt(staffIdParam) === staff.id;
                                return (
                                    <div
                                        key={staff.id}
                                        className={`grid grid-cols-8 border-b border-slate-100 min-w-[1000px] ${
                                            isHighlighted ? 'bg-blue-50' : 'hover:bg-slate-50'
                                        }`}
                                    >
                                        {/* Staff Info */}
                                        <div className="px-4 py-3 border-r border-slate-200">
                                            <div className="text-sm font-medium">{staff.name}</div>
                                            <div className="text-xs text-slate-500">
                                                {staff.role?.code || '-'}, {staff.employment_type?.code || '-'}
                                            </div>
                                            <div className="mt-1 flex items-center gap-2">
                                                <div className="flex-1 h-1.5 bg-slate-200 rounded-full">
                                                    <div
                                                        className={`h-full rounded-full ${
                                                            staff.utilization?.utilization > 90 ? 'bg-red-500' :
                                                            staff.utilization?.utilization > 75 ? 'bg-amber-500' :
                                                            'bg-emerald-500'
                                                        }`}
                                                        style={{ width: `${Math.min(100, staff.utilization?.utilization || 0)}%` }}
                                                    />
                                                </div>
                                                <span className="text-xs text-slate-400">
                                                    {staff.utilization?.scheduled || 0}h/{staff.weekly_capacity_hours || 40}h
                                                </span>
                                            </div>
                                        </div>

                                        {/* Day Columns */}
                                        {weekDays.map((date, dayIdx) => {
                                            const dateString = date.toISOString().split('T')[0];
                                            const dayAssignments = getAssignmentsForCell(staff.id, dateString);
                                            const isAvailable = isStaffAvailable(staff, date.getDay());

                                            return (
                                                <div
                                                    key={dayIdx}
                                                    className={`px-2 py-2 border-r border-slate-200 min-h-[80px] ${
                                                        !isAvailable ? 'bg-slate-100' : ''
                                                    }`}
                                                    onClick={() => {
                                                        if (isAvailable && dayAssignments.length === 0) {
                                                            openAssignModal(null, null, staff, dateString);
                                                        }
                                                    }}
                                                >
                                                    {isAvailable && (
                                                        <div className="space-y-1">
                                                            {dayAssignments.map((assignment) => (
                                                                <button
                                                                    key={assignment.id}
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        openEditModal(assignment);
                                                                    }}
                                                                    className="w-full text-left px-2 py-1 rounded text-xs hover:opacity-80"
                                                                    style={{ backgroundColor: assignment.color || getCategoryColor(assignment.category) }}
                                                                >
                                                                    <div className="font-medium truncate">
                                                                        {assignment.service_type_name}
                                                                    </div>
                                                                    <div className="text-xs opacity-75">
                                                                        {assignment.patient_name}
                                                                    </div>
                                                                    <div className="text-xs opacity-75">
                                                                        {assignment.start_time}-{assignment.end_time}
                                                                    </div>
                                                                </button>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                );
                            })}

                            {(!gridData.staff || gridData.staff.length === 0) && (
                                <div className="text-center py-12 text-slate-400">
                                    <div className="text-sm">No staff match the current filters</div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Assign Care Service Modal */}
            {assignModalOpen && (
                <AssignCareServiceModal
                    isOpen={assignModalOpen}
                    onClose={() => setAssignModalOpen(false)}
                    onAssign={handleCreateAssignment}
                    patient={selectedPatient}
                    serviceTypeId={selectedServiceType}
                    staff={selectedStaff}
                    date={selectedDate}
                    weekRange={weekRange}
                />
            )}

            {/* Edit Assignment Modal */}
            {editModalOpen && selectedAssignment && (
                <EditAssignmentModal
                    isOpen={editModalOpen}
                    onClose={() => setEditModalOpen(false)}
                    assignment={selectedAssignment}
                    onUpdate={handleUpdateAssignment}
                    onCancel={handleCancelAssignment}
                />
            )}
        </div>
    );
};

/**
 * Assign Care Service Modal Component
 */
const AssignCareServiceModal = ({ isOpen, onClose, onAssign, patient, serviceTypeId, staff, date, weekRange }) => {
    const [loading, setLoading] = useState(false);
    const [eligibleStaff, setEligibleStaff] = useState([]);
    const [serviceTypes, setServiceTypes] = useState([]);
    const [patients, setPatients] = useState([]);
    const [form, setForm] = useState({
        patient_id: patient?.patient_id || '',
        service_type_id: serviceTypeId || '',
        staff_id: staff?.id || '',
        date: date || weekRange.start,
        start_time: '09:00',
        duration_minutes: 60,
        notes: '',
    });

    // Fetch service types and patients
    useEffect(() => {
        const fetchData = async () => {
            try {
                const [typesRes, patientsRes] = await Promise.all([
                    api.get('/v2/service-types'),
                    api.get('/patients'),
                ]);
                setServiceTypes(typesRes.data.data || typesRes.data || []);
                setPatients(patientsRes.data.data || patientsRes.data || []);
            } catch (error) {
                console.error('Failed to fetch modal data:', error);
            }
        };
        fetchData();
    }, []);

    // Fetch eligible staff when service type changes
    useEffect(() => {
        if (!form.service_type_id || !form.date || !form.start_time) return;

        const fetchEligible = async () => {
            try {
                const dateTime = `${form.date}T${form.start_time}:00`;
                const params = new URLSearchParams({
                    service_type_id: form.service_type_id,
                    date_time: dateTime,
                    duration_minutes: form.duration_minutes,
                });
                const response = await api.get(`/v2/scheduling/eligible-staff?${params}`);
                setEligibleStaff(response.data.data || []);
            } catch (error) {
                console.error('Failed to fetch eligible staff:', error);
            }
        };
        fetchEligible();
    }, [form.service_type_id, form.date, form.start_time, form.duration_minutes]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.patient_id || !form.service_type_id || !form.staff_id) {
            alert('Please fill in all required fields');
            return;
        }
        setLoading(true);
        try {
            await onAssign(form);
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="px-6 py-4 border-b border-slate-200">
                    <h2 className="text-lg font-bold">Assign Care Service</h2>
                    <p className="text-sm text-slate-500">Schedule a new care assignment</p>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    {/* Patient Selection */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Patient *</label>
                        <select
                            value={form.patient_id}
                            onChange={(e) => setForm(prev => ({ ...prev, patient_id: e.target.value }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            required
                        >
                            <option value="">Select patient...</option>
                            {patients.map(p => (
                                <option key={p.id} value={p.id}>
                                    {p.user?.name || p.name || `Patient ${p.id}`}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Service Type Selection */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Service Type *</label>
                        <select
                            value={form.service_type_id}
                            onChange={(e) => setForm(prev => ({ ...prev, service_type_id: e.target.value }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            required
                        >
                            <option value="">Select service type...</option>
                            {serviceTypes.map(st => (
                                <option key={st.id} value={st.id}>
                                    {st.name} ({st.code})
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Date and Time */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                            <input
                                type="date"
                                value={form.date}
                                onChange={(e) => setForm(prev => ({ ...prev, date: e.target.value }))}
                                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Start Time *</label>
                            <input
                                type="time"
                                value={form.start_time}
                                onChange={(e) => setForm(prev => ({ ...prev, start_time: e.target.value }))}
                                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                                required
                            />
                        </div>
                    </div>

                    {/* Duration */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Duration (minutes) *</label>
                        <select
                            value={form.duration_minutes}
                            onChange={(e) => setForm(prev => ({ ...prev, duration_minutes: parseInt(e.target.value) }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                        >
                            <option value={30}>30 minutes</option>
                            <option value={45}>45 minutes</option>
                            <option value={60}>1 hour</option>
                            <option value={90}>1.5 hours</option>
                            <option value={120}>2 hours</option>
                            <option value={180}>3 hours</option>
                            <option value={240}>4 hours</option>
                        </select>
                    </div>

                    {/* Staff Selection */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">
                            Assigned Staff * {eligibleStaff.length > 0 && (
                                <span className="text-xs text-slate-400">({eligibleStaff.length} eligible)</span>
                            )}
                        </label>
                        <select
                            value={form.staff_id}
                            onChange={(e) => setForm(prev => ({ ...prev, staff_id: e.target.value }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            required
                        >
                            <option value="">Select staff...</option>
                            {eligibleStaff.map(s => (
                                <option key={s.id} value={s.id}>
                                    {s.name} ({s.role?.code || 'Unknown'})
                                </option>
                            ))}
                        </select>
                        {form.service_type_id && eligibleStaff.length === 0 && (
                            <p className="text-xs text-amber-600 mt-1">
                                No eligible staff available for this service at the selected time.
                            </p>
                        )}
                    </div>

                    {/* Notes */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                        <textarea
                            value={form.notes}
                            onChange={(e) => setForm(prev => ({ ...prev, notes: e.target.value }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            rows={2}
                            placeholder="Optional notes..."
                        />
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <Button variant="secondary" onClick={onClose} type="button">
                            Cancel
                        </Button>
                        <Button type="submit" disabled={loading}>
                            {loading ? 'Creating...' : 'Create Assignment'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
};

/**
 * Edit Assignment Modal Component
 */
const EditAssignmentModal = ({ isOpen, onClose, assignment, onUpdate, onCancel }) => {
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({
        date: assignment.date,
        start_time: assignment.start_time,
        duration_minutes: assignment.duration_minutes || 60,
        notes: assignment.notes || '',
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await onUpdate(assignment.id, form);
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
                <div className="px-6 py-4 border-b border-slate-200">
                    <h2 className="text-lg font-bold">Edit Assignment</h2>
                    <p className="text-sm text-slate-500">
                        {assignment.service_type_name} for {assignment.patient_name}
                    </p>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Date</label>
                            <input
                                type="date"
                                value={form.date}
                                onChange={(e) => setForm(prev => ({ ...prev, date: e.target.value }))}
                                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Start Time</label>
                            <input
                                type="time"
                                value={form.start_time}
                                onChange={(e) => setForm(prev => ({ ...prev, start_time: e.target.value }))}
                                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Duration (minutes)</label>
                        <select
                            value={form.duration_minutes}
                            onChange={(e) => setForm(prev => ({ ...prev, duration_minutes: parseInt(e.target.value) }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                        >
                            <option value={30}>30 minutes</option>
                            <option value={45}>45 minutes</option>
                            <option value={60}>1 hour</option>
                            <option value={90}>1.5 hours</option>
                            <option value={120}>2 hours</option>
                            <option value={180}>3 hours</option>
                            <option value={240}>4 hours</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                        <textarea
                            value={form.notes}
                            onChange={(e) => setForm(prev => ({ ...prev, notes: e.target.value }))}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            rows={2}
                        />
                    </div>

                    <div className="flex justify-between pt-4 border-t border-slate-200">
                        <Button
                            variant="danger"
                            onClick={() => onCancel(assignment.id)}
                            type="button"
                        >
                            Cancel Assignment
                        </Button>
                        <div className="flex gap-3">
                            <Button variant="secondary" onClick={onClose} type="button">
                                Close
                            </Button>
                            <Button type="submit" disabled={loading}>
                                {loading ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default SchedulingPage;
