import React, { useState, useMemo } from 'react';
import { useSchedulerContext } from '../SchedulerContext';
import { useReviewData } from '../hooks/useReviewData';

/**
 * ReviewTab
 * 
 * Central place to review grouped AI proposals:
 * - Proposal groups by match quality, service category, or patient
 * - Accept-group-with-confirmation flows
 * - Impact metrics for each proposal group
 * - Batch accept/reject actions
 */
const ReviewTab = () => {
  const { weekRange } = useSchedulerContext();
  const {
    loading,
    error,
    proposalGroups,
    selectedGroupId,
    selectedGroup,
    setSelectedGroupId,
    acceptSuggestion,
    acceptGroup,
    acceptSelected,
    rejectSuggestion,
    refresh,
    isProcessing,
    totalSuggestions,
  } = useReviewData();

  const [selectedSuggestions, setSelectedSuggestions] = useState(new Set());
  const [acceptingGroup, setAcceptingGroup] = useState(false);
  const [acceptResult, setAcceptResult] = useState(null);

  // Toggle suggestion selection
  const toggleSuggestion = (suggestion) => {
    const key = `${suggestion.patient_id}-${suggestion.service_type_id}`;
    setSelectedSuggestions(prev => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  };

  // Select all in current group
  const selectAll = () => {
    if (!selectedGroup) return;
    const keys = selectedGroup.suggestions.map(
      s => `${s.patient_id}-${s.service_type_id}`
    );
    setSelectedSuggestions(new Set(keys));
  };

  // Clear selection
  const clearSelection = () => {
    setSelectedSuggestions(new Set());
  };

  // Accept selected suggestions
  const handleAcceptSelected = async () => {
    if (selectedSuggestions.size === 0) return;
    setAcceptingGroup(true);
    setAcceptResult(null);
    
    try {
      const result = await acceptSelected(Array.from(selectedSuggestions));
      setAcceptResult(result);
      setSelectedSuggestions(new Set());
    } finally {
      setAcceptingGroup(false);
    }
  };

  // Accept entire group
  const handleAcceptGroup = async () => {
    if (!selectedGroup) return;
    setAcceptingGroup(true);
    setAcceptResult(null);
    
    try {
      const result = await acceptGroup(selectedGroup.id);
      setAcceptResult(result);
      setSelectedSuggestions(new Set());
    } finally {
      setAcceptingGroup(false);
    }
  };

  // Get match status color
  const getMatchColor = (status) => {
    switch (status) {
      case 'strong': return 'text-emerald-600 bg-emerald-50';
      case 'moderate': return 'text-blue-600 bg-blue-50';
      case 'weak': return 'text-amber-600 bg-amber-50';
      default: return 'text-slate-600 bg-slate-50';
    }
  };

  // Get group type icon
  const getGroupIcon = (type) => {
    switch (type) {
      case 'by_match_quality': return '⚡';
      case 'by_service_category': return '🏥';
      case 'by_patient': return '👤';
      default: return '📋';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin" />
          <span className="text-sm text-slate-500">Loading proposals...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 rounded-lg border border-red-200 p-6 text-center">
        <span className="text-red-600">{error}</span>
        <button onClick={refresh} className="ml-4 text-blue-600 hover:underline">
          Retry
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900">Review AI Proposals</h2>
          <p className="text-sm text-slate-500">
            {totalSuggestions} suggestions for {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
          </p>
        </div>
        <button
          onClick={refresh}
          className="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
        >
          <span>🔄</span>
          Refresh
        </button>
      </div>

      {/* Accept Result Toast */}
      {acceptResult && (
        <div className={`rounded-lg p-4 ${
          acceptResult.failed === 0 ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200'
        }`}>
          <div className="flex items-center justify-between">
            <div>
              {acceptResult.successful > 0 && (
                <span className="text-emerald-700">
                  ✓ {acceptResult.successful} assignment{acceptResult.successful !== 1 ? 's' : ''} created
                </span>
              )}
              {acceptResult.failed > 0 && (
                <span className="text-amber-700 ml-3">
                  ⚠ {acceptResult.failed} failed
                </span>
              )}
            </div>
            <button
              onClick={() => setAcceptResult(null)}
              className="text-slate-400 hover:text-slate-600"
            >
              ✕
            </button>
          </div>
        </div>
      )}

      {/* No Suggestions State */}
      {proposalGroups.length === 0 ? (
        <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-12 text-center">
          <span className="text-4xl">✓</span>
          <h3 className="mt-4 text-lg font-bold text-slate-700">All Caught Up!</h3>
          <p className="text-sm text-slate-500 mt-2">
            No pending AI proposals to review. Generate new suggestions from the Schedule tab.
          </p>
        </div>
      ) : (
        /* Main Content - Split Layout */
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left: Proposal Groups List */}
          <div className="lg:col-span-1 space-y-3">
            <h3 className="text-sm font-bold text-slate-700">PROPOSAL GROUPS ({proposalGroups.length})</h3>
            {proposalGroups.map((group) => (
              <button
                key={group.id}
                onClick={() => {
                  setSelectedGroupId(group.id);
                  setSelectedSuggestions(new Set());
                }}
                className={`w-full text-left p-4 rounded-lg border transition-colors ${
                  selectedGroupId === group.id
                    ? 'bg-blue-50 border-blue-300'
                    : 'bg-white border-slate-200 hover:bg-slate-50'
                }`}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex items-start gap-2">
                    <span className="text-lg">{getGroupIcon(group.type)}</span>
                    <div>
                      <div className="font-medium text-slate-900">{group.title}</div>
                      <div className="text-xs text-slate-500 mt-0.5">
                        {group.count} suggestion{group.count !== 1 ? 's' : ''}
                      </div>
                    </div>
                  </div>
                  {group.metrics?.strongCount > 0 && (
                    <span className="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">
                      {group.metrics.strongCount} strong
                    </span>
                  )}
                </div>
                <div className="text-xs text-slate-400 mt-2">Source: {group.source}</div>
              </button>
            ))}
          </div>

          {/* Right: Selected Group Details */}
          <div className="lg:col-span-2">
            {selectedGroup ? (
              <div className="bg-white rounded-lg border border-slate-200 p-6 space-y-6">
                {/* Group Header */}
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className="text-lg font-bold text-slate-900">{selectedGroup.title}</h3>
                    <p className="text-sm text-slate-500">{selectedGroup.description}</p>
                  </div>
                  <span className="text-2xl">{getGroupIcon(selectedGroup.type)}</span>
                </div>

                {/* Impact Metrics */}
                <div>
                  <h4 className="text-sm font-bold text-slate-700 mb-3">METRICS</h4>
                  <div className="grid grid-cols-3 gap-4">
                    {selectedGroup.metrics?.strongCount !== undefined && (
                      <div className="bg-emerald-50 rounded-lg p-3 text-center">
                        <div className="text-xs text-emerald-600">Strong Matches</div>
                        <div className="text-lg font-bold text-emerald-700">
                          {selectedGroup.metrics.strongCount}
                        </div>
                      </div>
                    )}
                    {selectedGroup.metrics?.moderateCount !== undefined && (
                      <div className="bg-blue-50 rounded-lg p-3 text-center">
                        <div className="text-xs text-blue-600">Moderate</div>
                        <div className="text-lg font-bold text-blue-700">
                          {selectedGroup.metrics.moderateCount}
                        </div>
                      </div>
                    )}
                    {selectedGroup.metrics?.avgConfidence !== undefined && (
                      <div className="bg-slate-50 rounded-lg p-3 text-center">
                        <div className="text-xs text-slate-600">Avg Confidence</div>
                        <div className="text-lg font-bold text-slate-700">
                          {selectedGroup.metrics.avgConfidence}%
                        </div>
                      </div>
                    )}
                    {selectedGroup.metrics?.totalHours !== undefined && (
                      <div className="bg-slate-50 rounded-lg p-3 text-center">
                        <div className="text-xs text-slate-600">Total Hours</div>
                        <div className="text-lg font-bold text-slate-700">
                          {selectedGroup.metrics.totalHours.toFixed(1)}h
                        </div>
                      </div>
                    )}
                    {selectedGroup.metrics?.serviceTypes !== undefined && (
                      <div className="bg-slate-50 rounded-lg p-3 text-center">
                        <div className="text-xs text-slate-600">Service Types</div>
                        <div className="text-lg font-bold text-slate-700">
                          {selectedGroup.metrics.serviceTypes}
                        </div>
                      </div>
                    )}
                  </div>
                </div>

                {/* Proposed Assignments Table */}
                <div>
                  <div className="flex items-center justify-between mb-3">
                    <h4 className="text-sm font-bold text-slate-700">PROPOSED ASSIGNMENTS</h4>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={selectAll}
                        className="text-xs text-blue-600 hover:underline"
                      >
                        Select All
                      </button>
                      {selectedSuggestions.size > 0 && (
                        <button
                          onClick={clearSelection}
                          className="text-xs text-slate-500 hover:underline"
                        >
                          Clear
                        </button>
                      )}
                    </div>
                  </div>
                  <div className="border border-slate-200 rounded-lg overflow-hidden">
                    <table className="w-full text-sm">
                      <thead className="bg-slate-50">
                        <tr>
                          <th className="w-8 px-3 py-2"></th>
                          <th className="text-left px-3 py-2 text-slate-600">Patient</th>
                          <th className="text-left px-3 py-2 text-slate-600">Service</th>
                          <th className="text-left px-3 py-2 text-slate-600">Staff</th>
                          <th className="text-left px-3 py-2 text-slate-600">Match</th>
                          <th className="w-20 px-3 py-2"></th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {selectedGroup.suggestions.map((suggestion) => {
                          const key = `${suggestion.patient_id}-${suggestion.service_type_id}`;
                          const isSelected = selectedSuggestions.has(key);
                          const processing = isProcessing(suggestion);
                          
                          return (
                            <tr 
                              key={key} 
                              className={`hover:bg-slate-50 ${processing ? 'opacity-50' : ''}`}
                            >
                              <td className="px-3 py-2">
                                <input
                                  type="checkbox"
                                  checked={isSelected}
                                  onChange={() => toggleSuggestion(suggestion)}
                                  disabled={processing}
                                  className="rounded border-slate-300"
                                />
                              </td>
                              <td className="px-3 py-2 font-medium">{suggestion.patient_name}</td>
                              <td className="px-3 py-2">{suggestion.service_type_name}</td>
                              <td className="px-3 py-2">{suggestion.suggested_staff_name || 'TBD'}</td>
                              <td className="px-3 py-2">
                                <span className={`px-2 py-0.5 text-xs rounded-full ${getMatchColor(suggestion.match_status)}`}>
                                  {suggestion.confidence_score || 0}%
                                </span>
                              </td>
                              <td className="px-3 py-2">
                                <button
                                  onClick={() => acceptSuggestion(suggestion)}
                                  disabled={processing}
                                  className="text-xs text-emerald-600 hover:text-emerald-700 font-medium disabled:opacity-50"
                                >
                                  {processing ? '...' : 'Accept'}
                                </button>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-between pt-4 border-t border-slate-200">
                  <div className="text-sm text-slate-500">
                    {selectedSuggestions.size > 0 
                      ? `${selectedSuggestions.size} selected`
                      : `${selectedGroup.count} total`
                    }
                  </div>
                  <div className="flex items-center gap-3">
                    {selectedSuggestions.size > 0 && (
                      <button
                        onClick={handleAcceptSelected}
                        disabled={acceptingGroup}
                        className="px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 disabled:opacity-50"
                      >
                        Accept Selected ({selectedSuggestions.size})
                      </button>
                    )}
                    <button
                      onClick={handleAcceptGroup}
                      disabled={acceptingGroup}
                      className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
                    >
                      {acceptingGroup && (
                        <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                      )}
                      Accept All ({selectedGroup.count})
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-12 text-center h-full flex flex-col items-center justify-center">
                <div className="text-slate-400">
                  <span className="text-3xl">📋</span>
                  <p className="mt-2 text-sm">Select a proposal group to view details</p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default ReviewTab;
