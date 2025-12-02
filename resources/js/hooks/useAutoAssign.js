import { useState, useCallback } from 'react';
import api from '../services/api';

/**
 * useAutoAssign Hook
 *
 * Manages AI-assisted scheduling suggestions state and API interactions.
 *
 * Usage:
 *   const {
 *     suggestions,
 *     isLoading,
 *     error,
 *     generateSuggestions,
 *     getExplanation,
 *     acceptSuggestion,
 *     acceptBatch,
 *     clearSuggestions,
 *   } = useAutoAssign(weekStart, organizationId);
 */
export function useAutoAssign(weekStart, organizationId) {
    const [suggestions, setSuggestions] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const [explanations, setExplanations] = useState({}); // Cache explanations by key

    /**
     * Generate suggestions for unscheduled care
     */
    const generateSuggestions = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (weekStart) params.set('week_start', weekStart);
            if (organizationId) params.set('organization_id', organizationId);

            const response = await api.get(`/v2/scheduling/suggestions?${params}`);
            setSuggestions(response.data.data || []);
            return response.data;
        } catch (err) {
            const errorMessage = err.response?.data?.error || 'Failed to generate suggestions';
            setError(errorMessage);
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, [weekStart, organizationId]);

    /**
     * Get explanation for a specific suggestion
     */
    const getExplanation = useCallback(async (patientId, serviceTypeId, staffId) => {
        const cacheKey = `${patientId}-${serviceTypeId}-${staffId}`;

        // Return cached explanation if available
        if (explanations[cacheKey]) {
            return explanations[cacheKey];
        }

        try {
            const params = new URLSearchParams({
                staff_id: staffId,
                ...(weekStart && { week_start: weekStart }),
            });

            const response = await api.get(
                `/v2/scheduling/suggestions/${patientId}/${serviceTypeId}/explain?${params}`
            );

            const explanation = response.data.data;

            // Cache the explanation
            setExplanations(prev => ({
                ...prev,
                [cacheKey]: explanation,
            }));

            return explanation;
        } catch (err) {
            throw new Error(err.response?.data?.error || 'Failed to get explanation');
        }
    }, [weekStart, explanations]);

    /**
     * Accept a single suggestion
     */
    const acceptSuggestion = useCallback(async (suggestion, scheduledStart, scheduledEnd) => {
        try {
            const response = await api.post('/v2/scheduling/suggestions/accept', {
                patient_id: suggestion.patient_id,
                service_type_id: suggestion.service_type_id,
                staff_id: suggestion.suggested_staff_id,
                scheduled_start: scheduledStart,
                scheduled_end: scheduledEnd,
            });

            // Remove accepted suggestion from local state
            setSuggestions(prev => prev.filter(s =>
                !(s.patient_id === suggestion.patient_id &&
                  s.service_type_id === suggestion.service_type_id)
            ));

            return response.data.data;
        } catch (err) {
            throw new Error(err.response?.data?.error || 'Failed to accept suggestion');
        }
    }, []);

    /**
     * Accept multiple suggestions in batch
     */
    const acceptBatch = useCallback(async (suggestionsToAccept) => {
        try {
            const response = await api.post('/v2/scheduling/suggestions/accept-batch', {
                suggestions: suggestionsToAccept,
            });

            // Remove successful acceptances from local state
            const successfulIds = new Set(
                response.data.data.successful.map(s => `${s.patient_id}-${s.service_type_id}`)
            );
            setSuggestions(prev => prev.filter(s =>
                !successfulIds.has(`${s.patient_id}-${s.service_type_id}`)
            ));

            return response.data.data;
        } catch (err) {
            throw new Error(err.response?.data?.error || 'Failed to accept batch');
        }
    }, []);

    /**
     * Clear all suggestions
     */
    const clearSuggestions = useCallback(() => {
        setSuggestions([]);
        setExplanations({});
        setError(null);
    }, []);

    /**
     * Get suggestion for a specific patient-service combination
     */
    const getSuggestionFor = useCallback((patientId, serviceTypeId) => {
        return suggestions.find(
            s => s.patient_id === patientId && s.service_type_id === serviceTypeId
        );
    }, [suggestions]);

    /**
     * Check if suggestions have been generated
     */
    const hasSuggestions = suggestions.length > 0;

    /**
     * Get counts by match status
     */
    const strongMatches = suggestions.filter(s => s.match_status === 'strong');
    const moderateMatches = suggestions.filter(s => s.match_status === 'moderate');
    const weakMatches = suggestions.filter(s => s.match_status === 'weak');
    const noMatches = suggestions.filter(s => s.match_status === 'none');

    return {
        suggestions,
        isLoading,
        error,
        hasSuggestions,
        strongMatches,
        moderateMatches,
        weakMatches,
        noMatches,
        generateSuggestions,
        getExplanation,
        acceptSuggestion,
        acceptBatch,
        clearSuggestions,
        getSuggestionFor,
    };
}

export default useAutoAssign;
