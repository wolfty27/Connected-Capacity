import React, { useState } from 'react';
import {
    Activity,
    AlertTriangle,
    Brain,
    Heart,
    ChevronDown,
    ChevronUp,
    Stethoscope,
    HelpCircle
} from 'lucide-react';

/**
 * ClinicalInsightsPanel - Displays algorithm scores and triggered CAPs
 *
 * Part of Phase 7 (v2.2): UI Enhancements for AI Bundle Engine
 *
 * Displays:
 * - CA Algorithm scores (PSA, Rehabilitation, CHESS, Pain, etc.)
 * - Triggered Clinical Assessment Protocols (CAPs)
 * - BMHS indicators (if available)
 */
const ClinicalInsightsPanel = ({ algorithmScores, triggeredCAPs, profileSummary }) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const [showTooltip, setShowTooltip] = useState(null);

    // Ensure triggeredCAPs is always an array
    const capsArray = Array.isArray(triggeredCAPs) ? triggeredCAPs : [];

    if (!algorithmScores && capsArray.length === 0) {
        return null;
    }

    // Algorithm score definitions with interpretations
    const algorithmDefs = {
        personalSupport: {
            name: 'Personal Support',
            abbrev: 'PSA',
            range: [1, 6],
            icon: Heart,
            color: 'rose',
            description: 'Personal care support need level',
            interpret: (score) => {
                if (score <= 2) return { label: 'Low', color: 'emerald' };
                if (score <= 4) return { label: 'Moderate', color: 'amber' };
                return { label: 'High', color: 'rose' };
            }
        },
        rehabilitation: {
            name: 'Rehabilitation',
            abbrev: 'RHA',
            range: [1, 5],
            icon: Activity,
            color: 'blue',
            description: 'Rehabilitation potential and therapy need',
            interpret: (score) => {
                if (score <= 2) return { label: 'Maintenance', color: 'slate' };
                if (score <= 3) return { label: 'Moderate', color: 'blue' };
                return { label: 'High', color: 'emerald' };
            }
        },
        chessCA: {
            name: 'Health Instability',
            abbrev: 'CHESS',
            range: [0, 5],
            icon: Stethoscope,
            color: 'purple',
            description: 'Changes in health, end-stage disease, signs and symptoms',
            interpret: (score) => {
                if (score <= 1) return { label: 'Stable', color: 'emerald' };
                if (score <= 3) return { label: 'Moderate', color: 'amber' };
                return { label: 'Unstable', color: 'rose' };
            }
        },
        pain: {
            name: 'Pain Scale',
            abbrev: 'PAIN',
            range: [0, 4],
            icon: AlertTriangle,
            color: 'amber',
            description: 'Pain frequency and intensity',
            interpret: (score) => {
                if (score === 0) return { label: 'None', color: 'emerald' };
                if (score <= 2) return { label: 'Mild', color: 'amber' };
                return { label: 'Severe', color: 'rose' };
            }
        },
        distressedMood: {
            name: 'Distressed Mood',
            abbrev: 'DMS',
            range: [0, 12],
            icon: Brain,
            color: 'indigo',
            description: 'Depression and mood disturbance indicators',
            interpret: (score) => {
                if (score <= 3) return { label: 'Low', color: 'emerald' };
                if (score <= 7) return { label: 'Moderate', color: 'amber' };
                return { label: 'High', color: 'rose' };
            }
        },
        serviceUrgency: {
            name: 'Service Urgency',
            abbrev: 'SUA',
            range: [1, 6],
            icon: AlertTriangle,
            color: 'red',
            description: 'Urgency of service initiation',
            interpret: (score) => {
                if (score <= 2) return { label: 'Routine', color: 'slate' };
                if (score <= 4) return { label: 'Elevated', color: 'amber' };
                return { label: 'Urgent', color: 'rose' };
            }
        }
    };

    // CAP level colors
    const capLevelColors = {
        IMPROVE: 'rose',
        PREVENT: 'amber',
        FACILITATE: 'blue',
        MAINTAIN: 'slate',
        NOT_TRIGGERED: 'slate'
    };

    const renderAlgorithmScore = (key, score) => {
        if (score === null || score === undefined) return null;

        const def = algorithmDefs[key];
        if (!def) return null;

        const Icon = def.icon;
        const interpretation = def.interpret(score);

        return (
            <div
                key={key}
                className="relative flex items-center gap-2 p-2 bg-white rounded-lg border border-slate-200 hover:border-slate-300 transition-colors"
                onMouseEnter={() => setShowTooltip(key)}
                onMouseLeave={() => setShowTooltip(null)}
            >
                <div className={`p-1.5 rounded bg-${def.color}-50`}>
                    <Icon className={`w-3.5 h-3.5 text-${def.color}-600`} />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-baseline gap-1">
                        <span className="text-xs font-semibold text-slate-700">{def.abbrev}</span>
                        <span className="text-[10px] text-slate-400">({score}/{def.range[1]})</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="flex-1 h-1 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                className={`h-full bg-${interpretation.color}-500 rounded-full transition-all`}
                                style={{ width: `${(score / def.range[1]) * 100}%` }}
                            />
                        </div>
                        <span className={`text-[9px] font-medium text-${interpretation.color}-600 whitespace-nowrap`}>
                            {interpretation.label}
                        </span>
                    </div>
                </div>

                {/* Tooltip */}
                {showTooltip === key && (
                    <div className="absolute z-50 bottom-full left-0 mb-1 w-48 p-2 bg-slate-900 text-white text-xs rounded-lg shadow-lg">
                        <div className="font-semibold mb-1">{def.name}</div>
                        <div className="text-slate-300">{def.description}</div>
                        <div className="mt-1 pt-1 border-t border-slate-700">
                            Score: {score} of {def.range[1]} ({interpretation.label})
                        </div>
                        <div className="absolute left-4 bottom-0 transform translate-y-1/2 rotate-45 w-2 h-2 bg-slate-900" />
                    </div>
                )}
            </div>
        );
    };

    const renderCAP = (cap) => {
        const level = cap.level || 'NOT_TRIGGERED';
        const colorClass = capLevelColors[level] || 'slate';

        return (
            <div
                key={cap.name}
                className={`p-2 rounded-lg border bg-${colorClass}-50 border-${colorClass}-200`}
            >
                <div className="flex items-center justify-between gap-2">
                    <span className={`text-xs font-semibold text-${colorClass}-700`}>
                        {cap.name}
                    </span>
                    <span className={`px-1.5 py-0.5 text-[9px] font-bold rounded bg-${colorClass}-100 text-${colorClass}-700`}>
                        {level}
                    </span>
                </div>
                {cap.description && (
                    <p className={`mt-1 text-[10px] text-${colorClass}-600 leading-tight`}>
                        {cap.description}
                    </p>
                )}
            </div>
        );
    };

    // Count significant scores
    const significantScores = algorithmScores ? Object.entries(algorithmScores).filter(
        ([key, score]) => score !== null && score !== undefined && algorithmDefs[key]
    ).length : 0;

    const activeCAPs = capsArray.filter(cap => cap.level !== 'NOT_TRIGGERED').length;

    return (
        <div className="mt-4 bg-gradient-to-br from-slate-50 to-white rounded-xl border border-slate-200 overflow-hidden">
            {/* Header */}
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-50 transition-colors"
            >
                <div className="flex items-center gap-3">
                    <div className="p-1.5 bg-purple-100 rounded-lg">
                        <Brain className="w-4 h-4 text-purple-600" />
                    </div>
                    <div className="text-left">
                        <h3 className="text-sm font-semibold text-slate-800">Clinical Insights</h3>
                        <p className="text-[10px] text-slate-500">
                            {significantScores} algorithm scores • {activeCAPs} active CAPs
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {activeCAPs > 0 && (
                        <span className="px-2 py-0.5 bg-rose-100 text-rose-700 text-[10px] font-semibold rounded-full">
                            {activeCAPs} Intervention{activeCAPs > 1 ? 's' : ''}
                        </span>
                    )}
                    {isExpanded ? (
                        <ChevronUp className="w-4 h-4 text-slate-400" />
                    ) : (
                        <ChevronDown className="w-4 h-4 text-slate-400" />
                    )}
                </div>
            </button>

            {/* Expanded Content */}
            {isExpanded && (
                <div className="px-4 pb-4 space-y-4">
                    {/* Algorithm Scores Grid */}
                    {algorithmScores && Object.keys(algorithmScores).some(k => algorithmScores[k] !== null) && (
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <Activity className="w-3.5 h-3.5 text-slate-400" />
                                <span className="text-xs font-medium text-slate-600">Algorithm Scores</span>
                                <HelpCircle
                                    className="w-3 h-3 text-slate-300 cursor-help"
                                    title="Derived from InterRAI Contact Assessment algorithms"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                {Object.entries(algorithmScores).map(([key, score]) =>
                                    renderAlgorithmScore(key, score)
                                )}
                            </div>
                        </div>
                    )}

                    {/* Triggered CAPs */}
                    {capsArray.length > 0 && (
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <AlertTriangle className="w-3.5 h-3.5 text-slate-400" />
                                <span className="text-xs font-medium text-slate-600">Clinical Assessment Protocols</span>
                            </div>
                            <div className="space-y-2">
                                {capsArray.map(cap => renderCAP(cap))}
                            </div>
                        </div>
                    )}

                    {/* BMHS Indicators */}
                    {profileSummary?.has_bmhs && (
                        <div className="p-2 bg-indigo-50 rounded-lg border border-indigo-200">
                            <div className="flex items-center gap-2">
                                <Brain className="w-3.5 h-3.5 text-indigo-600" />
                                <span className="text-xs font-semibold text-indigo-700">Mental Health Screen Available</span>
                            </div>
                            {profileSummary.self_harm_risk_level > 0 && (
                                <div className="mt-1 flex items-center gap-1">
                                    <span className="text-[10px] text-indigo-600">Self-Harm Risk:</span>
                                    <span className={`px-1.5 py-0.5 text-[9px] font-bold rounded ${
                                        profileSummary.self_harm_risk_level >= 2
                                            ? 'bg-rose-100 text-rose-700'
                                            : 'bg-amber-100 text-amber-700'
                                    }`}>
                                        Level {profileSummary.self_harm_risk_level}
                                    </span>
                                </div>
                            )}
                            {profileSummary.violence_risk_level > 0 && (
                                <div className="mt-1 flex items-center gap-1">
                                    <span className="text-[10px] text-indigo-600">Violence Risk:</span>
                                    <span className={`px-1.5 py-0.5 text-[9px] font-bold rounded ${
                                        profileSummary.violence_risk_level >= 2
                                            ? 'bg-rose-100 text-rose-700'
                                            : 'bg-amber-100 text-amber-700'
                                    }`}>
                                        Level {profileSummary.violence_risk_level}
                                    </span>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default ClinicalInsightsPanel;

