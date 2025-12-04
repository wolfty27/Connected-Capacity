import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '../../../../services/api';
import { useSchedulerContext } from '../SchedulerContext';

/**
 * useConflictsData
 * 
 * Fetches and manages scheduling conflicts for the Conflicts tab.
 * 
 * Conflict Types:
 * - no_match: AI couldn't find any eligible staff
 * - double_booked: Staff assigned to overlapping visits
 * - travel: Excessive travel time between visits
 * - spacing: PSW spacing rules violated
 * - capacity: Staff over capacity limit
 * 
 * @returns {Object} Conflicts data and actions
 */
export function useConflictsData() {
  const { weekRange, isSspoMode } = useSchedulerContext();

  // ============================================
  // State
  // ============================================
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [conflicts, setConflicts] = useState([]);
  const [noMatchItems, setNoMatchItems] = useState([]);
  const [selectedConflictId, setSelectedConflictId] = useState(null);
  const [filterType, setFilterType] = useState('all');

  // ============================================
  // Fetch Data
  // ============================================
  const fetchConflicts = useCallback(async () => {
    setLoading(true);
    setError(null);
    
    try {
      // Fetch suggestions to get no-match items
      const suggestionsParams = new URLSearchParams({
        week_start: weekRange.start,
      });
      const suggestionsRes = await api.get(`/v2/scheduling/suggestions?${suggestionsParams}`);
      const suggestions = suggestionsRes.data.data || [];
      
      // Extract no-match items from suggestions
      const noMatch = suggestions
        .filter(s => s.match_status === 'none')
        .map(s => ({
          id: `no-match-${s.patient_id}-${s.service_type_id}`,
          type: 'no_match',
          patient_id: s.patient_id,
          patient_name: s.patient_name,
          service_type_id: s.service_type_id,
          service_type_name: s.service_type_name,
          date: weekRange.start,
          summary: s.no_match_reason || 'No eligible staff found within constraints',
          severity: 'high',
          suggestion: s,
        }));
      setNoMatchItems(noMatch);

      // Fetch assignments to check for scheduling conflicts
      const gridParams = new URLSearchParams({
        start_date: weekRange.start,
        end_date: weekRange.end,
      });
      const gridRes = await api.get(`/v2/scheduling/grid?${gridParams}`);
      const { staff, assignments } = gridRes.data.data || { staff: [], assignments: [] };

      // Detect conflicts from assignments
      const detectedConflicts = detectConflicts(assignments, staff);
      
      setConflicts([...noMatch, ...detectedConflicts]);
    } catch (err) {
      console.error('Failed to fetch conflicts:', err);
      setError('Failed to load conflicts');
    } finally {
      setLoading(false);
    }
  }, [weekRange.start, weekRange.end]);

  // Initial load
  useEffect(() => {
    fetchConflicts();
  }, [fetchConflicts]);

  // ============================================
  // Conflict Detection Logic
  // ============================================
  function detectConflicts(assignments, staff) {
    const conflicts = [];
    
    // Group assignments by staff and date
    const byStaffDate = {};
    assignments.forEach(a => {
      if (a.status === 'cancelled') return;
      const key = `${a.staff_id}-${a.date}`;
      if (!byStaffDate[key]) {
        byStaffDate[key] = [];
      }
      byStaffDate[key].push(a);
    });

    // Check for double-booking (overlapping times)
    Object.entries(byStaffDate).forEach(([key, dayAssignments]) => {
      if (dayAssignments.length < 2) return;
      
      // Sort by start time
      const sorted = [...dayAssignments].sort((a, b) => 
        a.start_time.localeCompare(b.start_time)
      );
      
      for (let i = 0; i < sorted.length - 1; i++) {
        const current = sorted[i];
        const next = sorted[i + 1];
        
        // Check if current end overlaps with next start
        if (current.end_time > next.start_time) {
          const staffMember = staff.find(s => s.id === current.staff_id);
          conflicts.push({
            id: `double-${current.id}-${next.id}`,
            type: 'double_booked',
            patient_id: current.patient_id,
            patient_name: current.patient_name,
            service_type_name: current.service_type_name,
            staff_id: current.staff_id,
            staff_name: staffMember?.name || 'Unknown',
            date: current.date,
            summary: `${staffMember?.name} is double-booked: ${current.start_time}-${current.end_time} overlaps with ${next.start_time}-${next.end_time}`,
            severity: 'high',
            assignment1: current,
            assignment2: next,
          });
        }
        
        // Check for insufficient travel time (less than 15 min gap)
        const currentEndMinutes = timeToMinutes(current.end_time);
        const nextStartMinutes = timeToMinutes(next.start_time);
        const gap = nextStartMinutes - currentEndMinutes;
        
        if (gap > 0 && gap < 15) {
          const staffMember = staff.find(s => s.id === current.staff_id);
          conflicts.push({
            id: `travel-${current.id}-${next.id}`,
            type: 'travel',
            patient_id: next.patient_id,
            patient_name: next.patient_name,
            service_type_name: next.service_type_name,
            staff_id: current.staff_id,
            staff_name: staffMember?.name || 'Unknown',
            date: current.date,
            summary: `Only ${gap} minutes between visits for ${staffMember?.name} (may be insufficient for travel)`,
            severity: 'medium',
            assignment1: current,
            assignment2: next,
          });
        }
      }
    });

    // Check for staff over capacity
    staff.forEach(s => {
      const utilization = s.utilization?.utilization || 0;
      if (utilization > 100) {
        conflicts.push({
          id: `capacity-${s.id}`,
          type: 'capacity',
          staff_id: s.id,
          staff_name: s.name,
          date: weekRange.start,
          summary: `${s.name} is ${Math.round(utilization)}% utilized (over capacity)`,
          severity: utilization > 120 ? 'high' : 'medium',
          utilization,
        });
      }
    });

    return conflicts;
  }

  function timeToMinutes(time) {
    const [hours, minutes] = time.split(':').map(Number);
    return hours * 60 + minutes;
  }

  // ============================================
  // Filtered Conflicts
  // ============================================
  const filteredConflicts = useMemo(() => {
    if (filterType === 'all') return conflicts;
    return conflicts.filter(c => c.type === filterType);
  }, [conflicts, filterType]);

  // Conflict counts by type
  const conflictCounts = useMemo(() => {
    const counts = { all: conflicts.length };
    conflicts.forEach(c => {
      counts[c.type] = (counts[c.type] || 0) + 1;
    });
    return counts;
  }, [conflicts]);

  // Selected conflict
  const selectedConflict = useMemo(() => {
    if (!selectedConflictId) return null;
    return conflicts.find(c => c.id === selectedConflictId);
  }, [conflicts, selectedConflictId]);

  // ============================================
  // Actions
  // ============================================

  /**
   * Dismiss a conflict (mark as acknowledged)
   */
  const dismissConflict = useCallback((conflictId) => {
    setConflicts(prev => prev.filter(c => c.id !== conflictId));
    if (selectedConflictId === conflictId) {
      setSelectedConflictId(null);
    }
  }, [selectedConflictId]);

  /**
   * Refresh conflicts
   */
  const refresh = useCallback(async () => {
    await fetchConflicts();
  }, [fetchConflicts]);

  // ============================================
  // Resolution Helpers
  // ============================================

  /**
   * Get suggested resolutions for a conflict
   */
  const getResolutions = useCallback((conflict) => {
    const resolutions = [];

    switch (conflict.type) {
      case 'no_match':
        resolutions.push({
          id: 'expand-time',
          label: 'Adjust Time Window',
          description: 'Look for availability in a wider time frame',
          action: 'suggest_times',
        });
        resolutions.push({
          id: 'expand-region',
          label: 'Expand Search Radius',
          description: 'Include staff from adjacent regions',
          action: 'expand_search',
        });
        resolutions.push({
          id: 'escalate',
          label: 'Escalate to SSPO',
          description: 'Request specialized provider assignment',
          action: 'escalate',
        });
        break;

      case 'double_booked':
        resolutions.push({
          id: 'reschedule-first',
          label: 'Reschedule Earlier Visit',
          description: `Move ${conflict.assignment1?.patient_name}'s visit`,
          action: 'reschedule',
          target: conflict.assignment1?.id,
        });
        resolutions.push({
          id: 'reschedule-second',
          label: 'Reschedule Later Visit',
          description: `Move ${conflict.assignment2?.patient_name}'s visit`,
          action: 'reschedule',
          target: conflict.assignment2?.id,
        });
        resolutions.push({
          id: 'reassign',
          label: 'Assign Different Staff',
          description: 'Find another available staff member',
          action: 'reassign',
        });
        break;

      case 'travel':
        resolutions.push({
          id: 'adjust-time',
          label: 'Adjust Appointment Time',
          description: 'Add buffer time for travel',
          action: 'adjust_time',
        });
        resolutions.push({
          id: 'swap-order',
          label: 'Optimize Route',
          description: 'Reorder visits to minimize travel',
          action: 'optimize_route',
        });
        break;

      case 'capacity':
        resolutions.push({
          id: 'reassign-visits',
          label: 'Redistribute Workload',
          description: 'Move some visits to other staff',
          action: 'redistribute',
        });
        resolutions.push({
          id: 'approve-overtime',
          label: 'Approve Overtime',
          description: 'Allow staff to work over capacity',
          action: 'approve_overtime',
        });
        break;

      default:
        resolutions.push({
          id: 'manual',
          label: 'Resolve Manually',
          description: 'Open scheduler to fix this issue',
          action: 'manual',
        });
    }

    return resolutions;
  }, []);

  return {
    // State
    loading,
    error,
    conflicts,
    filteredConflicts,
    conflictCounts,
    selectedConflictId,
    selectedConflict,
    filterType,
    
    // Actions
    setSelectedConflictId,
    setFilterType,
    dismissConflict,
    refresh,
    getResolutions,
    
    // Counts
    totalConflicts: conflicts.length,
  };
}

export default useConflictsData;

