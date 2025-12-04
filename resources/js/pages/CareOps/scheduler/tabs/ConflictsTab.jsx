import React, { useState } from 'react';
import { useSchedulerContext } from '../SchedulerContext';

/**
 * ConflictsTab (Stub)
 * 
 * Aggregated view of scheduling conflicts and "no match" items:
 * - Hard constraint violations (from SchedulingEngine)
 * - "No match" outcomes from AutoAssignEngine
 * - AI-suggested resolutions
 * 
 * Features (to be implemented in Phase 5):
 * - Conflicts list with type filtering
 * - Detail view with AI explanations
 * - Resolution action buttons
 */
const ConflictsTab = () => {
  const { weekRange, conflicts, noMatchItems } = useSchedulerContext();
  const [selectedConflict, setSelectedConflict] = useState(null);
  const [activeFilter, setActiveFilter] = useState('all');

  // Mock conflicts for the stub
  const mockConflicts = [
    {
      id: 1,
      type: 'no_match',
      patient: 'Eleanor Pena',
      service: 'Physical Therapy',
      date: 'Oct 28',
      summary: 'No qualified provider available within travel radius',
    },
    {
      id: 2,
      type: 'double_booked',
      patient: 'Arthur Morgan',
      service: 'Wound Care',
      date: 'Oct 28',
      summary: 'Staff member double-booked at 10:30 AM',
    },
    {
      id: 3,
      type: 'travel',
      patient: 'Cody Fisher',
      service: 'Check-in',
      date: 'Oct 29',
      summary: 'Excessive travel time between visits (45+ minutes)',
    },
    {
      id: 4,
      type: 'no_match',
      patient: 'Jane Cooper',
      service: 'Occupational Therapy',
      date: 'Oct 29',
      summary: 'No OT available in patient region',
    },
  ];

  const allConflicts = conflicts.length > 0 ? conflicts : mockConflicts;

  const filteredConflicts = activeFilter === 'all'
    ? allConflicts
    : allConflicts.filter(c => c.type === activeFilter);

  const getTypeBadge = (type) => {
    switch (type) {
      case 'no_match':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">No Match</span>;
      case 'double_booked':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">Double Booked</span>;
      case 'travel':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">Travel</span>;
      case 'spacing':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700">Spacing</span>;
      default:
        return <span className="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-700">Other</span>;
    }
  };

  const filters = [
    { id: 'all', label: 'All', count: allConflicts.length },
    { id: 'no_match', label: 'No Match', count: allConflicts.filter(c => c.type === 'no_match').length },
    { id: 'double_booked', label: 'Double Booked', count: allConflicts.filter(c => c.type === 'double_booked').length },
    { id: 'travel', label: 'Travel', count: allConflicts.filter(c => c.type === 'travel').length },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-lg font-bold text-slate-900">Conflicts &amp; No-Match</h2>
        <p className="text-sm text-slate-500">
          Scheduling conflicts and unresolved assignments for {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
        </p>
      </div>

      {/* Filter Tabs */}
      <div className="flex flex-wrap gap-2">
        {filters.map((filter) => (
          <button
            key={filter.id}
            onClick={() => setActiveFilter(filter.id)}
            className={`px-3 py-1.5 text-sm rounded-full border transition-colors ${
              activeFilter === filter.id
                ? 'bg-blue-50 border-blue-300 text-blue-700'
                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
          >
            {filter.label} ({filter.count})
          </button>
        ))}
      </div>

      {/* Main Content - Split Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Conflicts List */}
        <div className="space-y-3">
          <h3 className="text-sm font-bold text-slate-700">CONFLICT LIST</h3>
          {filteredConflicts.map((conflict) => (
            <button
              key={conflict.id}
              onClick={() => setSelectedConflict(conflict)}
              className={`w-full text-left p-4 rounded-lg border transition-colors ${
                selectedConflict?.id === conflict.id
                  ? 'bg-blue-50 border-blue-300'
                  : 'bg-white border-slate-200 hover:bg-slate-50'
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <div className="font-medium text-slate-900">{conflict.patient}</div>
                  <div className="text-xs text-slate-500 mt-1">
                    {conflict.service} - {conflict.date}
                  </div>
                </div>
                {getTypeBadge(conflict.type)}
              </div>
            </button>
          ))}

          {filteredConflicts.length === 0 && (
            <div className="bg-emerald-50 rounded-lg border border-emerald-200 p-6 text-center">
              <span className="text-2xl">✓</span>
              <p className="text-sm text-emerald-700 mt-2">No conflicts in this category!</p>
            </div>
          )}
        </div>

        {/* Right: Selected Conflict Details */}
        <div>
          {selectedConflict ? (
            <div className="bg-white rounded-lg border border-slate-200 p-6 space-y-6">
              {/* Conflict Header */}
              <div>
                <div className="text-xs text-blue-600 font-medium mb-1">
                  Scheduling Conflict for Visit #{selectedConflict.id}
                </div>
                <h3 className="text-lg font-bold text-slate-900">
                  {selectedConflict.patient} - {selectedConflict.service}
                </h3>
                <p className="text-sm text-slate-500">
                  Tuesday, {selectedConflict.date} @ 2:00 PM - 3:00 PM
                </p>
              </div>

              {/* AI Explanation */}
              <div className="bg-slate-50 rounded-lg p-4">
                <div className="flex items-center gap-2 mb-2">
                  <span className="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs">💡</span>
                  <span className="font-bold text-slate-800">AI Explanation: No Match Found</span>
                </div>
                <p className="text-sm text-slate-600">
                  {selectedConflict.summary}. The AI could not find a qualified provider because no one with the 
                  '{selectedConflict.service}' skill is available within the client's required 30-minute travel radius 
                  during the requested time slot.
                </p>
              </div>

              {/* Suggested Resolutions */}
              <div>
                <h4 className="text-sm font-bold text-slate-700 mb-3">AI-SUGGESTED RESOLUTIONS</h4>
                <div className="space-y-3">
                  <div className="bg-white rounded-lg border border-slate-200 p-4">
                    <h5 className="font-medium text-slate-900">Adjust Time Window</h5>
                    <p className="text-xs text-slate-500 mt-1">
                      Look for provider availability in a wider time frame on the same day.
                    </p>
                    <button className="mt-3 w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                      Suggest Alternative Times
                    </button>
                  </div>
                  
                  <div className="bg-white rounded-lg border border-slate-200 p-4">
                    <h5 className="font-medium text-slate-900">Relax Region Boundary</h5>
                    <p className="text-xs text-slate-500 mt-1">
                      Expand search to include providers from adjacent regions who may be available.
                    </p>
                    <button className="mt-3 w-full px-4 py-2 border border-blue-600 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50">
                      Expand Search Radius
                    </button>
                  </div>
                  
                  <div className="bg-white rounded-lg border border-slate-200 p-4">
                    <h5 className="font-medium text-slate-900">Escalate for Approval</h5>
                    <p className="text-xs text-slate-500 mt-1">
                      Send a request to a supervisor for special provider assignment (SSPO).
                    </p>
                    <button className="mt-3 w-full px-4 py-2 border border-slate-300 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50">
                      Escalate Request
                    </button>
                  </div>
                </div>
              </div>

              {/* Manual Assign */}
              <div className="pt-4 border-t border-slate-200">
                <button className="w-full px-4 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-200">
                  Assign Manually →
                </button>
              </div>
            </div>
          ) : (
            <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-12 text-center h-full flex flex-col items-center justify-center">
              <div className="text-slate-400">
                <span className="text-3xl">⚠️</span>
                <p className="mt-2 text-sm">Select a conflict to view details and resolution options</p>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Placeholder message */}
      <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-6 text-center">
        <div className="text-slate-400 text-sm">
          <strong>Phase 5:</strong> This tab will be fully implemented with live conflict detection from the SchedulingEngine and AutoAssignEngine.
        </div>
      </div>
    </div>
  );
};

export default ConflictsTab;

