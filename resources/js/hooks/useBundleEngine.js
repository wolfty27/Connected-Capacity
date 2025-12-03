import { useState, useCallback } from 'react';
import axios from 'axios';

/**
 * useBundleEngine hook
 *
 * Provides state management and API calls for the AI-Assisted Bundle Engine.
 *
 * Features:
 * - Build patient needs profile
 * - Get applicable scenario axes
 * - Generate scenario bundles
 * - Compare scenarios
 * - Get AI explanations for scenarios (v2.2)
 * - Display algorithm scores and triggered CAPs (v2.2)
 */
export function useBundleEngine() {
    const [profile, setProfile] = useState(null);
    const [axes, setAxes] = useState([]);
    const [scenarios, setScenarios] = useState([]);
    const [selectedScenario, setSelectedScenario] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [profileSummary, setProfileSummary] = useState(null);
    const [algorithmScores, setAlgorithmScores] = useState(null);
    const [triggeredCAPs, setTriggeredCAPs] = useState([]);
    const [explanation, setExplanation] = useState(null);
    const [explanationLoading, setExplanationLoading] = useState(false);

    /**
     * Build patient needs profile
     */
    const buildProfile = useCallback(async (patientId, options = {}) => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (options.forceRefresh) params.append('force_refresh', 'true');
            if (options.includeReferral !== undefined) {
                params.append('include_referral', options.includeReferral ? 'true' : 'false');
            }

            const response = await axios.get(
                `/api/v2/bundle-engine/profile/${patientId}?${params.toString()}`
            );

            if (response.data.success) {
                setProfile(response.data.data.profile);
                return response.data.data;
            } else {
                throw new Error(response.data.error || 'Failed to build profile');
            }
        } catch (err) {
            const message = err.response?.data?.error || err.message || 'Failed to build profile';
            setError(message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    /**
     * Get applicable scenario axes for a patient
     */
    const getAxes = useCallback(async (patientId, options = {}) => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (options.maxAxes) params.append('max_axes', options.maxAxes);
            if (options.detailed) params.append('detailed', 'true');

            const response = await axios.get(
                `/api/v2/bundle-engine/axes/${patientId}?${params.toString()}`
            );

            if (response.data.success) {
                const axesData = response.data.data.applicable_axes || response.data.data.evaluation || [];
                setAxes(axesData);
                return axesData;
            } else {
                throw new Error(response.data.error || 'Failed to get axes');
            }
        } catch (err) {
            const message = err.response?.data?.error || err.message || 'Failed to get axes';
            setError(message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    /**
     * Generate scenario bundles for a patient
     */
    const generateScenarios = useCallback(async (patientId, options = {}) => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (options.minScenarios) params.append('min_scenarios', options.minScenarios);
            if (options.maxScenarios) params.append('max_scenarios', options.maxScenarios);
            if (options.includeBalanced !== undefined) {
                params.append('include_balanced', options.includeBalanced ? 'true' : 'false');
            }
            if (options.referenceCap) params.append('reference_cap', options.referenceCap);

            const response = await axios.get(
                `/api/v2/bundle-engine/scenarios/${patientId}?${params.toString()}`
            );

            if (response.data.success) {
                setScenarios(response.data.data.scenarios);
                setProfileSummary(response.data.data.profile_summary);

                // v2.2: Extract algorithm scores and triggered CAPs from profile summary
                if (response.data.data.profile_summary) {
                    const summary = response.data.data.profile_summary;
                    setAlgorithmScores({
                        personalSupport: summary.personal_support_score,
                        rehabilitation: summary.rehabilitation_score,
                        chessCA: summary.chess_ca_score,
                        pain: summary.pain_score,
                        distressedMood: summary.distressed_mood_score,
                        serviceUrgency: summary.service_urgency_score,
                    });
                    setTriggeredCAPs(summary.triggered_caps || []);
                }

                // Auto-select recommended scenario
                const recommended = response.data.data.scenarios.find(s => s.meta?.is_recommended);
                if (recommended) {
                    setSelectedScenario(recommended);
                }

                return response.data.data;
            } else {
                throw new Error(response.data.error || 'Failed to generate scenarios');
            }
        } catch (err) {
            const message = err.response?.data?.error || err.message || 'Failed to generate scenarios';
            setError(message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    /**
     * Get AI explanation for a selected scenario (v2.2)
     */
    const getExplanation = useCallback(async (patientId, scenarioIndex, withAlternatives = true) => {
        setExplanationLoading(true);
        setExplanation(null);

        try {
            const response = await axios.post('/api/v2/bundle-engine/explain', {
                patient_id: patientId,
                scenario_index: scenarioIndex,
                with_alternatives: withAlternatives,
            });

            if (response.data.success) {
                setExplanation(response.data.data.explanation);
                return response.data.data;
            } else {
                throw new Error(response.data.error || 'Failed to get explanation');
            }
        } catch (err) {
            const message = err.response?.data?.error || err.message || 'Failed to get explanation';
            console.error('Explanation error:', message);
            throw err;
        } finally {
            setExplanationLoading(false);
        }
    }, []);

    /**
     * Get available data sources for a patient
     */
    const getDataSources = useCallback(async (patientId) => {
        try {
            const response = await axios.get(`/api/v2/bundle-engine/data-sources/${patientId}`);
            return response.data.data;
        } catch (err) {
            console.error('Failed to get data sources:', err);
            return null;
        }
    }, []);

    /**
     * Compare two scenarios
     */
    const compareScenarios = useCallback(async (patientId, scenarioId1, scenarioId2) => {
        try {
            const response = await axios.post('/api/v2/bundle-engine/compare', {
                patient_id: patientId,
                scenario_id_1: scenarioId1,
                scenario_id_2: scenarioId2,
            });

            if (response.data.success) {
                return response.data.data.comparison;
            } else {
                throw new Error(response.data.error || 'Failed to compare scenarios');
            }
        } catch (err) {
            const message = err.response?.data?.error || err.message || 'Failed to compare scenarios';
            setError(message);
            throw err;
        }
    }, []);

    /**
     * Invalidate cached profile
     */
    const invalidateCache = useCallback(async (patientId) => {
        try {
            await axios.post(`/api/v2/bundle-engine/invalidate-cache/${patientId}`);
            setProfile(null);
            setScenarios([]);
            setSelectedScenario(null);
        } catch (err) {
            console.error('Failed to invalidate cache:', err);
        }
    }, []);

    /**
     * Select a scenario
     */
    const selectScenario = useCallback((scenario) => {
        setSelectedScenario(scenario);
    }, []);

    /**
     * Clear explanation state
     */
    const clearExplanation = useCallback(() => {
        setExplanation(null);
    }, []);

    /**
     * Clear all state
     */
    const reset = useCallback(() => {
        setProfile(null);
        setAxes([]);
        setScenarios([]);
        setSelectedScenario(null);
        setError(null);
        setProfileSummary(null);
        setAlgorithmScores(null);
        setTriggeredCAPs([]);
        setExplanation(null);
    }, []);

    return {
        // State
        profile,
        axes,
        scenarios,
        selectedScenario,
        loading,
        error,
        profileSummary,
        // v2.2: Algorithm scores and CAPs
        algorithmScores,
        triggeredCAPs,
        explanation,
        explanationLoading,

        // Actions
        buildProfile,
        getAxes,
        generateScenarios,
        getDataSources,
        compareScenarios,
        invalidateCache,
        selectScenario,
        reset,
        // v2.2: Explanation
        getExplanation,
        clearExplanation,
    };
}

export default useBundleEngine;

