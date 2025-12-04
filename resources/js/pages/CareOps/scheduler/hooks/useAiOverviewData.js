import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '../../../../services/api';
import { useSchedulerContext } from '../SchedulerContext';

/**
 * useAiOverviewData
 * 
 * Fetches and aggregates data for the AI Overview tab:
 * - Metrics summary (TFS, missed care, capacity, unscheduled)
 * - AI suggestions with match counts
 * - High-risk patients
 * - Staff capacity warnings
 * 
 * @returns {Object} AI Overview data and loading states
 */
export function useAiOverviewData() {
  const { weekRange, isSspoMode } = useSchedulerContext();

  // ============================================
  // State
  // ============================================
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  // Metrics
  const [tfsMetrics, setTfsMetrics] = useState(null);
  const [missedCareMetrics, setMissedCareMetrics] = useState(null);
  const [capacityMetrics, setCapacityMetrics] = useState(null);
  const [workforceSummary, setWorkforceSummary] = useState(null);
  
  // AI Suggestions
  const [suggestions, setSuggestions] = useState([]);
  const [suggestionsLoading, setSuggestionsLoading] = useState(false);
  
  // Unscheduled Care
  const [unscheduledRequirements, setUnscheduledRequirements] = useState({ data: [], summary: {} });
  
  // Staff at capacity
  const [staffCapacity, setStaffCapacity] = useState([]);

  // ============================================
  // Fetch Functions
  // ============================================

  const fetchTfsMetrics = useCallback(async () => {
    try {
      const response = await api.get('/v2/tfs/summary');
      setTfsMetrics(response.data.data);
    } catch (err) {
      console.error('Failed to fetch TFS metrics:', err);
    }
  }, []);

  const fetchDashboardMetrics = useCallback(async () => {
    try {
      const response = await api.get('/v2/spo-dashboard');
      const data = response.data;
      setMissedCareMetrics(data.missed_care);
      // Extract any other useful metrics
    } catch (err) {
      console.error('Failed to fetch dashboard metrics:', err);
    }
  }, []);

  const fetchCapacityMetrics = useCallback(async () => {
    try {
      const params = new URLSearchParams({
        period_type: 'week',
        start_date: weekRange.start,
        ...(isSspoMode && { provider_type: 'sspo' }),
      });
      const response = await api.get(`/v2/workforce/capacity?${params}`);
      setCapacityMetrics(response.data.data);
    } catch (err) {
      console.error('Failed to fetch capacity metrics:', err);
    }
  }, [weekRange.start, isSspoMode]);

  const fetchWorkforceSummary = useCallback(async () => {
    try {
      const response = await api.get('/v2/workforce/summary');
      setWorkforceSummary(response.data.data);
    } catch (err) {
      console.error('Failed to fetch workforce summary:', err);
    }
  }, []);

  const fetchUnscheduledRequirements = useCallback(async () => {
    try {
      const params = new URLSearchParams({
        start_date: weekRange.start,
        end_date: weekRange.end,
        ...(isSspoMode && { provider_type: 'sspo' }),
      });
      const response = await api.get(`/v2/scheduling/requirements?${params}`);
      setUnscheduledRequirements(response.data);
    } catch (err) {
      console.error('Failed to fetch unscheduled requirements:', err);
    }
  }, [weekRange.start, weekRange.end, isSspoMode]);

  const fetchSuggestions = useCallback(async () => {
    setSuggestionsLoading(true);
    try {
      const params = new URLSearchParams({
        week_start: weekRange.start,
      });
      const response = await api.get(`/v2/scheduling/suggestions?${params}`);
      setSuggestions(response.data.data || []);
    } catch (err) {
      console.error('Failed to fetch suggestions:', err);
    } finally {
      setSuggestionsLoading(false);
    }
  }, [weekRange.start]);

  const fetchStaffCapacity = useCallback(async () => {
    try {
      const params = new URLSearchParams({
        start_date: weekRange.start,
        end_date: weekRange.end,
      });
      const response = await api.get(`/v2/scheduling/grid?${params}`);
      // Extract staff with high utilization
      const staff = response.data.data?.staff || [];
      const atCapacity = staff
        .filter(s => s.utilization?.utilization > 85)
        .sort((a, b) => (b.utilization?.utilization || 0) - (a.utilization?.utilization || 0))
        .slice(0, 5);
      setStaffCapacity(atCapacity);
    } catch (err) {
      console.error('Failed to fetch staff capacity:', err);
    }
  }, [weekRange.start, weekRange.end]);

  // ============================================
  // Load All Data
  // ============================================

  const loadAllData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      await Promise.all([
        fetchTfsMetrics(),
        fetchDashboardMetrics(),
        fetchCapacityMetrics(),
        fetchWorkforceSummary(),
        fetchUnscheduledRequirements(),
        fetchSuggestions(),
        fetchStaffCapacity(),
      ]);
    } catch (err) {
      setError('Failed to load AI Overview data');
    } finally {
      setLoading(false);
    }
  }, [
    fetchTfsMetrics,
    fetchDashboardMetrics,
    fetchCapacityMetrics,
    fetchWorkforceSummary,
    fetchUnscheduledRequirements,
    fetchSuggestions,
    fetchStaffCapacity,
  ]);

  // Initial load
  useEffect(() => {
    loadAllData();
  }, [loadAllData]);

  // ============================================
  // Computed Values
  // ============================================

  // Suggestion counts by match status
  const suggestionCounts = useMemo(() => {
    const strong = suggestions.filter(s => s.match_status === 'strong');
    const moderate = suggestions.filter(s => s.match_status === 'moderate');
    const weak = suggestions.filter(s => s.match_status === 'weak');
    const none = suggestions.filter(s => s.match_status === 'none');
    
    return {
      total: suggestions.length,
      strong: strong.length,
      moderate: moderate.length,
      weak: weak.length,
      none: none.length,
      autoAssignable: strong.length + moderate.length, // Safe to auto-assign
    };
  }, [suggestions]);

  // Quick win calculation (strong + moderate matches that can be auto-assigned)
  const quickWin = useMemo(() => {
    const safeAssignments = suggestions.filter(
      s => s.match_status === 'strong' || s.match_status === 'moderate'
    );
    
    // Estimate time saved (avg 3 min per manual assignment)
    const estimatedMinutesSaved = safeAssignments.length * 3;
    
    return {
      count: safeAssignments.length,
      assignments: safeAssignments,
      estimatedMinutesSaved,
    };
  }, [suggestions]);

  // High priority unscheduled (patients with urgent needs)
  const highPriorityUnscheduled = useMemo(() => {
    return (unscheduledRequirements.data || [])
      .filter(p => p.risk_flags?.includes('dangerous') || p.risk_flags?.includes('warning'))
      .slice(0, 5);
  }, [unscheduledRequirements.data]);

  // Patients requiring attention (high risk or with no-match suggestions)
  const patientsRequiringAttention = useMemo(() => {
    const noMatchPatientIds = new Set(
      suggestions.filter(s => s.match_status === 'none').map(s => s.patient_id)
    );
    
    return (unscheduledRequirements.data || [])
      .filter(p => 
        p.risk_flags?.includes('dangerous') || 
        p.risk_flags?.includes('warning') ||
        noMatchPatientIds.has(p.patient_id)
      )
      .slice(0, 5);
  }, [unscheduledRequirements.data, suggestions]);

  // Net capacity (available - scheduled)
  const netCapacity = useMemo(() => {
    if (!capacityMetrics) return null;
    
    const available = capacityMetrics.available_hours || 0;
    const scheduled = capacityMetrics.scheduled_hours || 0;
    const required = capacityMetrics.required_hours || 0;
    const net = available - scheduled;
    
    let status = 'green';
    if (net < 0) status = 'red';
    else if (net < required * 0.2) status = 'amber';
    
    return {
      value: net,
      available,
      scheduled,
      required,
      status,
    };
  }, [capacityMetrics]);

  // Metrics summary for cards
  const metricsSummary = useMemo(() => {
    return {
      tfs: {
        value: tfsMetrics?.average_hours || 0,
        unit: 'h',
        band: tfsMetrics?.band || 'C',
        target: 24, // OHaH target
      },
      unscheduled: {
        value: unscheduledRequirements.summary?.total_remaining_hours || 0,
        unit: 'h',
        patients: unscheduledRequirements.summary?.patients_with_needs || 0,
        visits: unscheduledRequirements.summary?.total_remaining_visits || 0,
      },
      missedCare: {
        value: missedCareMetrics?.rate_percent || 0,
        unit: '%',
        band: missedCareMetrics?.band || 'A',
        target: 0, // OHaH target is 0%
      },
      netCapacity: netCapacity,
    };
  }, [tfsMetrics, unscheduledRequirements.summary, missedCareMetrics, netCapacity]);

  // ============================================
  // Actions
  // ============================================

  const regenerateSuggestions = useCallback(async () => {
    await fetchSuggestions();
  }, [fetchSuggestions]);

  const refreshAll = useCallback(async () => {
    await loadAllData();
  }, [loadAllData]);

  return {
    // Loading states
    loading,
    suggestionsLoading,
    error,
    
    // Raw data
    tfsMetrics,
    missedCareMetrics,
    capacityMetrics,
    workforceSummary,
    unscheduledRequirements,
    suggestions,
    staffCapacity,
    
    // Computed data
    suggestionCounts,
    quickWin,
    highPriorityUnscheduled,
    patientsRequiringAttention,
    netCapacity,
    metricsSummary,
    
    // Actions
    regenerateSuggestions,
    refreshAll,
  };
}

export default useAiOverviewData;

