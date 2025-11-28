import React from 'react';
import type { StaffMember, Assignment } from '../types';

interface SchedulingGridProps {
  view: 'week' | 'month';
  staff: StaffMember[];
  assignments: Assignment[];
  weekStartDate: Date;
  onAssignFromGrid: (staffId: string, date: string, time: string) => void;
  onEditAssignment: (assignment: Assignment) => void;
  highlightedStaffId: string | null;
}

export function SchedulingGrid({
  view,
  staff,
  assignments,
  weekStartDate,
  onAssignFromGrid,
  onEditAssignment,
  highlightedStaffId,
}: SchedulingGridProps) {
  const weekDays = Array.from({ length: 7 }, (_, i) => {
    const date = new Date(weekStartDate);
    date.setDate(weekStartDate.getDate() + i);
    return date;
  });

  const getDayLabel = (date: Date) => {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return days[date.getDay()];
  };

  const getDateString = (date: Date) => {
    return date.toISOString().split('T')[0];
  };

  const getAssignmentsForStaffAndDay = (staffId: string, date: string) => {
    return assignments.filter(
      a => a.staffId === staffId && a.date === date && a.status !== 'cancelled'
    );
  };

  const isStaffAvailable = (staff: StaffMember, dayOfWeek: number) => {
    return staff.availability.some(
      block => block.dayOfWeek === dayOfWeek && block.isAvailable
    );
  };

  const getServiceTypeLabel = (date: Date) => {
    // Get unique service categories for this day across all assignments
    const dayString = getDateString(date);
    const dayAssignments = assignments.filter(a => a.date === dayString);
    const categories = new Set(dayAssignments.map(a => a.category));
    
    if (categories.size === 0) return null;
    
    const categoryLabels: Record<string, string> = {
      'nursing': 'SN Visit',
      'rehab': 'PT Eval',
      'psw': 'PSW',
      'behaviour': 'Wound Care',
      'other': 'OT Eval',
    };
    
    return Array.from(categories).map(cat => categoryLabels[cat] || cat).join(', ');
  };

  return (
    <div className="bg-white overflow-x-auto">
      {/* Column Headers */}
      <div className="grid grid-cols-8 border-b border-gray-200 sticky top-0 bg-white z-10 min-w-[1200px]">
        <div className="px-4 py-3 border-r border-gray-200">
          <div className="text-xs text-gray-600">Staff Member</div>
        </div>
        {weekDays.map((date, index) => {
          const serviceLabel = getServiceTypeLabel(date);
          return (
            <div key={index} className="px-4 py-3 border-r border-gray-200">
              <div className="text-xs">{getDayLabel(date)} {date.getDate()}</div>
              {serviceLabel && (
                <div className="text-xs text-blue-600 mt-0.5">{serviceLabel}</div>
              )}
            </div>
          );
        })}
      </div>

      {/* Staff Rows */}
      {staff.map((staffMember) => {
        const isHighlighted = highlightedStaffId === staffMember.id;
        
        return (
          <div
            key={staffMember.id}
            className={`grid grid-cols-8 border-b border-gray-100 min-w-[1200px] ${
              isHighlighted ? 'bg-blue-50' : 'hover:bg-gray-50'
            }`}
          >
            {/* Staff Info Column */}
            <div className="px-4 py-4 border-r border-gray-200">
              <div className="text-sm mb-0.5">{staffMember.name}</div>
              <div className="text-xs text-gray-600 mb-1">
                {staffMember.role.displayName}, {staffMember.employmentType.displayName}
              </div>
              {(() => {
                // Calculate total scheduled hours for this staff member
                const staffAssignments = assignments.filter(a => a.staffId === staffMember.id);
                const totalMinutes = staffAssignments.reduce((sum, a) => sum + a.durationMinutes, 0);
                const totalHours = totalMinutes / 60;
                const capacityPercent = (totalHours / staffMember.weeklyCapacityHours) * 100;
                
                return (
                  <div className="mt-1">
                    <div className="flex items-center gap-2">
                      <div className="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                        <div 
                          className={`h-full ${
                            capacityPercent > 90 ? 'bg-red-500' : 
                            capacityPercent > 75 ? 'bg-yellow-500' : 
                            'bg-green-500'
                          }`}
                          style={{ width: `${Math.min(capacityPercent, 100)}%` }}
                        ></div>
                      </div>
                      <span className="text-xs text-gray-500 whitespace-nowrap">
                        {totalHours.toFixed(1)}/{staffMember.weeklyCapacityHours}h
                      </span>
                    </div>
                  </div>
                );
              })()}
            </div>

            {/* Day Columns */}
            {weekDays.map((date, dayIndex) => {
              const dayOfWeek = date.getDay();
              const dateString = getDateString(date);
              const dayAssignments = getAssignmentsForStaffAndDay(staffMember.id, dateString);
              const isAvailable = isStaffAvailable(staffMember, dayOfWeek);

              return (
                <div
                  key={dayIndex}
                  className="px-2 py-2 border-r border-b border-gray-200 min-h-[80px] bg-white"
                  onClick={() => {
                    if (isAvailable && dayAssignments.length === 0) {
                      onAssignFromGrid(staffMember.id, dateString, '09:00');
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
                            onEditAssignment(assignment);
                          }}
                          className="w-full text-left px-2 py-1 rounded text-xs hover:opacity-80 transition-opacity"
                          style={{ backgroundColor: assignment.color }}
                        >
                          <div className="flex items-start justify-between gap-1">
                            <span className="truncate">{assignment.serviceTypeName}</span>
                            {assignment.conflicts?.includes('error') && (
                              <span className="text-red-600 text-xs flex-shrink-0">error</span>
                            )}
                          </div>
                          <div className="text-xs opacity-75 mt-0.5">
                            {assignment.startTime}-{assignment.endTime?.substring(0, 5)}
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

      {staff.length === 0 && (
        <div className="text-center py-12 text-gray-500">
          <div className="text-sm">No staff members match the current filters</div>
        </div>
      )}
    </div>
  );
}
