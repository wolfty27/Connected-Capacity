import React from 'react';

/**
 * SuggestionRow
 *
 * Displays an AI-generated suggestion inline under a service requirement.
 *
 * Props:
 * - suggestion: { suggested_staff_id, suggested_staff_name, match_status, confidence_score, ... }
 * - onAccept: (suggestion) => void
 * - onManual: () => void - Open manual assignment modal
 * - onExplain: (suggestion) => void - Open explanation modal
 * - isAccepting: boolean - Loading state during accept
 */
const SuggestionRow = ({ suggestion, onAccept, onManual, onExplain, isAccepting }) => {
    if (!suggestion || suggestion.match_status === 'none') {
        return null;
    }

    const getMatchStatusConfig = (status) => {
        switch (status) {
            case 'strong':
                return {
                    bg: 'bg-emerald-50',
                    border: 'border-emerald-200',
                    badge: 'bg-emerald-100 text-emerald-800',
                    icon: '⚡',
                    label: 'Strong Match',
                };
            case 'moderate':
                return {
                    bg: 'bg-blue-50',
                    border: 'border-blue-200',
                    badge: 'bg-blue-100 text-blue-800',
                    icon: '👍',
                    label: 'Good Match',
                };
            case 'weak':
                return {
                    bg: 'bg-amber-50',
                    border: 'border-amber-200',
                    badge: 'bg-amber-100 text-amber-800',
                    icon: '🤔',
                    label: 'Weak Match',
                };
            default:
                return {
                    bg: 'bg-slate-50',
                    border: 'border-slate-200',
                    badge: 'bg-slate-100 text-slate-800',
                    icon: '❓',
                    label: 'Unknown',
                };
        }
    };

    const config = getMatchStatusConfig(suggestion.match_status);

    return (
        <div className={`mt-1 px-2 py-1.5 rounded ${config.bg} border ${config.border} flex items-center justify-between gap-2`}>
            {/* Left: Suggestion info */}
            <div className="flex items-center gap-2 flex-1 min-w-0">
                <span className="text-sm">{config.icon}</span>
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                        <span className="text-xs font-medium text-slate-800 truncate">
                            {suggestion.suggested_staff_name || 'Unknown Staff'}
                        </span>
                        <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${config.badge}`}>
                            {Math.round(suggestion.confidence_score)}%
                        </span>
                    </div>
                    <div className="flex items-center gap-2 text-[10px] text-slate-500">
                        {suggestion.suggested_staff_role && (
                            <span>{suggestion.suggested_staff_role}</span>
                        )}
                        {suggestion.estimated_travel_minutes > 0 && (
                            <span>• {suggestion.estimated_travel_minutes} min travel</span>
                        )}
                        {suggestion.continuity_note && (
                            <span className="text-emerald-600">• {suggestion.continuity_note}</span>
                        )}
                    </div>
                </div>
            </div>

            {/* Right: Actions */}
            <div className="flex items-center gap-1 flex-shrink-0">
                {/* Explain */}
                <button
                    onClick={() => onExplain(suggestion)}
                    className="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                    title="Why this match?"
                >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </button>

                {/* Manual assign */}
                <button
                    onClick={onManual}
                    className="p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded transition-colors"
                    title="Choose different staff"
                >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>

                {/* Accept */}
                <button
                    onClick={() => onAccept(suggestion)}
                    disabled={isAccepting}
                    className="flex items-center gap-0.5 px-2 py-1 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded transition-colors disabled:opacity-50"
                >
                    {isAccepting ? (
                        <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    ) : (
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                    <span>Accept</span>
                </button>
            </div>
        </div>
    );
};

export default SuggestionRow;
