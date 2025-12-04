import React, { useState, useEffect, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import api from '../../../../services/api';
import Button from '../../../../components/UI/Button';
import Select from '../../../../components/UI/Select';
import PatientTimeline from '../../../../components/scheduling/PatientTimeline';
import ExplanationModal from '../../../../components/scheduling/ExplanationModal';
import SuggestionRow from '../../../../components/scheduling/SuggestionRow';
import { useAutoAssign } from '../../../../hooks/useAutoAssign';
import { useSchedulerContext, SCHEDULE_SUB_MODES } from '../SchedulerContext';
import { useSchedulerData } from '../hooks/useSchedulerData';

/**
 * ScheduleTab
 * 
 * Primary working surface with the calendar view.
 * This is the migrated content from the original SchedulingPage.jsx.
 * 
 * Features:
 * - Week grid view with staff rows and day columns
 * - Unscheduled care panel showing patients with unmet service needs
 * - Quick Navigation for Staff/Patient/Full Dashboard views
 * - AI Auto-Assign with suggestion rows and explanation modal
 * - Assign/Edit Assignment modals
 */
const ScheduleTab = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const {
    isSspoMode,
    weekRange,
    weekDays,
    goToPreviousWeek,
    goToNextWeek,
    goToCurrentWeek,
    weekOffset,
    filters,
    updateFilter,
    clearFilters: clearContextFilters,
    hasFilters: contextHasFilters,
    isNavCollapsed,
    setIsNavCollapsed,
    isUnscheduledCollapsed,
    setIsUnscheduledCollapsed,
    scheduleSubMode,
    setScheduleSubMode,
  } = useSchedulerContext();

  // URL params for deep links
  const staffIdParam = searchParams.get('staff_id');
  const patientIdParam = searchParams.get('patient_id');

  // Data hook
  const {
    loading,
    gridData,
    requirements,
    navExamples,
    roles,
    employmentTypes,
    allStaff,
    allPatients,
    loadAllData,
    refreshAll,
    getAssignmentsForCell,
    isStaffAvailable,
    getCategoryColor,
    formatDate,
  } = useSchedulerData();

  // Modals
  const [assignModalOpen, setAssignModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [selectedAssignment, setSelectedAssignment] = useState(null);
  const [selectedPatient, setSelectedPatient] = useState(null);
  const [selectedServiceType, setSelectedServiceType] = useState(null);
  const [selectedStaff, setSelectedStaff] = useState(null);
  const [selectedDate, setSelectedDate] = useState(null);

  // Auto-Assign AI state
  const [explanationModalOpen, setExplanationModalOpen] = useState(false);
  const [selectedSuggestion, setSelectedSuggestion] = useState(null);
  const [acceptingId, setAcceptingId] = useState(null);

  // Auto-Assign AI hook
  const {
    suggestions,
    isLoading: autoAssignLoading,
    hasSuggestions,
    generateSuggestions,
    getExplanation,
    acceptSuggestion,
    getSuggestionFor,
    clearSuggestions,
  } = useAutoAssign(weekRange.start, null);

  // Initial load
  useEffect(() => {
    loadAllData(staffIdParam, patientIdParam);
  }, [loadAllData, staffIdParam, patientIdParam, weekRange.start]);

  // Sync filter state with context
  useEffect(() => {
    updateFilter('roleFilter', filters.roleFilter);
    updateFilter('empTypeFilter', filters.empTypeFilter);
  }, []);

  // ============================================
  // Handlers
  // ============================================

  const openAssignModal = (patient, serviceTypeId, staff = null, date = null) => {
    setSelectedPatient(patient);
    setSelectedServiceType(serviceTypeId);
    setSelectedStaff(staff);
    setSelectedDate(date);
    setAssignModalOpen(true);
  };

  const openEditModal = (assignment) => {
    setSelectedAssignment(assignment);
    setEditModalOpen(true);
  };

  const handleCreateAssignment = async (data) => {
    try {
      await api.post('/v2/scheduling/assignments', data);
      setAssignModalOpen(false);
      refreshAll(staffIdParam, patientIdParam);
    } catch (error) {
      console.error('Failed to create assignment:', error);
      alert(error.response?.data?.message || 'Failed to create assignment');
    }
  };

  const handleUpdateAssignment = async (id, data) => {
    try {
      await api.patch(`/v2/scheduling/assignments/${id}`, data);
      setEditModalOpen(false);
      refreshAll(staffIdParam, patientIdParam);
    } catch (error) {
      console.error('Failed to update assignment:', error);
      alert(error.response?.data?.message || 'Failed to update assignment');
    }
  };

  const handleCancelAssignment = async (id) => {
    if (!confirm('Are you sure you want to cancel this assignment?')) return;
    try {
      await api.delete(`/v2/scheduling/assignments/${id}`);
      setEditModalOpen(false);
      refreshAll(staffIdParam, patientIdParam);
    } catch (error) {
      console.error('Failed to cancel assignment:', error);
    }
  };

  const clearFilters = () => {
    clearContextFilters();
    navigate(window.location.pathname);
  };

  const handleAutoAssign = async () => {
    try {
      await generateSuggestions();
    } catch (error) {
      console.error('Failed to generate suggestions:', error);
    }
  };

  const handleAcceptSuggestion = async (suggestion) => {
    const suggestionKey = `${suggestion.patient_id}-${suggestion.service_type_id}`;
    setAcceptingId(suggestionKey);
    try {
      const startTime = weekRange.start + 'T09:00:00';
      const endTime = new Date(new Date(startTime).getTime() + (suggestion.duration_minutes || 60) * 60000).toISOString();
      await acceptSuggestion(suggestion, startTime, endTime);
      refreshAll(staffIdParam, patientIdParam);
    } catch (error) {
      console.error('Failed to accept suggestion:', error);
      alert(error.message || 'Failed to accept suggestion');
    } finally {
      setAcceptingId(null);
    }
  };

  const handleOpenExplanation = (suggestion) => {
    setSelectedSuggestion(suggestion);
    setExplanationModalOpen(true);
  };

  const hasFilters = staffIdParam || patientIdParam || filters.roleFilter || filters.empTypeFilter || contextHasFilters;

  // ============================================
  // Render
  // ============================================

  return (
    <div className="space-y-4">
      {/* Filters Bar */}
      <div className="bg-white border border-slate-200 rounded-lg px-4 py-3">
        <div className="flex flex-wrap items-center gap-4">
          <Select
            placeholder="Filter: By Role"
            value={filters.roleFilter}
            onChange={(e) => updateFilter('roleFilter', e.target.value)}
            options={[
              { value: '', label: 'All Roles' },
              ...roles.map(r => ({ value: r.code, label: `${r.code} - ${r.name}` })),
            ]}
            className="w-48"
          />
          <Select
            placeholder="Filter: By Employment Type"
            value={filters.empTypeFilter}
            onChange={(e) => updateFilter('empTypeFilter', e.target.value)}
            options={[
              { value: '', label: 'All Employment Types' },
              ...employmentTypes.map(t => ({ value: t.code, label: t.name })),
            ]}
            className="w-64"
          />
          
          {/* Sub-mode toggle */}
          <div className="flex items-center gap-1 ml-auto">
            <button
              onClick={() => setScheduleSubMode(SCHEDULE_SUB_MODES.STAFF_LANES)}
              className={`px-3 py-1.5 text-xs font-medium rounded-l-md border transition-colors ${
                scheduleSubMode === SCHEDULE_SUB_MODES.STAFF_LANES
                  ? 'bg-blue-50 border-blue-300 text-blue-700'
                  : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
              }`}
            >
              Staff Lanes
            </button>
            <button
              onClick={() => setScheduleSubMode(SCHEDULE_SUB_MODES.LIST)}
              className={`px-3 py-1.5 text-xs font-medium rounded-r-md border-t border-r border-b transition-colors ${
                scheduleSubMode === SCHEDULE_SUB_MODES.LIST
                  ? 'bg-blue-50 border-blue-300 text-blue-700'
                  : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
              }`}
            >
              List View
            </button>
          </div>
          
          {hasFilters && (
            <Button variant="ghost" size="sm" onClick={clearFilters}>
              Clear Filters
            </Button>
          )}
          {(staffIdParam || patientIdParam) && (
            <span className="text-sm text-slate-500 flex items-center gap-2">
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

      {/* Quick Navigation Card */}
      <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <button
          onClick={() => setIsNavCollapsed(!isNavCollapsed)}
          className="w-full px-4 py-3 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition-colors"
        >
          <h3 className="text-sm font-bold text-slate-700">Quick Navigation</h3>
          <svg
            className={`w-4 h-4 text-slate-500 transition-transform ${isNavCollapsed ? '' : 'rotate-180'}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        {!isNavCollapsed && (
          <div className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {/* Staff-centric view */}
              <div className={`p-3 rounded-lg border ${staffIdParam ? 'bg-blue-100 border-blue-400' : 'bg-blue-50 border-blue-200'}`}>
                <div className="text-xs font-bold text-blue-800 mb-2">
                  Staff-Centric View
                  {staffIdParam && <span className="ml-1 text-blue-600">(Active)</span>}
                </div>
                <div className="relative">
                  <select
                    value={staffIdParam || ''}
                    onChange={(e) => {
                      if (e.target.value) {
                        setSearchParams({ staff_id: e.target.value });
                      } else {
                        clearFilters();
                      }
                    }}
                    className="appearance-none w-full text-sm border border-blue-300 rounded px-2 py-1.5 pr-10 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Select a staff member...</option>
                    {allStaff.map((staff) => (
                      <option key={staff.id} value={staff.id}>
                        {staff.name} ({staff.role?.code || staff.organization_role || 'Staff'})
                      </option>
                    ))}
                  </select>
                  <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg className="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
              </div>

              {/* Patient-centric view */}
              <div className={`p-3 rounded-lg border ${patientIdParam ? 'bg-green-100 border-green-400' : 'bg-green-50 border-green-200'}`}>
                <div className="text-xs font-bold text-green-800 mb-2">
                  Patient-Centric View
                  {patientIdParam && <span className="ml-1 text-green-600">(Active)</span>}
                </div>
                <div className="relative">
                  <select
                    value={patientIdParam || ''}
                    onChange={(e) => {
                      if (e.target.value) {
                        setSearchParams({ patient_id: e.target.value });
                      } else {
                        clearFilters();
                      }
                    }}
                    className="appearance-none w-full text-sm border border-green-300 rounded px-2 py-1.5 pr-10 bg-white focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                    <option value="">Select a patient...</option>
                    {allPatients.map((patient) => (
                      <option key={patient.id} value={patient.id}>
                        {patient.name || patient.user?.name || `Patient ${patient.id}`}
                      </option>
                    ))}
                  </select>
                  <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg className="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
              </div>

              {/* Full dashboard view */}
              <div className={`p-3 rounded-lg border ${!hasFilters ? 'bg-slate-100 border-slate-400' : 'bg-slate-50 border-slate-200'}`}>
                <div className="text-xs font-bold text-slate-700 mb-2">
                  Full Dashboard View
                  {!hasFilters && <span className="ml-1 text-slate-500">(Active)</span>}
                </div>
                <button
                  onClick={clearFilters}
                  className={`w-full text-sm px-3 py-1.5 rounded ${
                    hasFilters
                      ? 'bg-slate-600 text-white hover:bg-slate-700'
                      : 'bg-slate-200 text-slate-600 cursor-default'
                  }`}
                  disabled={!hasFilters}
                >
                  {hasFilters ? 'Clear filters & view all' : 'Viewing all schedules'}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Unscheduled Care Panel */}
      <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div className="bg-amber-50 border-b border-amber-200 px-4 py-3 flex items-center justify-between">
          <button
            onClick={() => setIsUnscheduledCollapsed(!isUnscheduledCollapsed)}
            className="text-left flex-1 hover:opacity-80 transition-opacity"
          >
            <h2 className="text-sm font-bold text-amber-800">Unscheduled Care</h2>
            <p className="text-xs text-amber-600">
              {requirements.summary?.patients_with_needs || 0} patients need scheduling
              {requirements.summary?.total_remaining_hours > 0 && (
                <span className="ml-2">
                  ({requirements.summary.total_remaining_hours}h + {requirements.summary.total_remaining_visits || 0} visits remaining)
                </span>
              )}
            </p>
          </button>
          <div className="flex items-center gap-2">
            {!isUnscheduledCollapsed && requirements.data?.length > 0 && (
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  handleAutoAssign();
                }}
                disabled={autoAssignLoading}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                  hasSuggestions
                    ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                    : 'bg-blue-600 text-white hover:bg-blue-700'
                } disabled:opacity-50`}
              >
                {autoAssignLoading ? (
                  <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                ) : (
                  <span>⚡</span>
                )}
                <span>{hasSuggestions ? `${suggestions.length} Suggestions` : 'Auto Assign'}</span>
              </button>
            )}
            {hasSuggestions && (
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  clearSuggestions();
                }}
                className="p-1.5 text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-100"
                title="Clear suggestions"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            )}
            <button
              onClick={() => setIsUnscheduledCollapsed(!isUnscheduledCollapsed)}
              className="p-1 hover:bg-amber-100 rounded transition-colors"
            >
              <svg
                className={`w-4 h-4 text-amber-600 transition-transform ${isUnscheduledCollapsed ? '' : 'rotate-180'}`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>
        {!isUnscheduledCollapsed && requirements.data?.length > 0 ? (
          <div className="overflow-x-auto p-4">
            <div className="flex gap-4 min-w-max">
              {requirements.data?.map((item) => (
                <div
                  key={item.patient_id}
                  className="bg-slate-50 rounded-lg border border-slate-200 p-3 w-72 flex-shrink-0"
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
                    {item.services?.slice(0, 3).map((service) => {
                      const suggestion = getSuggestionFor(item.patient_id, service.service_type_id);
                      const suggestionKey = `${item.patient_id}-${service.service_type_id}`;
                      return (
                        <div key={service.service_type_id} className="space-y-1">
                          <div className="flex items-center justify-between">
                            <div className="flex-1">
                              <div className="text-xs font-medium">{service.service_type_name}</div>
                              <div className="text-xs text-slate-500">
                                {service.scheduled}/{service.required} {service.unit_type}
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
                          {suggestion && suggestion.match_status !== 'none' && (
                            <SuggestionRow
                              suggestion={suggestion}
                              onAccept={handleAcceptSuggestion}
                              onManual={() => openAssignModal(item, service.service_type_id)}
                              onExplain={handleOpenExplanation}
                              isAccepting={acceptingId === suggestionKey}
                            />
                          )}
                        </div>
                      );
                    })}
                    {item.services?.length > 3 && (
                      <div className="text-xs text-slate-400 text-center">
                        +{item.services.length - 3} more services
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : !isUnscheduledCollapsed ? (
          <div className="text-center py-6 text-emerald-600">
            <div className="text-2xl mb-1">&#10003;</div>
            <div className="text-sm font-medium">All required care scheduled for this week</div>
          </div>
        ) : null}
      </div>

      {/* Schedule Grid or Patient Timeline */}
      <div>
        {patientIdParam ? (
          <PatientTimeline
            assignments={gridData.assignments?.map(a => ({
              ...a,
              staff_name: gridData.staff?.find(s => s.id === a.staff_id)?.name || 'Unknown',
              staff_role: gridData.staff?.find(s => s.id === a.staff_id)?.role?.code,
            }))}
            weekDays={weekDays}
            patientName={navExamples.patient?.name || `Patient #${patientIdParam}`}
            onEditAssignment={openEditModal}
          />
        ) : scheduleSubMode === SCHEDULE_SUB_MODES.LIST ? (
          // List View Mode
          <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-200 bg-slate-50">
              <h3 className="text-sm font-bold text-slate-700">Appointments List</h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-slate-50 border-b border-slate-200">
                  <tr>
                    <th className="text-left px-4 py-2 text-slate-600 font-medium">Date</th>
                    <th className="text-left px-4 py-2 text-slate-600 font-medium">Time</th>
                    <th className="text-left px-4 py-2 text-slate-600 font-medium">Patient</th>
                    <th className="text-left px-4 py-2 text-slate-600 font-medium">Service</th>
                    <th className="text-left px-4 py-2 text-slate-600 font-medium">Staff</th>
                    <th className="text-left px-4 py-2 text-slate-600 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {gridData.assignments?.length > 0 ? (
                    gridData.assignments.map((assignment) => (
                      <tr
                        key={assignment.id}
                        className="hover:bg-slate-50 cursor-pointer"
                        onClick={() => openEditModal(assignment)}
                      >
                        <td className="px-4 py-2">{assignment.date}</td>
                        <td className="px-4 py-2">{assignment.start_time} - {assignment.end_time}</td>
                        <td className="px-4 py-2">{assignment.patient_name}</td>
                        <td className="px-4 py-2">{assignment.service_type_name}</td>
                        <td className="px-4 py-2">
                          {gridData.staff?.find(s => s.id === assignment.staff_id)?.name || 'Unknown'}
                        </td>
                        <td className="px-4 py-2">
                          <span className={`px-2 py-0.5 text-xs rounded-full ${
                            assignment.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                            assignment.status === 'planned' ? 'bg-blue-100 text-blue-700' :
                            assignment.status === 'cancelled' ? 'bg-slate-100 text-slate-500' :
                            'bg-amber-100 text-amber-700'
                          }`}>
                            {assignment.status}
                          </span>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-slate-400">
                        No appointments for this week
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
          // Staff Lanes Mode (default grid)
          <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
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
        )}
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

      {/* AI Explanation Modal */}
      <ExplanationModal
        isOpen={explanationModalOpen}
        onClose={() => {
          setExplanationModalOpen(false);
          setSelectedSuggestion(null);
        }}
        suggestion={selectedSuggestion}
        getExplanation={getExplanation}
      />
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

export default ScheduleTab;

