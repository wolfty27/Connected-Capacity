import React from 'react';
import {
    X,
    Sparkles,
    Bot,
    CheckCircle2,
    Loader2,
    AlertTriangle,
    ArrowRight
} from 'lucide-react';

/**
 * BundleExplanationModal - Shows AI-generated explanation for a care bundle
 *
 * Part of Phase 7 (v2.2): UI Enhancements for AI Bundle Engine
 *
 * Features:
 * - Displays short explanation and detailed points
 * - Shows confidence level
 * - Indicates source (AI or rules-based)
 */
const BundleExplanationModal = ({
    isOpen,
    onClose,
    explanation,
    loading,
    scenarioTitle,
    error
}) => {
    if (!isOpen) return null;

    // Confidence level colors
    const confidenceColors = {
        'High Confidence': 'emerald',
        'High Confidence - Full HC Assessment': 'emerald',
        'Medium Confidence': 'amber',
        'Medium Confidence - CA Assessment': 'amber',
        'Low Confidence': 'rose',
        'Low Confidence - Limited Data': 'rose',
    };

    const getConfidenceColor = (label) => {
        for (const [key, color] of Object.entries(confidenceColors)) {
            if (label?.includes(key.split(' - ')[0])) {
                return color;
            }
        }
        return 'slate';
    };

    const sourceLabels = {
        'vertex_ai': { label: 'AI Generated', icon: Sparkles, color: 'purple' },
        'rules_based': { label: 'Rules-Based', icon: Bot, color: 'blue' },
    };

    const source = sourceLabels[explanation?.source] || sourceLabels['rules_based'];
    const SourceIcon = source.icon;
    const confidenceColor = getConfidenceColor(explanation?.confidence_label);

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                onClick={onClose}
            />

            {/* Modal */}
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
                    {/* Header */}
                    <div className="relative px-6 py-4 bg-gradient-to-r from-teal-600 to-teal-700">
                        <button
                            onClick={onClose}
                            className="absolute right-4 top-4 p-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition-colors"
                        >
                            <X className="w-4 h-4 text-white" />
                        </button>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-white/20 rounded-lg">
                                <Sparkles className="w-5 h-5 text-white" />
                            </div>
                            <div>
                                <h2 className="text-lg font-bold text-white">Bundle Explanation</h2>
                                <p className="text-sm text-teal-100">{scenarioTitle || 'Selected Scenario'}</p>
                            </div>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="px-6 py-5">
                        {loading && (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Loader2 className="w-8 h-8 text-teal-600 animate-spin mb-3" />
                                <p className="text-sm text-slate-600">Generating explanation...</p>
                            </div>
                        )}

                        {error && !loading && (
                            <div className="flex items-start gap-3 p-4 bg-rose-50 rounded-lg border border-rose-200">
                                <AlertTriangle className="w-5 h-5 text-rose-500 mt-0.5" />
                                <div>
                                    <p className="font-medium text-rose-800">Unable to generate explanation</p>
                                    <p className="text-sm text-rose-600 mt-1">{error}</p>
                                </div>
                            </div>
                        )}

                        {explanation && !loading && (
                            <div className="space-y-4">
                                {/* Source & Confidence Badges */}
                                <div className="flex items-center gap-2 flex-wrap">
                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-${source.color}-100 text-${source.color}-700`}>
                                        <SourceIcon className="w-3.5 h-3.5" />
                                        {source.label}
                                    </span>
                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-${confidenceColor}-100 text-${confidenceColor}-700`}>
                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                        {explanation.confidence_label}
                                    </span>
                                </div>

                                {/* Short Explanation */}
                                <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                    <p className="text-sm text-slate-700 leading-relaxed">
                                        {explanation.short_explanation}
                                    </p>
                                </div>

                                {/* Detailed Points */}
                                {explanation.detailed_points && explanation.detailed_points.length > 0 && (
                                    <div>
                                        <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                            Key Factors
                                        </h4>
                                        <ul className="space-y-2">
                                            {explanation.detailed_points.map((point, idx) => (
                                                <li key={idx} className="flex items-start gap-2">
                                                    <ArrowRight className="w-4 h-4 text-teal-500 mt-0.5 flex-shrink-0" />
                                                    <span className="text-sm text-slate-600">{point}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {/* Response Time (if available) */}
                                {explanation.response_time_ms > 0 && (
                                    <p className="text-[10px] text-slate-400 text-right">
                                        Generated in {explanation.response_time_ms}ms
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                        <button
                            onClick={onClose}
                            className="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-medium rounded-lg transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default BundleExplanationModal;

