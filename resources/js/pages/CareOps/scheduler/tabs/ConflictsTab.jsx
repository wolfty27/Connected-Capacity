import React from 'react';
import { useSchedulerContext, VIEW_MODES } from '../SchedulerContext';
import { useConflictsData } from '../hooks/useConflictsData';

/**
 * ConflictsTab
 * 
 * Aggregated view of scheduling conflicts and "no match" items:
 * - Hard constraint violations (double-booking, capacity)
 * - "No match" outcomes from AutoAssignEngine
 * - Travel time warnings
 * - AI-suggested resolutions
 */
const ConflictsTab = () => {
  const { weekRange, setViewMode } = useSchedulerContext();
  const {
    loading,
    error,
    filteredConflicts,
    conflictCounts,
    selectedConflictId,
    selectedConflict,
    filterType,
    setSelectedConflictId,
    setFilterType,
    dismissConflict,
    refresh,
    getResolutions,
    totalConflicts,
  } = useConflictsData();

  // Get type badge styling
  const getTypeBadge = (type) => {
    switch (type) {
      case 'no_match':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">No Match</span>;
      case 'double_booked':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">Double Booked</span>;
      case 'travel':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">Travel</span>;
      case 'capacity':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700">Capacity</span>;
      case 'spacing':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">Spacing</span>;
      default:
        return <span className="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-700">Other</span>;
    }
  };

  // Get severity indicator
  const getSeverityColor = (severity) => {
    switch (severity) {
      case 'high': return 'border-l-red-500';
      case 'medium': return 'border-l-amber-500';
      case 'low': return 'border-l-blue-500';
      default: return 'border-l-slate-300';
    }
  };

  // Filter tabs config
  const filters = [
    { id: 'all', label: 'All' },
    { id: 'no_match', label: 'No Match' },
    { id: 'double_booked', label: 'Double Booked' },
    { id: 'travel', label: 'Travel' },
    { id: 'capacity', label: 'Capacity' },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin" />
          <span className="text-sm text-slate-500">Detecting conflicts...</span>
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
          <h2 className="text-lg font-bold text-slate-900">Conflicts &amp; No-Match</h2>
          <p className="text-sm text-slate-500">
            {totalConflicts} issue{totalConflicts !== 1 ? 's' : ''} for {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
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

      {/* Filter Tabs */}
      <div className="flex flex-wrap gap-2">
        {filters.map((filter) => {
          const count = conflictCounts[filter.id] || 0;
          return (
            <button
              key={filter.id}
              onClick={() => setFilterType(filter.id)}
              className={`px-3 py-1.5 text-sm rounded-full border transition-colors ${
                filterType === filter.id
                  ? 'bg-blue-50 border-blue-300 text-blue-700'
                  : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
              }`}
            >
              {filter.label} ({count})
            </button>
          );
        })}
      </div>

      {/* No Conflicts State */}
      {filteredConflicts.length === 0 ? (
        <div className="bg-emerald-50 rounded-lg border border-emerald-200 p-12 text-center">
          <span className="text-4xl">✓</span>
          <h3 className="mt-4 text-lg font-bold text-emerald-700">No Conflicts!</h3>
          <p className="text-sm text-emerald-600 mt-2">
            {filterType === 'all' 
              ? 'All scheduling constraints are satisfied.'
              : `No ${filterType.replace('_', ' ')} conflicts found.`
            }
          </p>
        </div>
      ) : (
        /* Main Content - Split Layout */
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Left: Conflicts List */}
          <div className="space-y-3">
            <h3 className="text-sm font-bold text-slate-700">
              CONFLICT LIST ({filteredConflicts.length})
            </h3>
            <div className="space-y-2 max-h-[600px] overflow-y-auto">
              {filteredConflicts.map((conflict) => (
                <button
                  key={conflict.id}
                  onClick={() => setSelectedConflictId(conflict.id)}
                  className={`w-full text-left p-4 rounded-lg border-l-4 transition-colors ${
                    getSeverityColor(conflict.severity)
                  } ${
                    selectedConflictId === conflict.id
                      ? 'bg-blue-50 border border-blue-300'
                      : 'bg-white border border-slate-200 hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="font-medium text-slate-900">
                        {conflict.patient_name || conflict.staff_name || 'Unknown'}
                      </div>
                      <div className="text-xs text-slate-500 mt-1">
                        {conflict.service_type_name || conflict.type.replace('_', ' ')} - {conflict.date}
                      </div>
                    </div>
                    {getTypeBadge(conflict.type)}
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Right: Selected Conflict Details */}
          <div>
            {selectedConflict ? (
              <div className="bg-white rounded-lg border border-slate-200 p-6 space-y-6 sticky top-4">
                {/* Conflict Header */}
                <div>
                  <div className="flex items-center gap-2 mb-2">
                    {getTypeBadge(selectedConflict.type)}
                    <span className={`text-xs ${
                      selectedConflict.severity === 'high' ? 'text-red-600' :
                      selectedConflict.severity === 'medium' ? 'text-amber-600' :
                      'text-blue-600'
                    }`}>
                      {selectedConflict.severity?.toUpperCase()} priority
                    </span>
                  </div>
                  <h3 className="text-lg font-bold text-slate-900">
                    {selectedConflict.patient_name || selectedConflict.staff_name}
                  </h3>
                  {selectedConflict.service_type_name && (
                    <p className="text-sm text-slate-500">
                      {selectedConflict.service_type_name} • {selectedConflict.date}
                    </p>
                  )}
                </div>

                {/* AI Explanation */}
                <div className="bg-slate-50 rounded-lg p-4">
                  <div className="flex items-center gap-2 mb-2">
                    <span className="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 text-xs">⚠</span>
                    <span className="font-bold text-slate-800">Issue Details</span>
                  </div>
                  <p className="text-sm text-slate-600">
                    {selectedConflict.summary}
                  </p>
                </div>

                {/* Additional Details */}
                {selectedConflict.type === 'double_booked' && (
                  <div className="text-sm space-y-2">
                    <div className="font-medium text-slate-700">Conflicting Assignments:</div>
                    <div className="grid grid-cols-2 gap-2">
                      <div className="bg-slate-50 rounded p-2">
                        <div className="font-medium">{selectedConflict.assignment1?.patient_name}</div>
                        <div className="text-xs text-slate-500">
                          {selectedConflict.assignment1?.start_time} - {selectedConflict.assignment1?.end_time}
                        </div>
                      </div>
                      <div className="bg-slate-50 rounded p-2">
                        <div className="font-medium">{selectedConflict.assignment2?.patient_name}</div>
                        <div className="text-xs text-slate-500">
                          {selectedConflict.assignment2?.start_time} - {selectedConflict.assignment2?.end_time}
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {selectedConflict.type === 'capacity' && (
                  <div className="text-sm">
                    <div className="font-medium text-slate-700 mb-2">Current Utilization</div>
                    <div className="flex items-center gap-3">
                      <div className="flex-1 h-3 bg-slate-200 rounded-full overflow-hidden">
                        <div 
                          className={`h-full rounded-full ${
                            selectedConflict.utilization > 120 ? 'bg-red-500' : 'bg-amber-500'
                          }`}
                          style={{ width: `${Math.min(100, selectedConflict.utilization)}%` }}
                        />
                      </div>
                      <span className={`font-bold ${
                        selectedConflict.utilization > 120 ? 'text-red-600' : 'text-amber-600'
                      }`}>
                        {Math.round(selectedConflict.utilization)}%
                      </span>
                    </div>
                  </div>
                )}

                {/* Suggested Resolutions */}
                <div>
                  <h4 className="text-sm font-bold text-slate-700 mb-3">SUGGESTED RESOLUTIONS</h4>
                  <div className="space-y-2">
                    {getResolutions(selectedConflict).map((resolution) => (
                      <div 
                        key={resolution.id}
                        className="bg-white rounded-lg border border-slate-200 p-3"
                      >
                        <h5 className="font-medium text-slate-900">{resolution.label}</h5>
                        <p className="text-xs text-slate-500 mt-1">
                          {resolution.description}
                        </p>
                        <button 
                          onClick={() => {
                            // For now, navigate to schedule tab for manual resolution
                            setViewMode(VIEW_MODES.SCHEDULE);
                          }}
                          className="mt-2 w-full px-3 py-1.5 text-sm font-medium text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50"
                        >
                          Apply Resolution
                        </button>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-between pt-4 border-t border-slate-200">
                  <button
                    onClick={() => dismissConflict(selectedConflict.id)}
                    className="text-sm text-slate-500 hover:text-slate-700"
                  >
                    Dismiss
                  </button>
                  <button
                    onClick={() => setViewMode(VIEW_MODES.SCHEDULE)}
                    className="px-4 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-200"
                  >
                    Open in Schedule →
                  </button>
                </div>
              </div>
            ) : (
              <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-12 text-center h-full flex flex-col items-center justify-center min-h-[400px]">
                <div className="text-slate-400">
                  <span className="text-3xl">⚠️</span>
                  <p className="mt-2 text-sm">Select a conflict to view details and resolution options</p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default ConflictsTab;
