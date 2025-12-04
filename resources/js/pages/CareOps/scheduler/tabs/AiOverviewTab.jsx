import React from 'react';
import { useSchedulerContext, VIEW_MODES } from '../SchedulerContext';
import { useAiOverviewData } from '../hooks/useAiOverviewData';

/**
 * AiOverviewTab
 * 
 * "Monday morning view" - AI summarizes the week and surfaces what to do first.
 * 
 * Features:
 * - Quick Win card showing safe auto-assign opportunities
 * - Key Insights panels (Patients Requiring Attention, High-Priority Unscheduled, Staff Capacity)
 * - Metrics Summary cards (TFS, Unscheduled, Missed Care, Net Capacity)
 * - AI Action cards that navigate to Review/Schedule tabs
 */
const AiOverviewTab = () => {
  const { weekRange, setViewMode } = useSchedulerContext();
  const {
    loading,
    suggestionsLoading,
    suggestionCounts,
    quickWin,
    highPriorityUnscheduled,
    patientsRequiringAttention,
    staffCapacity,
    metricsSummary,
    regenerateSuggestions,
  } = useAiOverviewData();

  const getBandColor = (band) => {
    switch (band?.toUpperCase()) {
      case 'A': return 'text-emerald-600';
      case 'B': return 'text-blue-600';
      case 'C': return 'text-amber-600';
      case 'D': return 'text-red-600';
      default: return 'text-slate-600';
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'green': return 'text-emerald-600';
      case 'amber': return 'text-amber-600';
      case 'red': return 'text-red-600';
      default: return 'text-slate-600';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin" />
          <span className="text-sm text-slate-500">Loading AI insights...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900">AI Overview</h2>
          <p className="text-sm text-slate-500">
            Week of {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
          </p>
        </div>
        <button
          onClick={regenerateSuggestions}
          disabled={suggestionsLoading}
          className="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors disabled:opacity-50"
        >
          {suggestionsLoading ? (
            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          ) : (
            <span>🔄</span>
          )}
          Refresh Insights
        </button>
      </div>

      {/* Quick Win Card */}
      {quickWin.count > 0 ? (
        <div className="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-lg border border-blue-200 p-6">
          <div className="flex items-start justify-between">
            <div>
              <h3 className="text-lg font-bold text-blue-900">Quick Win: Auto-Assign Safe Visits</h3>
              <p className="text-sm text-blue-700 mt-1">
                Our AI has identified <strong>{quickWin.count} visits</strong> with high-confidence matches
                ({suggestionCounts.strong} strong, {suggestionCounts.moderate} moderate).
              </p>
              <p className="text-sm text-emerald-600 mt-2">
                This action can save you an estimated {quickWin.estimatedMinutesSaved} minutes.
              </p>
            </div>
            <button
              onClick={() => setViewMode(VIEW_MODES.REVIEW)}
              className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap"
            >
              Review &amp; Approve Assignments
            </button>
          </div>
        </div>
      ) : (
        <div className="bg-slate-50 rounded-lg border border-slate-200 p-6">
          <div className="flex items-center gap-3">
            <span className="text-2xl">✓</span>
            <div>
              <h3 className="font-bold text-slate-700">No Quick Wins Available</h3>
              <p className="text-sm text-slate-500">
                {suggestionCounts.total === 0 
                  ? 'Run Auto-Assign from the Schedule tab to generate suggestions.'
                  : `${suggestionCounts.weak} weak matches and ${suggestionCounts.none} no-match items require manual review.`
                }
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Suggestion Summary Bar */}
      {suggestionCounts.total > 0 && (
        <div className="bg-white rounded-lg border border-slate-200 p-4">
          <div className="flex items-center gap-6 text-sm">
            <span className="font-medium text-slate-700">AI Suggestions:</span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
              <span>{suggestionCounts.strong} Strong</span>
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-full bg-blue-500" />
              <span>{suggestionCounts.moderate} Moderate</span>
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-full bg-amber-500" />
              <span>{suggestionCounts.weak} Weak</span>
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-full bg-red-500" />
              <span>{suggestionCounts.none} No Match</span>
            </span>
          </div>
        </div>
      )}

      {/* Key Insights Grid */}
      <div>
        <h3 className="text-base font-bold text-slate-800 mb-4">Key Insights</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Patients Requiring Attention */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center text-red-600 text-xs font-bold">!</span>
              <h4 className="font-bold text-slate-800">Patients Requiring Attention</h4>
            </div>
            <p className="text-xs text-slate-500 mb-3">
              {patientsRequiringAttention.length > 0 
                ? 'High-risk patients or those with no staff match.'
                : 'No patients requiring immediate attention.'
              }
            </p>
            <div className="space-y-2 text-sm">
              {patientsRequiringAttention.length > 0 ? (
                patientsRequiringAttention.map((patient, idx) => (
                  <div key={patient.patient_id || idx} className="flex items-center justify-between">
                    <div>
                      <span className="font-medium">{patient.patient_name}</span>
                      <div className="text-xs text-red-600">
                        {patient.risk_flags?.includes('dangerous') ? 'High risk' :
                         patient.risk_flags?.includes('warning') ? 'Needs attention' : 
                         'No staff match'}
                      </div>
                    </div>
                    <button 
                      onClick={() => setViewMode(VIEW_MODES.SCHEDULE)}
                      className="text-xs text-blue-600 hover:underline"
                    >
                      View Schedule
                    </button>
                  </div>
                ))
              ) : (
                <div className="text-center py-4 text-emerald-600">
                  <span className="text-lg">✓</span>
                  <p className="text-xs mt-1">All clear</p>
                </div>
              )}
            </div>
          </div>

          {/* High-Priority Unscheduled Services */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">📋</span>
              <h4 className="font-bold text-slate-800">High-Priority Unscheduled</h4>
            </div>
            <p className="text-xs text-slate-500 mb-3">
              {highPriorityUnscheduled.length > 0
                ? 'Services that need to be scheduled urgently.'
                : 'No high-priority unscheduled services.'
              }
            </p>
            <div className="space-y-2 text-sm">
              {highPriorityUnscheduled.length > 0 ? (
                highPriorityUnscheduled.map((patient, idx) => (
                  <div key={patient.patient_id || idx} className="flex items-center justify-between">
                    <div>
                      <span className="font-medium">{patient.services?.[0]?.service_type_name || 'Service'}</span>
                      <div className="text-xs text-amber-600">For: {patient.patient_name}</div>
                    </div>
                    <button 
                      onClick={() => setViewMode(VIEW_MODES.SCHEDULE)}
                      className="text-xs text-blue-600 hover:underline"
                    >
                      Find Staff
                    </button>
                  </div>
                ))
              ) : (
                <div className="text-center py-4 text-emerald-600">
                  <span className="text-lg">✓</span>
                  <p className="text-xs mt-1">All scheduled</p>
                </div>
              )}
            </div>
          </div>

          {/* Staff Approaching Capacity */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">📊</span>
              <h4 className="font-bold text-slate-800">Staff Approaching Capacity</h4>
            </div>
            <p className="text-xs text-slate-500 mb-3">
              {staffCapacity.length > 0
                ? 'Staff members with high scheduled workloads.'
                : 'All staff have comfortable capacity levels.'
              }
            </p>
            <div className="space-y-2 text-sm">
              {staffCapacity.length > 0 ? (
                staffCapacity.map((staff, idx) => {
                  const utilization = staff.utilization?.utilization || 0;
                  const barColor = utilization > 100 ? 'bg-red-500' : 
                                   utilization > 90 ? 'bg-amber-500' : 'bg-emerald-500';
                  const textColor = utilization > 100 ? 'text-red-600' : 
                                    utilization > 90 ? 'text-amber-600' : 'text-emerald-600';
                  return (
                    <div key={staff.id || idx} className="flex items-center justify-between">
                      <span className="font-medium truncate max-w-[100px]">{staff.name}</span>
                      <div className="flex items-center gap-2">
                        <div className="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                          <div 
                            className={`h-full ${barColor} rounded-full`} 
                            style={{ width: `${Math.min(100, utilization)}%` }} 
                          />
                        </div>
                        <span className={`text-xs font-medium ${textColor}`}>
                          {Math.round(utilization)}%
                        </span>
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="text-center py-4 text-emerald-600">
                  <span className="text-lg">✓</span>
                  <p className="text-xs mt-1">Balanced workloads</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Metrics Summary */}
      <div>
        <h3 className="text-base font-bold text-slate-800 mb-4">Metrics Summary</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {/* Time-to-First-Service */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Time-to-First-Service</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">
              {metricsSummary.tfs.value?.toFixed(1) || '—'}{metricsSummary.tfs.unit}
            </div>
            <div className={`text-xs font-medium ${getBandColor(metricsSummary.tfs.band)}`}>
              Band {metricsSummary.tfs.band} {metricsSummary.tfs.band === 'A' ? '✓' : 
                metricsSummary.tfs.value <= metricsSummary.tfs.target ? '✓' : '!'}
            </div>
          </div>

          {/* Unscheduled Care */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Unscheduled Care</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">
              {metricsSummary.unscheduled.value?.toFixed(0) || 0}{metricsSummary.unscheduled.unit}
            </div>
            <div className="text-xs text-slate-500">
              {metricsSummary.unscheduled.patients} patients, {metricsSummary.unscheduled.visits} visits
            </div>
          </div>

          {/* Missed Care Rate */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Missed Care Rate</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">
              {metricsSummary.missedCare.value?.toFixed(2) || '0.00'}{metricsSummary.missedCare.unit}
            </div>
            <div className={`text-xs font-medium ${getBandColor(metricsSummary.missedCare.band)}`}>
              Band {metricsSummary.missedCare.band} {metricsSummary.missedCare.band === 'A' ? '✓' : '!'}
            </div>
          </div>

          {/* Net Capacity */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Net Capacity</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">
              {metricsSummary.netCapacity?.value?.toFixed(1) || '—'}h
            </div>
            <div className={`text-xs font-medium ${getStatusColor(metricsSummary.netCapacity?.status)}`}>
              {metricsSummary.netCapacity?.status?.toUpperCase() || '—'} {
                metricsSummary.netCapacity?.status === 'green' ? '✓' : 
                metricsSummary.netCapacity?.status === 'red' ? '!' : ''
              }
            </div>
          </div>
        </div>
      </div>

      {/* AI Actions Row */}
      <div>
        <h3 className="text-base font-bold text-slate-800 mb-4">Quick Actions</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <button
            onClick={() => setViewMode(VIEW_MODES.SCHEDULE)}
            className="bg-white rounded-lg border border-slate-200 p-4 text-left hover:bg-slate-50 transition-colors"
          >
            <div className="flex items-center gap-3">
              <span className="text-2xl">📅</span>
              <div>
                <div className="font-bold text-slate-800">View Schedule</div>
                <div className="text-xs text-slate-500">Open the weekly calendar grid</div>
              </div>
            </div>
          </button>

          <button
            onClick={() => setViewMode(VIEW_MODES.REVIEW)}
            className="bg-white rounded-lg border border-slate-200 p-4 text-left hover:bg-slate-50 transition-colors"
          >
            <div className="flex items-center gap-3">
              <span className="text-2xl">📋</span>
              <div>
                <div className="font-bold text-slate-800">Review Proposals</div>
                <div className="text-xs text-slate-500">
                  {suggestionCounts.autoAssignable > 0 
                    ? `${suggestionCounts.autoAssignable} ready for approval`
                    : 'View AI proposal groups'
                  }
                </div>
              </div>
            </div>
          </button>

          <button
            onClick={() => setViewMode(VIEW_MODES.CONFLICTS)}
            className="bg-white rounded-lg border border-slate-200 p-4 text-left hover:bg-slate-50 transition-colors"
          >
            <div className="flex items-center gap-3">
              <span className="text-2xl">⚠️</span>
              <div>
                <div className="font-bold text-slate-800">Resolve Conflicts</div>
                <div className="text-xs text-slate-500">
                  {suggestionCounts.none > 0 
                    ? `${suggestionCounts.none} items need attention`
                    : 'View scheduling conflicts'
                  }
                </div>
              </div>
            </div>
          </button>
        </div>
      </div>
    </div>
  );
};

export default AiOverviewTab;
