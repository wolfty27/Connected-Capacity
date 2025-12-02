import React, { useState, useEffect } from 'react';
import Spinner from '../UI/Spinner';

/**
 * ExplanationModal
 *
 * Displays AI-generated or rules-based explanations for scheduling suggestions.
 *
 * Props:
 * - isOpen: boolean
 * - onClose: () => void
 * - suggestion: suggestion object (patient_id, service_type_id, etc.)
 * - getExplanation: async function to fetch explanation
 */
const ExplanationModal = ({ isOpen, onClose, suggestion, getExplanation }) => {
    const [explanation, setExplanation] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (isOpen && suggestion?.suggested_staff_id) {
            fetchExplanation();
        } else {
            setExplanation(null);
            setError(null);
        }
    }, [isOpen, suggestion?.patient_id, suggestion?.service_type_id, suggestion?.suggested_staff_id]);

    const fetchExplanation = async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await getExplanation(
                suggestion.patient_id,
                suggestion.service_type_id,
                suggestion.suggested_staff_id
            );
            setExplanation(result);
        } catch (err) {
            setError(err.message || 'Failed to load explanation');
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    const getConfidenceBadgeColor = (label) => {
        switch (label) {
            case 'High Match':
                return 'bg-emerald-100 text-emerald-800';
            case 'Good Match':
                return 'bg-blue-100 text-blue-800';
            case 'Acceptable':
                return 'bg-amber-100 text-amber-800';
            case 'Limited Options':
                return 'bg-orange-100 text-orange-800';
            case 'No Match':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-slate-100 text-slate-800';
        }
    };

    const getSourceLabel = (source) => {
        switch (source) {
            case 'vertex_ai':
                return { label: 'AI Generated', icon: '🤖' };
            case 'rules_based':
                return { label: 'Rules-Based', icon: '📋' };
            default:
                return { label: source, icon: '❓' };
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                onClick={onClose}
            />

            {/* Modal */}
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all">
                    {/* Header */}
                    <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                        <div className="flex items-center gap-2">
                            <span className="text-xl">🧠</span>
                            <h3 className="text-lg font-semibold text-slate-900">Match Explanation</h3>
                        </div>
                        <button
                            onClick={onClose}
                            className="text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Body */}
                    <div className="px-6 py-4">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <Spinner size="md" />
                                <span className="ml-2 text-slate-500">Generating explanation...</span>
                            </div>
                        ) : error ? (
                            <div className="text-center py-8">
                                <div className="text-red-500 mb-2">⚠️</div>
                                <p className="text-sm text-red-600">{error}</p>
                                <button
                                    onClick={fetchExplanation}
                                    className="mt-3 text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Try again
                                </button>
                            </div>
                        ) : explanation ? (
                            <div className="space-y-4">
                                {/* Confidence Badge */}
                                <div className="flex items-center justify-between">
                                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getConfidenceBadgeColor(explanation.confidence_label)}`}>
                                        {explanation.confidence_label}
                                    </span>
                                    <span className="text-xs text-slate-400 flex items-center gap-1">
                                        {getSourceLabel(explanation.source).icon}
                                        {getSourceLabel(explanation.source).label}
                                    </span>
                                </div>

                                {/* Short Explanation */}
                                <p className="text-sm text-slate-700 leading-relaxed">
                                    {explanation.short_explanation}
                                </p>

                                {/* Detailed Points */}
                                {explanation.detailed_points?.length > 0 && (
                                    <div className="border-t border-slate-100 pt-4">
                                        <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                                            Key Factors
                                        </h4>
                                        <ul className="space-y-2">
                                            {explanation.detailed_points.map((point, idx) => (
                                                <li key={idx} className="flex items-start gap-2 text-sm text-slate-600">
                                                    <span className="text-emerald-500 mt-0.5">✓</span>
                                                    <span>{point}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {/* Scoring Breakdown (if available) */}
                                {suggestion?.scoring_breakdown && (
                                    <div className="border-t border-slate-100 pt-4">
                                        <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                                            Score Breakdown
                                        </h4>
                                        <div className="grid grid-cols-2 gap-2">
                                            {Object.entries(suggestion.scoring_breakdown).map(([key, value]) => (
                                                <div key={key} className="flex justify-between text-xs">
                                                    <span className="text-slate-500 capitalize">
                                                        {key.replace(/_/g, ' ')}
                                                    </span>
                                                    <span className="font-medium text-slate-700">
                                                        {value.score}/{value.max}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-2 pt-2 border-t border-slate-50 flex justify-between text-sm">
                                            <span className="font-medium text-slate-600">Total Score</span>
                                            <span className="font-bold text-slate-800">
                                                {suggestion.confidence_score?.toFixed(1) || '—'}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* Response Time */}
                                {explanation.response_time_ms && (
                                    <div className="text-xs text-slate-400 text-right">
                                        Generated in {explanation.response_time_ms}ms
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-slate-500">
                                No explanation available
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="px-6 py-4 border-t border-slate-200 flex justify-end">
                        <button
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ExplanationModal;
