import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import InterraiStatusBadge from './InterraiStatusBadge';
import Button from '../UI/Button';

/**
 * InterraiSummaryPanel - Compact InterRAI assessment summary
 *
 * Shows key scores: MAPLe, CPS, ADL, CHESS
 * With status badge and quick actions
 */
const InterraiSummaryPanel = ({
    patientId,
    assessment,
    status = 'missing',
    onRequestReassessment,
    onViewDetails,
    compact = false,
    className = '',
}) => {
    const navigate = useNavigate();
    const [showActions, setShowActions] = useState(false);

    // Score color helpers
    const getMapleColor = (score) => {
        const colors = {
            1: 'text-emerald-600 bg-emerald-50',
            2: 'text-emerald-500 bg-emerald-50',
            3: 'text-amber-600 bg-amber-50',
            4: 'text-orange-600 bg-orange-50',
            5: 'text-rose-600 bg-rose-50',
        };
        return colors[score] || 'text-slate-400 bg-slate-50';
    };

    const getCpsColor = (score) => {
        if (score <= 1) return 'text-emerald-600 bg-emerald-50';
        if (score <= 3) return 'text-amber-600 bg-amber-50';
        return 'text-rose-600 bg-rose-50';
    };

    const getAdlColor = (score) => {
        if (score <= 1) return 'text-emerald-600 bg-emerald-50';
        if (score <= 3) return 'text-amber-600 bg-amber-50';
        return 'text-rose-600 bg-rose-50';
    };

    const getChessColor = (score) => {
        if (score <= 1) return 'text-emerald-600 bg-emerald-50';
        if (score <= 2) return 'text-amber-600 bg-amber-50';
        return 'text-rose-600 bg-rose-50';
    };

    const handleCompleteAssessment = () => {
        navigate(`/interrai/complete/${patientId}`);
    };

    const handleViewAssessment = () => {
        if (onViewDetails) {
            onViewDetails(assessment);
        } else if (assessment?.id) {
            navigate(`/interrai/assessments/${assessment.id}`);
        }
    };

    // No assessment state
    if (!assessment) {
        return (
            <div className={`bg-white rounded-lg border border-slate-200 p-4 ${className}`}>
                <div className="flex items-center justify-between mb-3">
                    <h4 className="font-medium text-slate-900">InterRAI Assessment</h4>
                    <InterraiStatusBadge status="missing" size="sm" />
                </div>
                <p className="text-sm text-slate-500 mb-4">
                    No InterRAI HC assessment on file. Assessment required per OHaH RFS.
                </p>
                <Button
                    variant="primary"
                    onClick={handleCompleteAssessment}
                    className="w-full"
                >
                    Complete Assessment
                </Button>
            </div>
        );
    }

    // Compact view for tables/lists
    if (compact) {
        return (
            <div className={`flex items-center gap-3 ${className}`}>
                <InterraiStatusBadge
                    status={status}
                    size="sm"
                    daysUntilStale={assessment.days_until_stale}
                />
                <div className="flex items-center gap-2 text-sm">
                    <span className={`px-1.5 py-0.5 rounded font-medium ${getMapleColor(assessment.maple_score)}`}>
                        M{assessment.maple_score || '-'}
                    </span>
                    <span className={`px-1.5 py-0.5 rounded font-medium ${getCpsColor(assessment.cognitive_performance_scale)}`}>
                        CPS{assessment.cognitive_performance_scale ?? '-'}
                    </span>
                </div>
            </div>
        );
    }

    // Full panel view
    return (
        <div
            className={`bg-white rounded-lg border border-slate-200 ${className}`}
            onMouseEnter={() => setShowActions(true)}
            onMouseLeave={() => setShowActions(false)}
        >
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-slate-100">
                <div className="flex items-center gap-3">
                    <h4 className="font-medium text-slate-900">InterRAI Assessment</h4>
                    <InterraiStatusBadge
                        status={status}
                        size="sm"
                        daysUntilStale={assessment.days_until_stale}
                    />
                </div>
                <span className="text-sm text-slate-500">
                    {assessment.assessment_date
                        ? new Date(assessment.assessment_date).toLocaleDateString()
                        : 'N/A'}
                </span>
            </div>

            {/* Scores Grid */}
            <div className="grid grid-cols-4 gap-2 p-4">
                {/* MAPLe */}
                <div className="text-center">
                    <div
                        className={`text-2xl font-bold rounded-lg py-2 ${getMapleColor(assessment.maple_score)}`}
                    >
                        {assessment.maple_score || '-'}
                    </div>
                    <div className="text-xs text-slate-500 mt-1">MAPLe</div>
                </div>

                {/* CPS */}
                <div className="text-center">
                    <div
                        className={`text-2xl font-bold rounded-lg py-2 ${getCpsColor(assessment.cognitive_performance_scale)}`}
                    >
                        {assessment.cognitive_performance_scale ?? '-'}
                    </div>
                    <div className="text-xs text-slate-500 mt-1">CPS</div>
                </div>

                {/* ADL */}
                <div className="text-center">
                    <div
                        className={`text-2xl font-bold rounded-lg py-2 ${getAdlColor(assessment.adl_hierarchy)}`}
                    >
                        {assessment.adl_hierarchy ?? '-'}
                    </div>
                    <div className="text-xs text-slate-500 mt-1">ADL</div>
                </div>

                {/* CHESS */}
                <div className="text-center">
                    <div
                        className={`text-2xl font-bold rounded-lg py-2 ${getChessColor(assessment.chess_score)}`}
                    >
                        {assessment.chess_score ?? '-'}
                    </div>
                    <div className="text-xs text-slate-500 mt-1">CHESS</div>
                </div>
            </div>

            {/* RUG Classification */}
            {(assessment.rug_classification || assessment.rug_group) && (
                <div className="px-4 pb-3">
                    <div className="p-2 bg-teal-50 border border-teal-200 rounded-lg">
                        <div className="flex items-center justify-between">
                            <div>
                                <span className="text-xs font-semibold text-teal-600 uppercase">RUG</span>
                                <span className="ml-2 text-sm font-bold text-teal-800">
                                    {assessment.rug_classification?.rug_group || assessment.rug_group}
                                </span>
                            </div>
                            <span className="text-xs text-teal-600">
                                {assessment.rug_classification?.rug_category || assessment.rug_category}
                            </span>
                        </div>
                        {(assessment.rug_classification?.rug_description || assessment.rug_description) && (
                            <div className="mt-1 text-xs text-teal-700">
                                {assessment.rug_classification?.rug_description || assessment.rug_description}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Risk Flags */}
            {(assessment.falls_in_last_90_days || assessment.wandering_flag || assessment.high_risk_flags?.length > 0) && (
                <div className="px-4 pb-3 flex flex-wrap gap-1.5">
                    {assessment.falls_in_last_90_days && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-700">
                            Falls Risk
                        </span>
                    )}
                    {assessment.wandering_flag && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                            Wandering
                        </span>
                    )}
                    {assessment.high_risk_flags?.map((flag, i) => (
                        <span
                            key={i}
                            className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700"
                        >
                            {flag}
                        </span>
                    ))}
                </div>
            )}

            {/* Actions */}
            <div className={`border-t border-slate-100 px-4 py-3 flex gap-2 transition-opacity ${showActions ? 'opacity-100' : 'opacity-50'}`}>
                <Button variant="secondary" onClick={handleViewAssessment} className="flex-1">
                    View Details
                </Button>
                {(status === 'stale' || status === 'missing') && (
                    <Button
                        variant="primary"
                        onClick={onRequestReassessment || handleCompleteAssessment}
                        className="flex-1"
                    >
                        {status === 'stale' ? 'Reassess' : 'Complete'}
                    </Button>
                )}
            </div>
        </div>
    );
};

/**
 * Inline scores display for use in headers/cards
 */
export const InterraiScoresInline = ({ assessment, className = '' }) => {
    if (!assessment) return null;

    return (
        <div className={`flex items-center gap-2 text-sm ${className}`}>
            <span className="text-slate-500">MAPLe:</span>
            <span className="font-semibold">{assessment.maple_score || '-'}</span>
            <span className="text-slate-300">|</span>
            <span className="text-slate-500">CPS:</span>
            <span className="font-semibold">{assessment.cognitive_performance_scale ?? '-'}</span>
            <span className="text-slate-300">|</span>
            <span className="text-slate-500">ADL:</span>
            <span className="font-semibold">{assessment.adl_hierarchy ?? '-'}</span>
        </div>
    );
};

export default InterraiSummaryPanel;
