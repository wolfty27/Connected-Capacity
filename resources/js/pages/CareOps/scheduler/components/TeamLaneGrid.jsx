import React, { useMemo, useState } from 'react';

/**
 * TeamLaneGrid
 * 
 * Renders staff in grouped "Team Lanes" based on role categories.
 * 
 * Team Lane Logic:
 * - High-population roles (PSW, RPN): Individual lanes per staff member
 * - Low-population roles (OT, PT, SLP, SW, RD): Grouped by category
 * - Individual threshold: roles with 3+ staff get individual lanes
 * 
 * @see docs/CC21 Scheduler 2.0 prelim – Design & Implementation Spec.txt
 */
const TeamLaneGrid = ({
  staff,
  assignments,
  weekDays,
  onCellClick,
  onEditAssignment,
  formatDate,
  getCategoryColor,
  getAssignmentsForCell,
  isStaffAvailable,
  highlightedStaffId,
}) => {
  const [expandedLanes, setExpandedLanes] = useState(new Set());
  const [showAllLanes, setShowAllLanes] = useState(false);

  // Group staff by role category and determine lane structure
  const teamLanes = useMemo(() => {
    if (!staff || staff.length === 0) return [];

    // Count staff per role
    const roleCounts = {};
    staff.forEach(s => {
      const roleCode = s.role?.code || 'OTHER';
      roleCounts[roleCode] = (roleCounts[roleCode] || 0) + 1;
    });

    // Group staff by category
    const categoryGroups = {};
    staff.forEach(s => {
      const category = s.role?.category || 'other';
      if (!categoryGroups[category]) {
        categoryGroups[category] = [];
      }
      categoryGroups[category].push(s);
    });

    // Build lane structure
    const lanes = [];
    const INDIVIDUAL_THRESHOLD = 3; // Roles with 3+ staff get individual lanes

    // Category order for display
    const categoryOrder = [
      'personal_support', // PSW - usually high population
      'nursing',          // RN, RPN
      'allied_health',    // OT, PT, SLP, SW, RD, RT
      'community_support',
      'administrative',
      'other',
    ];

    categoryOrder.forEach(category => {
      const staffInCategory = categoryGroups[category] || [];
      if (staffInCategory.length === 0) return;

      // Check if any role in this category exceeds threshold
      const roleBreakdown = {};
      staffInCategory.forEach(s => {
        const roleCode = s.role?.code || 'OTHER';
        if (!roleBreakdown[roleCode]) {
          roleBreakdown[roleCode] = [];
        }
        roleBreakdown[roleCode].push(s);
      });

      // If category has high-population roles, show individual lanes
      const hasHighPopulationRole = Object.values(roleBreakdown).some(
        arr => arr.length >= INDIVIDUAL_THRESHOLD
      );

      if (hasHighPopulationRole) {
        // Individual lanes for each staff member
        staffInCategory
          .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
          .forEach(s => {
            lanes.push({
              type: 'individual',
              id: `staff-${s.id}`,
              name: s.name,
              roleCode: s.role?.code || '-',
              category,
              staff: [s],
              isExpanded: true,
            });
          });
      } else {
        // Grouped lane for low-population category
        const categoryLabel = getCategoryLabel(category);
        lanes.push({
          type: 'grouped',
          id: `group-${category}`,
          name: categoryLabel,
          category,
          staff: staffInCategory.sort((a, b) => (a.name || '').localeCompare(b.name || '')),
          isExpanded: expandedLanes.has(`group-${category}`),
          roleBreakdown: Object.entries(roleBreakdown).map(([code, members]) => ({
            code,
            count: members.length,
          })),
        });
      }
    });

    return lanes;
  }, [staff, expandedLanes]);

  const toggleLaneExpand = (laneId) => {
    setExpandedLanes(prev => {
      const next = new Set(prev);
      if (next.has(laneId)) {
        next.delete(laneId);
      } else {
        next.add(laneId);
      }
      return next;
    });
  };

  // Display lanes (limited unless showAllLanes)
  const displayLanes = showAllLanes ? teamLanes : teamLanes.slice(0, 10);

  return (
    <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
      {/* Header Row */}
      <div className="grid grid-cols-8 border-b border-slate-200 bg-slate-50 sticky top-0 z-10 min-w-[1000px]">
        <div className="px-4 py-3 border-r border-slate-200">
          <div className="flex items-center justify-between">
            <div className="text-xs font-bold text-slate-600">Team Lane</div>
            <span className="text-xs text-slate-400">{staff.length} staff</span>
          </div>
        </div>
        {weekDays.map((date, idx) => (
          <div key={idx} className="px-4 py-3 border-r border-slate-200 text-center">
            <div className="text-xs font-bold text-slate-600">{formatDate(date)}</div>
          </div>
        ))}
      </div>

      {/* Lane Rows */}
      <div className="overflow-x-auto">
        {displayLanes.map((lane) => (
          <LaneRow
            key={lane.id}
            lane={lane}
            weekDays={weekDays}
            onToggleExpand={() => toggleLaneExpand(lane.id)}
            onCellClick={onCellClick}
            onEditAssignment={onEditAssignment}
            getCategoryColor={getCategoryColor}
            getAssignmentsForCell={getAssignmentsForCell}
            isStaffAvailable={isStaffAvailable}
            highlightedStaffId={highlightedStaffId}
          />
        ))}

        {teamLanes.length > 10 && !showAllLanes && (
          <div className="px-4 py-3 bg-slate-50 border-t border-slate-200 text-center">
            <button
              onClick={() => setShowAllLanes(true)}
              className="text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              Show {teamLanes.length - 10} more lanes...
            </button>
          </div>
        )}

        {displayLanes.length === 0 && (
          <div className="text-center py-12 text-slate-400">
            <div className="text-sm">No staff match the current filters</div>
          </div>
        )}
      </div>
    </div>
  );
};

