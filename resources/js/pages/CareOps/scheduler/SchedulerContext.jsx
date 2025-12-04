import React, { createContext, useContext, useState, useCallback, useMemo } from 'react';

/**
 * SchedulerContext
 * 
 * Provides shared state for the Scheduler 2.0 multi-tab interface.
 * All tabs access the same timeframe, filters, suggestions, and conflicts.
 * 
 * @see docs/CC21 Scheduler 2.0 prelim – Design & Implementation Spec.txt
 */

const SchedulerContext = createContext(null);

// View modes for the scheduler tabs
export const VIEW_MODES = {
  AI_OVERVIEW: 'ai-overview',
  SCHEDULE: 'schedule',
  REVIEW: 'review',
  CONFLICTS: 'conflicts',
};

// Sub-modes for the Schedule tab
export const SCHEDULE_SUB_MODES = {
  STAFF_LANES: 'staff-lanes',
  LIST: 'list',
};

/**
 * SchedulerProvider
 * 
 * Wraps the scheduler shell and provides shared state to all tabs.
 */
export function SchedulerProvider({ children, isSspoMode = false }) {
  // ============================================
  // Timeframe State
  // ============================================
  const [weekOffset, setWeekOffset] = useState(0);
  
  const weekRange = useMemo(() => {
    const today = new Date();
    const dayOfWeek = today.getDay();
    // Calculate Monday (ISO week standard)
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

  const weekDays = useMemo(() => {
    return Array.from({ length: 7 }, (_, i) => {
      const date = new Date(weekRange.startDate);
      date.setDate(weekRange.startDate.getDate() + i);
      return date;
    });
  }, [weekRange.startDate]);

  // ============================================
  // View State
  // ============================================
  const [viewMode, setViewMode] = useState(VIEW_MODES.SCHEDULE);
  const [scheduleSubMode, setScheduleSubMode] = useState(SCHEDULE_SUB_MODES.STAFF_LANES);

  // ============================================
  // Filter State
  // ============================================
  const [filters, setFilters] = useState({
    staffIds: [],
    teamLaneIds: [],
    patientIds: [],
    roleFilter: '',
    empTypeFilter: '',
  });

  const updateFilter = useCallback((key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  }, []);

  const clearFilters = useCallback(() => {
    setFilters({
      staffIds: [],
      teamLaneIds: [],
      patientIds: [],
      roleFilter: '',
      empTypeFilter: '',
    });
  }, []);

  const hasFilters = useMemo(() => {
    return filters.staffIds.length > 0 ||
           filters.teamLaneIds.length > 0 ||
           filters.patientIds.length > 0 ||
           filters.roleFilter ||
           filters.empTypeFilter;
  }, [filters]);

  // ============================================
  // Collapsible Sections State
  // ============================================
  const [isNavCollapsed, setIsNavCollapsed] = useState(false);
  const [isUnscheduledCollapsed, setIsUnscheduledCollapsed] = useState(false);

  // ============================================
  // AI Suggestions State (shared across tabs)
  // ============================================
  const [suggestions, setSuggestions] = useState([]);
  const [proposalGroups, setProposalGroups] = useState([]);
  const [selectedProposalGroupId, setSelectedProposalGroupId] = useState(null);
  const [explanations, setExplanations] = useState({});

  // ============================================
  // Conflicts State
  // ============================================
  const [conflicts, setConflicts] = useState([]);
  const [noMatchItems, setNoMatchItems] = useState([]);

  // ============================================
  // Loading States
  // ============================================
  const [ui, setUi] = useState({
    isLoadingSuggestions: false,
    isAutoAssignRunning: false,
    lastAutoAssignRunAt: null,
    isLoadingOverview: false,
    isLoadingConflicts: false,
  });

  const setUiState = useCallback((key, value) => {
    setUi(prev => ({ ...prev, [key]: value }));
  }, []);

  // ============================================
  // Week Navigation Helpers
  // ============================================
  const goToPreviousWeek = useCallback(() => {
    setWeekOffset(prev => prev - 1);
  }, []);

  const goToNextWeek = useCallback(() => {
    setWeekOffset(prev => prev + 1);
  }, []);

  const goToCurrentWeek = useCallback(() => {
    setWeekOffset(0);
  }, []);

  // ============================================
  // Context Value
  // ============================================
  const value = useMemo(() => ({
    // Mode
    isSspoMode,
    
    // Timeframe
    weekOffset,
    weekRange,
    weekDays,
    goToPreviousWeek,
    goToNextWeek,
    goToCurrentWeek,
    
    // View
    viewMode,
    setViewMode,
    scheduleSubMode,
    setScheduleSubMode,
    
    // Filters
    filters,
    updateFilter,
    clearFilters,
    hasFilters,
    
    // Collapsible sections
    isNavCollapsed,
    setIsNavCollapsed,
    isUnscheduledCollapsed,
    setIsUnscheduledCollapsed,
    
    // AI Suggestions
    suggestions,
    setSuggestions,
    proposalGroups,
    setProposalGroups,
    selectedProposalGroupId,
    setSelectedProposalGroupId,
    explanations,
    setExplanations,
    
    // Conflicts
    conflicts,
    setConflicts,
    noMatchItems,
    setNoMatchItems,
    
    // UI State
    ui,
    setUiState,
  }), [
    isSspoMode,
    weekOffset,
    weekRange,
    weekDays,
    goToPreviousWeek,
    goToNextWeek,
    goToCurrentWeek,
    viewMode,
    scheduleSubMode,
    filters,
    updateFilter,
    clearFilters,
    hasFilters,
    isNavCollapsed,
    isUnscheduledCollapsed,
    suggestions,
    proposalGroups,
    selectedProposalGroupId,
    explanations,
    conflicts,
    noMatchItems,
    ui,
    setUiState,
  ]);

  return (
    <SchedulerContext.Provider value={value}>
      {children}
    </SchedulerContext.Provider>
  );
}

/**
 * useSchedulerContext
 * 
 * Hook to access the scheduler shared state.
 * Must be used within a SchedulerProvider.
 */
export function useSchedulerContext() {
  const context = useContext(SchedulerContext);
  if (!context) {
    throw new Error('useSchedulerContext must be used within a SchedulerProvider');
  }
  return context;
}

export default SchedulerContext;

