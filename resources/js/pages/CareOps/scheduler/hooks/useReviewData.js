import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '../../../../services/api';
import { useSchedulerContext } from '../SchedulerContext';

/**
 * useReviewData
 * 
 * Fetches and manages AI proposal groups for the Review tab.
 * Groups suggestions into logical "proposal groups" for batch review.
 * 
 * Proposal Group Types:
 * - by_patient: All suggestions for a single patient
 * - by_match_quality: High-confidence suggestions across patients
 * - by_service_category: Grouped by service type (nursing, PSW, allied health)
 * - by_staff_rebalance: Suggestions that rebalance staff workload
 * 
 * @returns {Object} Review data and actions
 */
export function useReviewData() {
  const { weekRange, isSspoMode } = useSchedulerContext();

  // ============================================
  // State
  // ============================================
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [suggestions, setSuggestions] = useState([]);
  const [selectedGroupId, setSelectedGroupId] = useState(null);
  const [acceptingIds, setAcceptingIds] = useState(new Set());
  const [rejectingIds, setRejectingIds] = useState(new Set());

  // ============================================
  // Fetch Suggestions
  // ============================================
  const fetchSuggestions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({
        week_start: weekRange.start,
      });
      const response = await api.get(`/v2/scheduling/suggestions?${params}`);
      setSuggestions(response.data.data || []);
    } catch (err) {
      console.error('Failed to fetch suggestions:', err);
      setError('Failed to load suggestions');
    } finally {
      setLoading(false);
    }
  }, [weekRange.start]);

  // Initial load
  useEffect(() => {
    fetchSuggestions();
  }, [fetchSuggestions]);

  // ============================================
  // Computed Proposal Groups
  // ============================================
  const proposalGroups = useMemo(() => {
    if (!suggestions || suggestions.length === 0) return [];

    const groups = [];

    // Group 1: High-confidence matches (strong + moderate)
    const highConfidence = suggestions.filter(
      s => s.match_status === 'strong' || s.match_status === 'moderate'
    );
    if (highConfidence.length > 0) {
      groups.push({
        id: 'high-confidence',
        type: 'by_match_quality',
        title: 'High-Confidence Assignments',
        description: 'Strong and moderate matches ready for approval',
        suggestions: highConfidence,
        count: highConfidence.length,
        status: 'pending',
        source: 'AI Action',
        priority: 1,
        metrics: {
          strongCount: highConfidence.filter(s => s.match_status === 'strong').length,
          moderateCount: highConfidence.filter(s => s.match_status === 'moderate').length,
          avgConfidence: Math.round(
            highConfidence.reduce((sum, s) => sum + (s.confidence_score || 0), 0) / 
            highConfidence.length
          ),
        },
      });
    }

    // Group 2: By service category
    const byCategory = {};
    suggestions.forEach(s => {
      const category = s.service_category || 'other';
      if (!byCategory[category]) {
        byCategory[category] = [];
      }
      byCategory[category].push(s);
    });

    Object.entries(byCategory).forEach(([category, catSuggestions]) => {
      if (catSuggestions.length >= 3) {
        const categoryLabel = getCategoryLabel(category);
        groups.push({
          id: `category-${category}`,
          type: 'by_service_category',
          title: `${categoryLabel} Services`,
          description: `All ${categoryLabel.toLowerCase()} service assignments`,
          suggestions: catSuggestions,
          count: catSuggestions.length,
          status: 'pending',
          source: 'AI Action',
          priority: 2,
          metrics: {
            strongCount: catSuggestions.filter(s => s.match_status === 'strong').length,
            moderateCount: catSuggestions.filter(s => s.match_status === 'moderate').length,
            weakCount: catSuggestions.filter(s => s.match_status === 'weak').length,
          },
        });
      }
    });

    // Group 3: By patient (for patients with 3+ suggestions)
    const byPatient = {};
    suggestions.forEach(s => {
      const patientId = s.patient_id;
      if (!byPatient[patientId]) {
        byPatient[patientId] = {
          name: s.patient_name,
          suggestions: [],
        };
      }
      byPatient[patientId].suggestions.push(s);
    });

    Object.entries(byPatient).forEach(([patientId, data]) => {
      if (data.suggestions.length >= 2) {
        groups.push({
          id: `patient-${patientId}`,
          type: 'by_patient',
          title: `${data.name}'s Care Plan`,
          description: `All services for ${data.name}`,
          suggestions: data.suggestions,
          count: data.suggestions.length,
          status: 'pending',
          source: 'AI Action',
          priority: 3,
          metrics: {
            totalHours: data.suggestions.reduce((sum, s) => sum + ((s.duration_minutes || 60) / 60), 0),
            serviceTypes: [...new Set(data.suggestions.map(s => s.service_type_name))].length,
          },
        });
      }
    });

    // Group 4: Weak matches requiring review
    const weakMatches = suggestions.filter(s => s.match_status === 'weak');
    if (weakMatches.length > 0) {
      groups.push({
        id: 'weak-matches',
        type: 'by_match_quality',
        title: 'Requires Manual Review',
        description: 'Weak matches that need careful consideration',
        suggestions: weakMatches,
        count: weakMatches.length,
        status: 'pending',
        source: 'AI Action',
        priority: 4,
        metrics: {
          avgConfidence: Math.round(
            weakMatches.reduce((sum, s) => sum + (s.confidence_score || 0), 0) / 
            weakMatches.length
          ),
        },
      });
    }

    // Sort by priority
    return groups.sort((a, b) => a.priority - b.priority);
  }, [suggestions]);

  // Selected group data
  const selectedGroup = useMemo(() => {
    if (!selectedGroupId) return null;
    return proposalGroups.find(g => g.id === selectedGroupId);
  }, [proposalGroups, selectedGroupId]);

  // ============================================
  // Actions
  // ============================================

  /**
   * Accept a single suggestion
   */
  const acceptSuggestion = useCallback(async (suggestion) => {
    const suggestionKey = `${suggestion.patient_id}-${suggestion.service_type_id}`;
    setAcceptingIds(prev => new Set([...prev, suggestionKey]));
    
    try {
      // Calculate default time slot (9 AM on Monday of the week)
      const startTime = `${weekRange.start}T09:00:00`;
      const durationMs = (suggestion.duration_minutes || 60) * 60 * 1000;
      const endTime = new Date(new Date(startTime).getTime() + durationMs).toISOString();

      await api.post('/v2/scheduling/suggestions/accept', {
        patient_id: suggestion.patient_id,
        service_type_id: suggestion.service_type_id,
        staff_id: suggestion.suggested_staff_id,
        scheduled_start: startTime,
        scheduled_end: endTime,
      });

      // Remove from local state
      setSuggestions(prev => prev.filter(s =>
        !(s.patient_id === suggestion.patient_id &&
          s.service_type_id === suggestion.service_type_id)
      ));

      return { success: true };
    } catch (err) {
      console.error('Failed to accept suggestion:', err);
      return { success: false, error: err.response?.data?.error || 'Failed to accept' };
    } finally {
      setAcceptingIds(prev => {
        const next = new Set(prev);
        next.delete(suggestionKey);
        return next;
      });
    }
  }, [weekRange.start]);

  /**
   * Accept all suggestions in a group
   */
  const acceptGroup = useCallback(async (groupId) => {
    const group = proposalGroups.find(g => g.id === groupId);
    if (!group) return { success: false, error: 'Group not found' };

    const results = { successful: 0, failed: 0, errors: [] };

    for (const suggestion of group.suggestions) {
      const result = await acceptSuggestion(suggestion);
      if (result.success) {
        results.successful++;
      } else {
        results.failed++;
        results.errors.push(result.error);
      }
    }

    return results;
  }, [proposalGroups, acceptSuggestion]);

  /**
   * Accept selected suggestions (by their keys)
   */
  const acceptSelected = useCallback(async (selectedKeys) => {
    const results = { successful: 0, failed: 0, errors: [] };

    for (const key of selectedKeys) {
      const [patientId, serviceTypeId] = key.split('-').map(Number);
      const suggestion = suggestions.find(
        s => s.patient_id === patientId && s.service_type_id === serviceTypeId
      );
      
      if (suggestion) {
        const result = await acceptSuggestion(suggestion);
        if (result.success) {
          results.successful++;
        } else {
          results.failed++;
          results.errors.push(result.error);
        }
      }
    }

    return results;
  }, [suggestions, acceptSuggestion]);

  /**
   * Reject/dismiss a suggestion (removes from local state)
   */
  const rejectSuggestion = useCallback((suggestion) => {
    const suggestionKey = `${suggestion.patient_id}-${suggestion.service_type_id}`;
    setRejectingIds(prev => new Set([...prev, suggestionKey]));
    
    // For now, just remove from local state (could call API to log rejection)
    setTimeout(() => {
      setSuggestions(prev => prev.filter(s =>
        !(s.patient_id === suggestion.patient_id &&
          s.service_type_id === suggestion.service_type_id)
      ));
      setRejectingIds(prev => {
        const next = new Set(prev);
        next.delete(suggestionKey);
        return next;
      });
    }, 300);
  }, []);

  /**
   * Refresh suggestions
   */
  const refresh = useCallback(async () => {
    await fetchSuggestions();
  }, [fetchSuggestions]);

  // ============================================
  // Helper: Check if suggestion is being processed
  // ============================================
  const isProcessing = useCallback((suggestion) => {
    const key = `${suggestion.patient_id}-${suggestion.service_type_id}`;
    return acceptingIds.has(key) || rejectingIds.has(key);
  }, [acceptingIds, rejectingIds]);

  return {
    // State
    loading,
    error,
    suggestions,
    proposalGroups,
    selectedGroupId,
    selectedGroup,
    
    // Actions
    setSelectedGroupId,
    acceptSuggestion,
    acceptGroup,
    acceptSelected,
    rejectSuggestion,
    refresh,
    isProcessing,
    
    // Counts
    totalSuggestions: suggestions.length,
    totalGroups: proposalGroups.length,
  };
}

/**
 * Get category label from code
 */
function getCategoryLabel(category) {
  const labels = {
    nursing: 'Nursing',
    personal_support: 'Personal Support',
    psw: 'Personal Support',
    allied_health: 'Allied Health',
    rehab: 'Rehabilitation',
    therapy: 'Therapy',
    homemaking: 'Homemaking',
    other: 'Other',
  };
  return labels[category?.toLowerCase()] || category || 'Other';
}

export default useReviewData;