/**
 * LaneRow Component
 * Renders a single lane row (individual or grouped)
 */
const LaneRow = ({
  lane,
  weekDays,
  onToggleExpand,
  onCellClick,
  onEditAssignment,
  getCategoryColor,
  getAssignmentsForCell,
  isStaffAvailable,
  highlightedStaffId,
}) => {
  if (lane.type === 'individual') {
    // Individual staff lane
    const staff = lane.staff[0];
    const isHighlighted = highlightedStaffId === staff.id;

    return (
      <div
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
          <UtilizationBar staff={staff} />
        </div>

        {/* Day Columns */}
        {weekDays.map((date, dayIdx) => (
          <DayCell
            key={dayIdx}
            staff={staff}
            date={date}
            getAssignmentsForCell={getAssignmentsForCell}
            isStaffAvailable={isStaffAvailable}
            getCategoryColor={getCategoryColor}
            onCellClick={onCellClick}
            onEditAssignment={onEditAssignment}
          />
        ))}
      </div>
    );
  }

  // Grouped lane
  return (
    <>
      {/* Group Header Row */}
      <div className="grid grid-cols-8 border-b border-slate-200 bg-slate-100 min-w-[1000px]">
        <div className="px-4 py-2 border-r border-slate-200">
          <button
            onClick={onToggleExpand}
            className="flex items-center gap-2 w-full text-left"
          >
            <svg
              className={`w-4 h-4 text-slate-500 transition-transform ${lane.isExpanded ? 'rotate-90' : ''}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
            <div>
              <div className="text-sm font-bold text-slate-700">{lane.name}</div>
              <div className="text-xs text-slate-500">
                {lane.staff.length} staff • {lane.roleBreakdown?.map(r => `${r.count} ${r.code}`).join(', ')}
              </div>
            </div>
          </button>
        </div>
        {weekDays.map((_, idx) => (
          <div key={idx} className="border-r border-slate-200 bg-slate-100" />
        ))}
      </div>

      {/* Expanded Staff Rows */}
      {lane.isExpanded && lane.staff.map((staff) => (
        <div
          key={staff.id}
          className="grid grid-cols-8 border-b border-slate-100 min-w-[1000px] hover:bg-slate-50"
        >
          <div className="px-4 py-2 border-r border-slate-200 pl-10">
            <div className="text-sm font-medium">{staff.name}</div>
            <div className="text-xs text-slate-500">
              {staff.role?.code || '-'}
            </div>
            <UtilizationBar staff={staff} compact />
          </div>
          {weekDays.map((date, dayIdx) => (
            <DayCell
              key={dayIdx}
              staff={staff}
              date={date}
              getAssignmentsForCell={getAssignmentsForCell}
              isStaffAvailable={isStaffAvailable}
              getCategoryColor={getCategoryColor}
              onCellClick={onCellClick}
              onEditAssignment={onEditAssignment}
              compact
            />
          ))}
        </div>
      ))}
    </>
  );
};

/**
 * DayCell Component
 * Renders a single day cell for a staff member
 */
const DayCell = ({
  staff,
  date,
  getAssignmentsForCell,
  isStaffAvailable,
  getCategoryColor,
  onCellClick,
  onEditAssignment,
  compact = false,
}) => {
  const dateString = date.toISOString().split('T')[0];
  const dayAssignments = getAssignmentsForCell(staff.id, dateString);
  const isAvailable = isStaffAvailable(staff, date.getDay());
  const isWeekend = date.getDay() === 0 || date.getDay() === 6;

  return (
    <div
      className={`px-2 py-2 border-r border-slate-200 ${compact ? 'min-h-[60px]' : 'min-h-[80px]'} ${
        !isAvailable ? 'bg-slate-100' : isWeekend ? 'bg-slate-50' : ''
      }`}
      onClick={() => {
        if (isAvailable && dayAssignments.length === 0) {
          onCellClick?.(null, null, staff, dateString);
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
                onEditAssignment?.(assignment);
              }}
              className={`w-full text-left px-2 py-1 rounded text-xs hover:opacity-80 ${compact ? 'py-0.5' : ''}`}
              style={{ backgroundColor: assignment.color || getCategoryColor(assignment.category) }}
            >
              <div className="font-medium truncate">
                {assignment.service_type_name}
              </div>
              {!compact && (
                <>
                  <div className="text-xs opacity-75 truncate">
                    {assignment.patient_name}
                  </div>
                  <div className="text-xs opacity-75">
                    {assignment.start_time}-{assignment.end_time}
                  </div>
                </>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

/**
 * UtilizationBar Component
 */
const UtilizationBar = ({ staff, compact = false }) => {
  const utilization = staff.utilization?.utilization || 0;
  const scheduled = staff.utilization?.scheduled || 0;
  const capacity = staff.weekly_capacity_hours || 40;

  const barColor = utilization > 100 ? 'bg-red-500' :
                   utilization > 90 ? 'bg-amber-500' :
                   utilization > 75 ? 'bg-blue-500' : 'bg-emerald-500';

  return (
    <div className={`flex items-center gap-2 ${compact ? 'mt-0.5' : 'mt-1'}`}>
      <div className={`flex-1 ${compact ? 'h-1' : 'h-1.5'} bg-slate-200 rounded-full`}>
        <div
          className={`h-full rounded-full ${barColor}`}
          style={{ width: `${Math.min(100, utilization)}%` }}
        />
      </div>
      {!compact && (
        <span className="text-xs text-slate-400">
          {scheduled}h/{capacity}h
        </span>
      )}
    </div>
  );
};

/**
 * Get category label from code
 */
function getCategoryLabel(category) {
  const labels = {
    personal_support: 'Personal Support (PSW)',
    nursing: 'Nursing',
    allied_health: 'Allied Health',
    community_support: 'Community Support',
    administrative: 'Administrative',
    other: 'Other',
  };
  return labels[category] || category;
}

export default TeamLaneGrid;

