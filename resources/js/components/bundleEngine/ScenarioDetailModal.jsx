import React from 'react';

/**
 * ScenarioDetailModal Component
 *
 * Shows full details of a scenario including all services,
 * costs, and trade-offs.
 */
export function ScenarioDetailModal({ isOpen, scenario, onClose, onSelect }) {
    if (!isOpen || !scenario) return null;

    const {
        label,
        axis,
        services = [],
        cost,
        operations,
        context,
        safety,
        source,
    } = scenario;

    // Group services by category
    const servicesByCategory = services.reduce((acc, service) => {
        const category = service.service_category || 'Other';
        if (!acc[category]) acc[category] = [];
        acc[category].push(service);
        return acc;
    }, {});

    // Cost status styling
    const getCostStatusStyle = (status) => {
        switch (status) {
            case 'within_cap':
                return 'text-emerald-600 bg-emerald-50';
            case 'near_cap':
                return 'text-amber-600 bg-amber-50';
            case 'over_cap':
                return 'text-rose-600 bg-rose-50';
            default:
                return 'text-gray-600 bg-gray-50';
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/50 backdrop-blur-sm"
                onClick={onClose}
            />

            {/* Modal */}
            <div className="relative min-h-screen flex items-center justify-center p-4">
                <div className="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden">
                    {/* Header */}
                    <div className="px-6 py-5 bg-gradient-to-r from-teal-600 to-teal-500 text-white">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <span className="text-3xl">{label?.icon || '📋'}</span>
                                <div>
                                    <h2 className="text-xl font-semibold">{label?.title || 'Scenario Details'}</h2>
                                    <p className="text-teal-100 text-sm">{axis?.primary?.label || ''}</p>
                                </div>
                            </div>
                            <button
                                onClick={onClose}
                                className="p-2 hover:bg-white/20 rounded-lg transition-colors"
                            >
                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="p-6 max-h-[calc(100vh-200px)] overflow-y-auto">
                        {/* Description */}
                        <p className="text-gray-600 mb-6">{label?.description}</p>

                        {/* Overview Grid */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                            {/* Weekly Cost */}
                            <div className={`p-4 rounded-xl ${getCostStatusStyle(cost?.status)}`}>
                                <div className="text-xs font-medium opacity-75">Weekly Cost</div>
                                <div className="text-2xl font-bold">${cost?.weekly_estimate?.toLocaleString() || 0}</div>
                                <div className="text-xs">{cost?.status_label || ''}</div>
                            </div>

                            {/* Cap Utilization */}
                            <div className="p-4 rounded-xl bg-gray-50">
                                <div className="text-xs font-medium text-gray-500">Cap Utilization</div>
                                <div className="text-2xl font-bold text-gray-900">{cost?.cap_utilization || 0}%</div>
                                <div className="text-xs text-gray-500">of ${cost?.reference_cap?.toLocaleString() || 5000}/week</div>
                            </div>

                            {/* Weekly Hours */}
                            <div className="p-4 rounded-xl bg-gray-50">
                                <div className="text-xs font-medium text-gray-500">Weekly Hours</div>
                                <div className="text-2xl font-bold text-gray-900">{operations?.weekly_hours || 0}h</div>
                                <div className="text-xs text-gray-500">{operations?.weekly_visits || 0} visits</div>
                            </div>

                            {/* Disciplines */}
                            <div className="p-4 rounded-xl bg-gray-50">
                                <div className="text-xs font-medium text-gray-500">Disciplines</div>
                                <div className="text-2xl font-bold text-gray-900">{operations?.discipline_count || 0}</div>
                                <div className="text-xs text-gray-500">
                                    {operations?.in_person_percentage || 100}% in-person
                                </div>
                            </div>
                        </div>

                        {/* Cost Note */}
                        {cost?.note && (
                            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                                <div className="flex items-start gap-3">
                                    <span className="text-blue-500">ℹ️</span>
                                    <p className="text-sm text-blue-700">{cost.note}</p>
                                </div>
                            </div>
                        )}

                        {/* Trade-offs */}
                        {context?.trade_offs && (
                            <div className="mb-6">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">What This Scenario Emphasizes</h3>
                                <div className="space-y-2">
                                    <div className="p-3 bg-gray-50 rounded-lg">
                                        <span className="text-sm font-medium text-gray-700">Emphasis: </span>
                                        <span className="text-sm text-gray-600">{context.trade_offs.emphasis}</span>
                                    </div>
                                    <div className="p-3 bg-gray-50 rounded-lg">
                                        <span className="text-sm font-medium text-gray-700">Approach: </span>
                                        <span className="text-sm text-gray-600">{context.trade_offs.approach}</span>
                                    </div>
                                    <div className="p-3 bg-gray-50 rounded-lg">
                                        <span className="text-sm font-medium text-gray-700">Best For: </span>
                                        <span className="text-sm text-gray-600">{context.trade_offs.consideration}</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Key Benefits */}
                        {context?.key_benefits?.length > 0 && (
                            <div className="mb-6">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">Key Benefits</h3>
                                <ul className="space-y-2">
                                    {context.key_benefits.map((benefit, i) => (
                                        <li key={i} className="flex items-start gap-3 text-sm text-gray-600">
                                            <span className="text-teal-500 mt-0.5">✓</span>
                                            <span>{benefit}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Safety Warnings */}
                        {safety?.warnings?.length > 0 && (
                            <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                                <h3 className="text-sm font-semibold text-amber-800 mb-2">⚠️ Considerations</h3>
                                <ul className="space-y-1">
                                    {safety.warnings.map((warning, i) => (
                                        <li key={i} className="text-sm text-amber-700">• {warning}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Services by Category */}
                        <div className="mb-6">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Services Included</h3>
                            <div className="space-y-4">
                                {Object.entries(servicesByCategory).map(([category, categoryServices]) => (
                                    <div key={category} className="border border-gray-200 rounded-xl overflow-hidden">
                                        <div className="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                            <h4 className="font-medium text-gray-700 capitalize">{category}</h4>
                                        </div>
                                        <div className="divide-y divide-gray-100">
                                            {categoryServices.map((service, i) => (
                                                <div key={i} className="px-4 py-3">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <div className="font-medium text-gray-900">{service.service_name}</div>
                                                            <div className="text-sm text-gray-500">
                                                                {service.frequency?.label} • {service.duration?.label}
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="font-medium text-gray-900">
                                                                ${service.cost?.weekly_estimate?.toLocaleString() || 0}/wk
                                                            </div>
                                                            <span className={`text-xs px-2 py-0.5 rounded ${
                                                                service.priority?.level === 'core'
                                                                    ? 'bg-rose-100 text-rose-700'
                                                                    : service.priority?.level === 'recommended'
                                                                    ? 'bg-blue-100 text-blue-700'
                                                                    : 'bg-gray-100 text-gray-600'
                                                            }`}>
                                                                {service.priority?.level || 'optional'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    {service.clinical?.rationale && (
                                                        <p className="mt-1 text-xs text-gray-500">{service.clinical.rationale}</p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Source Info */}
                        <div className="text-xs text-gray-400 flex items-center gap-4">
                            <span>Source: {source?.type || 'rule_engine'}</span>
                            <span>•</span>
                            <span>Confidence: {source?.confidence || 'medium'}</span>
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                        <button
                            onClick={onClose}
                            className="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                        >
                            Close
                        </button>
                        <button
                            onClick={() => onSelect(scenario)}
                            className="px-6 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 transition-colors"
                        >
                            Select This Scenario
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default ScenarioDetailModal;

