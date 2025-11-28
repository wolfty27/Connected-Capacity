import React, { useState } from 'react';
import { SchedulingHeader } from './SchedulingHeader';
import { UnscheduledPanel } from './UnscheduledPanel';
import { SchedulingGrid } from './SchedulingGrid';
import { SchedulingFooter } from './SchedulingFooter';
import type { StaffMember, UnscheduledCareItem, Assignment, FilterState } from '../types';

interface SchedulingDashboardProps {
  view: 'week' | 'month';
  onViewChange: (view: 'week' | 'month') => void;
  staffData: StaffMember[];
  unscheduledCare: UnscheduledCareItem[];
  assignments: Assignment[];
  selectedStaffId: string | null;
  selectedPatientId: string | null;
  onClearStaffFilter: () => void;
  onClearPatientFilter: () => void;
  onAssignFromUnscheduled: (item: UnscheduledCareItem, serviceTypeId: string) => void;
  onAssignFromGrid: (staffId: string, date: string, time: string) => void;
  onEditAssignment: (assignment: Assignment) => void;
}

export function SchedulingDashboard({
  view,
  onViewChange,
  staffData,
  unscheduledCare,
  assignments,
  selectedStaffId,
  selectedPatientId,
  onClearStaffFilter,
  onClearPatientFilter,
  onAssignFromUnscheduled,
  onAssignFromGrid,
  onEditAssignment,
}: SchedulingDashboardProps) {
  const [filters, setFilters] = useState<FilterState>({
    organization: 'all',
    roles: [],
    employmentTypes: [],
    status: ['active'],
  });

  const [weekStartDate, setWeekStartDate] = useState<Date>(() => {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
    const monday = new Date(today);
    monday.setDate(today.getDate() + diff);
    return monday;
  });

  // Filter staff based on filters and selected staff ID
  const filteredStaff = staffData.filter(staff => {
    if (selectedStaffId && staff.id !== selectedStaffId) return false;
    
    if (filters.organization !== 'all' && staff.organization !== filters.organization) {
      return false;
    }
    
    if (filters.roles.length > 0 && !filters.roles.includes(staff.role.id)) {
      return false;
    }
    
    if (filters.employmentTypes.length > 0 && !filters.employmentTypes.includes(staff.employmentType.id)) {
      return false;
    }
    
    if (filters.status.length > 0 && !filters.status.includes(staff.status)) {
      return false;
    }
    
    return true;
  });

  // Filter unscheduled care based on selected patient
  const filteredUnscheduledCare = selectedPatientId
    ? unscheduledCare.filter(item => item.patientId === selectedPatientId)
    : unscheduledCare;

  return (
    <div className="flex flex-col flex-1">
      <SchedulingHeader
        view={view}
        onViewChange={onViewChange}
        filters={filters}
        onFiltersChange={setFilters}
        weekStartDate={weekStartDate}
        onWeekChange={setWeekStartDate}
        selectedStaffId={selectedStaffId}
        selectedPatientId={selectedPatientId}
        onClearStaffFilter={onClearStaffFilter}
        onClearPatientFilter={onClearPatientFilter}
      />
      
      <div className="flex flex-1 overflow-hidden">
        <UnscheduledPanel
          items={filteredUnscheduledCare}
          onAssign={onAssignFromUnscheduled}
        />
        
        <div className="flex-1 overflow-auto">
          <SchedulingGrid
            view={view}
            staff={filteredStaff}
            assignments={assignments}
            weekStartDate={weekStartDate}
            onAssignFromGrid={onAssignFromGrid}
            onEditAssignment={onEditAssignment}
            highlightedStaffId={selectedStaffId}
          />
        </div>
      </div>
      
      <SchedulingFooter />
    </div>
  );
}
