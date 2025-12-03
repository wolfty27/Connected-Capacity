import React, { useState, useEffect } from 'react';
import { ScenarioCard } from './ScenarioCard';
import { ScenarioDetailModal } from './ScenarioDetailModal';

/**
 * ScenarioSelector Component
 *
 * Main component for selecting a bundle scenario for a patient.
 * Displays 3-5 scenario options and allows selection.
 *
 * Patient-experience oriented design:
 * - Focus on care outcomes, not budget constraints
 * - Clear trade-offs explained
 * - Cost as reference, not barrier
 */
export function ScenarioSelector({
    patientId,
    scenarios = [],
    profileSummary = null,
    loading = false,
    error = null,
    onScenarioSelect,
    onGenerateScenarios,
    selectedScenarioId = null,
}) {
    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [detailScenario, setDetailScenario] = useState(null);

    // Handle scenario selection
    const handleSelect = (scenario) => {
        onScenarioSelect?.(scenario);
    };

    // Open detail modal
    const handleViewDetails = (scenario) => {
        setDetailScenario(scenario);
        setDetailModalOpen(true);
    };

    // Close detail modal
    const handleCloseDetails = () => {
        setDetailModalOpen(false);
        setDetailScenario(null);
    };

    // Confirm selection from modal
    const handleConfirmFromModal = (scenario) => {
        onScenarioSelect?.(scenario);
        setDetailModalOpen(false);
    };

    if (loading) {
        return (
            <div className="p-8 text-center">
                <div className="inline-flex items-center gap-3">
                    <div className="w-6 h-6 border-2 border-teal-600 border-t-transparent rounded-full animate-spin" />
                    <span className="text-gray-600">Generating scenarios...</span>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6 bg-rose-50 border border-rose-200 rounded-xl">
                <div className="flex items-start gap-3">
                    <span className="text-rose-500 text-xl">⚠️</span>
                    <div>
                        <h3 className="font-semibold text-rose-800">Unable to Generate Scenarios</h3>
                        <p className="text-sm text-rose-600 mt-1">{error}</p>
                        <button
                            onClick={onGenerateScenarios}
                            className="mt-3 px-4 py-2 text-sm font-medium text-rose-600 bg-white border border-rose-300 rounded-lg hover:bg-rose-50"
                        >
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    if (scenarios.length === 0) {
        return (
            <div className="p-8 text-center border-2 border-dashed border-gray-200 rounded-xl">
                <div className="text-4xl mb-4">📋</div>
                <h3 className="font-semibold text-gray-900 mb-2">No Scenarios Generated</h3>
                <p className="text-sm text-gray-600 mb-4">
                    Generate care bundle scenarios based on the patient's needs profile.
                </p>
                <button
                    onClick={onGenerateScenarios}
                    className="px-6 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 transition-colors"
                >
                    Generate Scenarios
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Choose a Care Scenario</h2>
                    <p className="text-sm text-gray-600">
                        Select the scenario that best fits the patient's goals and preferences
                    </p>
                </div>
                <button
                    onClick={onGenerateScenarios}
                    className="px-4 py-2 text-sm font-medium text-teal-600 bg-teal-50 hover:bg-teal-100 rounded-lg transition-colors"
                >
                    Regenerate
                </button>
            </div>

            {/* Profile Summary */}
            {profileSummary && (
                <div className="p-4 bg-gray-50 rounded-xl">
                    <div className="flex items-center gap-4 flex-wrap">
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-gray-500">Classification:</span>
                            <span className="px-2 py-1 bg-white rounded text-sm font-medium">
                                {profileSummary.primary_classification || 'N/A'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-gray-500">Episode Type:</span>
                            <span className="px-2 py-1 bg-white rounded text-sm font-medium capitalize">
                                {profileSummary.episode_type?.replace('_', ' ') || 'N/A'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-gray-500">Rehab Potential:</span>
                            <span className={`px-2 py-1 rounded text-sm font-medium ${
                                profileSummary.rehab_potential
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-gray-100 text-gray-700'
                            }`}>
                                {profileSummary.rehab_potential ? 'Yes' : 'No'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-gray-500">Confidence:</span>
                            <span className={`px-2 py-1 rounded text-sm font-medium capitalize ${
                                profileSummary.confidence === 'high'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : profileSummary.confidence === 'medium'
                                    ? 'bg-amber-100 text-amber-700'
                                    : 'bg-gray-100 text-gray-700'
                            }`}>
                                {profileSummary.confidence || 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>
            )}

            {/* Scenario Cards Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {scenarios.map((scenario) => (
                    <ScenarioCard
                        key={scenario.scenario_id}
                        scenario={scenario}
                        isSelected={selectedScenarioId === scenario.scenario_id}
                        onSelect={handleSelect}
                        onViewDetails={handleViewDetails}
                    />
                ))}
            </div>

            {/* Selection Summary */}
            {selectedScenarioId && (
                <div className="p-4 bg-teal-50 border border-teal-200 rounded-xl">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <span className="text-teal-600 text-xl">✓</span>
                            <div>
                                <span className="font-medium text-teal-800">
                                    {scenarios.find(s => s.scenario_id === selectedScenarioId)?.label?.title || 'Selected'}
                                </span>
                                <span className="text-sm text-teal-600 ml-2">
                                    Ready to proceed
                                </span>
                            </div>
                        </div>
                        <button
                            className="px-6 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 transition-colors"
                        >
                            Continue with Selection
                        </button>
                    </div>
                </div>
            )}

            {/* Detail Modal */}
            <ScenarioDetailModal
                isOpen={detailModalOpen}
                scenario={detailScenario}
                onClose={handleCloseDetails}
                onSelect={handleConfirmFromModal}
            />
        </div>
    );
}

export default ScenarioSelector;

