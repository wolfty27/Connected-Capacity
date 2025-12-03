import React from 'react';

/**
 * ScenarioCard Component
 *
 * Displays a single scenario bundle as a selectable card.
 * Patient-experience oriented design, NOT "budget vs clinical".
 */
export function ScenarioCard({ scenario, isSelected, onSelect, onViewDetails }) {
    const {
        scenario_id,
        label,
        axis,
        cost,
        operations,
        context,
        safety,
        meta,
    } = scenario;

    const title = label?.title || 'Scenario';
    const description = label?.description || '';
    const icon = label?.icon || '📋';
    const primaryAxis = axis?.primary;

    // Cost status styling
    const getCostStatusStyle = (status) => {
        switch (status) {
            case 'within_cap':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'near_cap':
                return 'bg-amber-50 text-amber-700 border-amber-200';
            case 'over_cap':
                return 'bg-rose-50 text-rose-700 border-rose-200';
            default:
                return 'bg-gray-50 text-gray-700 border-gray-200';
        }
    };

    // Safety status styling
    const getSafetyStyle = () => {
        if (safety?.warnings?.length > 0) {
            return 'border-amber-300';
        }
        if (!safety?.meets_requirements) {
            return 'border-rose-300';
        }
        return '';
    };

    return (
        <div
            className={`
                relative rounded-xl border-2 p-5 cursor-pointer transition-all duration-200
                ${isSelected
                    ? 'border-teal-500 bg-teal-50/50 shadow-lg ring-2 ring-teal-200'
                    : 'border-gray-200 bg-white hover:border-teal-300 hover:shadow-md'
                }
                ${getSafetyStyle()}
            `}
            onClick={() => onSelect(scenario)}
        >
            {/* Recommended Badge */}
            {meta?.is_recommended && (
                <div className="absolute -top-3 left-4 px-3 py-1 bg-teal-600 text-white text-xs font-semibold rounded-full">
                    Recommended
                </div>
            )}

            {/* Header */}
            <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                    <span className="text-2xl">{icon}</span>
                    <div>
                        <h3 className="font-semibold text-gray-900">{title}</h3>
                        {primaryAxis && (
                            <span className="text-sm text-gray-500">{primaryAxis.label}</span>
                        )}
                    </div>
                </div>
                {isSelected && (
                    <div className="flex-shrink-0 w-6 h-6 bg-teal-600 rounded-full flex items-center justify-center">
                        <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                )}
            </div>

            {/* Description */}
            <p className="text-sm text-gray-600 mb-4 line-clamp-2">{description}</p>

            {/* Key Metrics */}
            <div className="grid grid-cols-2 gap-3 mb-4">
                {/* Weekly Cost */}
                <div className={`px-3 py-2 rounded-lg border ${getCostStatusStyle(cost?.status)}`}>
                    <div className="text-xs font-medium opacity-75">Weekly Cost</div>
                    <div className="text-lg font-bold">${cost?.weekly_estimate?.toLocaleString() || '0'}</div>
                    <div className="text-xs">{cost?.cap_utilization || 0}% of reference</div>
                </div>

                {/* Weekly Hours */}
                <div className="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50">
                    <div className="text-xs font-medium text-gray-500">Weekly Hours</div>
                    <div className="text-lg font-bold text-gray-900">{operations?.weekly_hours || 0}h</div>
                    <div className="text-xs text-gray-500">{operations?.weekly_visits || 0} visits</div>
                </div>
            </div>

            {/* Trade-offs */}
            {context?.trade_offs && (
                <div className="mb-4 p-3 bg-gray-50 rounded-lg">
                    <div className="text-xs font-medium text-gray-500 mb-1">Emphasis</div>
                    <p className="text-sm text-gray-700">{context.trade_offs.emphasis}</p>
                </div>
            )}

            {/* Key Benefits */}
            {context?.key_benefits?.length > 0 && (
                <div className="mb-4">
                    <div className="text-xs font-medium text-gray-500 mb-2">Key Benefits</div>
                    <ul className="space-y-1">
                        {context.key_benefits.slice(0, 2).map((benefit, i) => (
                            <li key={i} className="flex items-start gap-2 text-sm text-gray-600">
                                <span className="text-teal-500 mt-0.5">✓</span>
                                <span className="line-clamp-1">{benefit}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Safety Warnings */}
            {safety?.warnings?.length > 0 && (
                <div className="mb-4 p-2 bg-amber-50 border border-amber-200 rounded-lg">
                    <div className="flex items-center gap-2 text-amber-700 text-sm">
                        <span>⚠️</span>
                        <span>{safety.warnings[0]}</span>
                    </div>
                </div>
            )}

            {/* Service Count & Disciplines */}
            <div className="flex items-center justify-between text-sm text-gray-500">
                <span>{scenario.services?.length || 0} services</span>
                <span>{operations?.discipline_count || 0} disciplines</span>
            </div>

            {/* View Details Button */}
            <button
                onClick={(e) => {
                    e.stopPropagation();
                    onViewDetails?.(scenario);
                }}
                className="mt-4 w-full py-2 px-4 text-sm font-medium text-teal-600 bg-teal-50 hover:bg-teal-100 rounded-lg transition-colors"
            >
                View Details
            </button>
        </div>
    );
}

export default ScenarioCard;

