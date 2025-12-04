import React, { useState } from 'react';
import { useSchedulerContext } from '../SchedulerContext';

/**
 * ReviewTab (Stub)
 * 
 * Central place to review grouped AI proposals:
 * - Proposal groups by AI action, patient, or staff rebalancing
 * - Accept-group-with-confirmation flows
 * - Impact metrics for each proposal group
 * 
 * Features (to be implemented in Phase 4):
 * - Proposal groups list with status
 * - Detail view with proposed assignments
 * - Impact metrics sidebar
 * - Batch accept/reject actions
 */
const ReviewTab = () => {
  const { weekRange, proposalGroups } = useSchedulerContext();
  const [selectedGroup, setSelectedGroup] = useState(null);

  // Mock proposal groups for the stub
  const mockGroups = [
    {
      id: 'high-risk-first',
      title: 'High-Risk First Visits',
      count: 12,
      createdAt: '10:24am',
      status: 'pending',
      source: 'AI Action',
    },
    {
      id: 'psw-rebalance',
      title: 'PSW Load Rebalancing',
      count: 8,
      createdAt: '9:45am',
      status: 'partial',
      source: 'AI Action',
    },
    {
      id: 'nursing-gaps',
      title: 'Nursing Coverage Gaps',
      count: 5,
      createdAt: '8:30am',
      status: 'completed',
      source: 'Manual',
    },
  ];

  const groups = proposalGroups.length > 0 ? proposalGroups : mockGroups;

  const getStatusBadge = (status) => {
    switch (status) {
      case 'pending':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600">Not reviewed</span>;
      case 'partial':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">Partially accepted</span>;
      case 'completed':
        return <span className="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Completed ✓</span>;
      default:
        return null;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-lg font-bold text-slate-900">Review AI Proposals</h2>
        <p className="text-sm text-slate-500">
          Review and approve grouped assignment suggestions for {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
        </p>
      </div>

      {/* Main Content - Split Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Proposal Groups List */}
        <div className="lg:col-span-1 space-y-3">
          <h3 className="text-sm font-bold text-slate-700">PROPOSAL GROUPS</h3>
          {groups.map((group) => (
            <button
              key={group.id}
              onClick={() => setSelectedGroup(group)}
              className={`w-full text-left p-4 rounded-lg border transition-colors ${
                selectedGroup?.id === group.id
                  ? 'bg-blue-50 border-blue-300'
                  : 'bg-white border-slate-200 hover:bg-slate-50'
              }`}
            >
              <div className="flex items-start justify-between">
                <div>
                  <div className="font-medium text-slate-900">{group.title}</div>
                  <div className="text-xs text-slate-500 mt-1">
                    {group.count} visits • Created {group.createdAt}
                  </div>
                </div>
                {getStatusBadge(group.status)}
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
              <div>
                <h3 className="text-lg font-bold text-slate-900">{selectedGroup.title}</h3>
                <p className="text-sm text-slate-500">{selectedGroup.count} proposed assignments</p>
              </div>

              {/* Impact Metrics */}
              <div>
                <h4 className="text-sm font-bold text-slate-700 mb-3">IMPACT METRICS</h4>
                <div className="grid grid-cols-3 gap-4">
                  <div className="bg-slate-50 rounded-lg p-3 text-center">
                    <div className="text-xs text-slate-500">Travel</div>
                    <div className="text-lg font-bold text-emerald-600">-22%</div>
                  </div>
                  <div className="bg-slate-50 rounded-lg p-3 text-center">
                    <div className="text-xs text-slate-500">Capacity</div>
                    <div className="text-lg font-bold text-amber-600">+2 over</div>
                  </div>
                  <div className="bg-slate-50 rounded-lg p-3 text-center">
                    <div className="text-xs text-slate-500">Continuity</div>
                    <div className="text-lg font-bold text-slate-700">88%</div>
                  </div>
                </div>
              </div>

              {/* Proposed Assignments Table */}
              <div>
                <h4 className="text-sm font-bold text-slate-700 mb-3">PROPOSED ASSIGNMENTS</h4>
                <div className="border border-slate-200 rounded-lg overflow-hidden">
                  <table className="w-full text-sm">
                    <thead className="bg-slate-50">
                      <tr>
                        <th className="w-8 px-3 py-2"></th>
                        <th className="text-left px-3 py-2 text-slate-600">Patient</th>
                        <th className="text-left px-3 py-2 text-slate-600">Service</th>
                        <th className="text-left px-3 py-2 text-slate-600">Staff</th>
                        <th className="text-left px-3 py-2 text-slate-600">Match</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      <tr className="hover:bg-slate-50">
                        <td className="px-3 py-2"><input type="checkbox" className="rounded" /></td>
                        <td className="px-3 py-2">Eleanor V.</td>
                        <td className="px-3 py-2">PSW Visit</td>
                        <td className="px-3 py-2">Jane D.</td>
                        <td className="px-3 py-2"><span className="text-emerald-600 font-medium">85%</span></td>
                      </tr>
                      <tr className="hover:bg-slate-50">
                        <td className="px-3 py-2"><input type="checkbox" className="rounded" /></td>
                        <td className="px-3 py-2">Marcus T.</td>
                        <td className="px-3 py-2">RN Visit</td>
                        <td className="px-3 py-2">Amanda C.</td>
                        <td className="px-3 py-2"><span className="text-emerald-600 font-medium">92%</span></td>
                      </tr>
                      <tr className="hover:bg-slate-50">
                        <td className="px-3 py-2"><input type="checkbox" className="rounded" /></td>
                        <td className="px-3 py-2">Clara O.</td>
                        <td className="px-3 py-2">PT Session</td>
                        <td className="px-3 py-2">Noah G.</td>
                        <td className="px-3 py-2"><span className="text-blue-600 font-medium">78%</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Actions */}
              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                <button className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">
                  Reject Selected
                </button>
                <button className="px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700">
                  Accept Selected
                </button>
                <button className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                  Accept All ({selectedGroup.count})
                </button>
              </div>
            </div>
          ) : (
            <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-12 text-center">
              <div className="text-slate-400">
                <span className="text-3xl">📋</span>
                <p className="mt-2 text-sm">Select a proposal group to view details</p>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Placeholder message */}
      <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-6 text-center">
        <div className="text-slate-400 text-sm">
          <strong>Phase 4:</strong> This tab will be fully implemented with live proposal groups from the AutoAssignEngine.
        </div>
      </div>
    </div>
  );
};

export default ReviewTab;

