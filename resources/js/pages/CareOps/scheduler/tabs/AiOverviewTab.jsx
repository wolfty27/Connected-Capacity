import React from 'react';
import { useSchedulerContext, VIEW_MODES } from '../SchedulerContext';

/**
 * AiOverviewTab (Stub)
 * 
 * "Monday morning view" - AI summarizes the week and surfaces what to do first.
 * 
 * Features (to be implemented in Phase 2):
 * - Quick Win card showing safe auto-assign opportunities
 * - Key Insights panels (Patients Requiring Attention, High-Priority Unscheduled, Staff Capacity)
 * - Metrics Summary cards (TFS, Unscheduled, Missed Care, Net Capacity)
 * - AI Action cards that navigate to Review tab
 */
const AiOverviewTab = () => {
  const { weekRange, setViewMode } = useSchedulerContext();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-lg font-bold text-slate-900">AI Overview</h2>
        <p className="text-sm text-slate-500">
          Week of {weekRange.startDate.toLocaleDateString()} - {weekRange.endDate.toLocaleDateString()}
        </p>
      </div>

      {/* Quick Win Card - Stub */}
      <div className="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-lg border border-blue-200 p-6">
        <div className="flex items-start justify-between">
          <div>
            <h3 className="text-lg font-bold text-blue-900">Quick Win: Auto-Assign Safe Visits</h3>
            <p className="text-sm text-blue-700 mt-1">
              Our AI has identified <strong>14 low-complexity visits</strong> with high-confidence matches.
            </p>
            <p className="text-sm text-emerald-600 mt-2">
              This action can save you an estimated 45 minutes.
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

      {/* Key Insights Grid - Stub */}
      <div>
        <h3 className="text-base font-bold text-slate-800 mb-4">Key Insights</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Patients Requiring Attention */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center text-red-600 text-xs font-bold">!</span>
              <h4 className="font-bold text-slate-800">Patients Requiring Immediate Attention</h4>
            </div>
            <p className="text-xs text-slate-500 mb-3">Top 5 patients with the highest risk scores this week.</p>
            <div className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <div>
                  <span className="font-medium">Eleanor Vance</span>
                  <div className="text-xs text-red-600">Missed critical appointment</div>
                </div>
                <button className="text-xs text-blue-600 hover:underline">View Schedule</button>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <span className="font-medium">Marcus Thorne</span>
                  <div className="text-xs text-red-600">Medication non-adherence</div>
                </div>
                <button className="text-xs text-blue-600 hover:underline">View Schedule</button>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <span className="font-medium">Clara Oswald</span>
                  <div className="text-xs text-amber-600">Declining vitals reported</div>
                </div>
                <button className="text-xs text-blue-600 hover:underline">View Schedule</button>
              </div>
            </div>
          </div>

          {/* High-Priority Unscheduled Services */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">📋</span>
              <h4 className="font-bold text-slate-800">High-Priority Unscheduled Services</h4>
            </div>
            <p className="text-xs text-slate-500 mb-3">Top 5 services that need to be scheduled urgently.</p>
            <div className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <div>
                  <span className="font-medium">Wound Care</span>
                  <div className="text-xs text-red-600">For: J. Doe (Urgent)</div>
                </div>
                <button className="text-xs text-blue-600 hover:underline">Find Staff</button>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <span className="font-medium">Post-Op Checkup</span>
                  <div className="text-xs text-amber-600">For: A. Smith</div>
                </div>
                <button className="text-xs text-blue-600 hover:underline">Find Staff</button>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <span className="font-medium">Personal Support</span>
                  <div className="text-xs text-amber-600">For: L. Johnson</div>
                </div>
                <button className="text-xs text-blue-600 hover:underline">Find Staff</button>
              </div>
            </div>
          </div>

          {/* Staff Approaching Capacity */}
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">📊</span>
              <h4 className="font-bold text-slate-800">Staff Approaching Capacity Limits</h4>
            </div>
            <p className="text-xs text-slate-500 mb-3">Staff members with the highest scheduled workloads.</p>
            <div className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="font-medium">Benjamin...</span>
                <div className="flex items-center gap-2">
                  <div className="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div className="h-full bg-red-500 rounded-full" style={{ width: '100%' }} />
                  </div>
                  <span className="text-xs text-red-600 font-medium">110%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="font-medium">Olivia Chen</span>
                <div className="flex items-center gap-2">
                  <div className="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div className="h-full bg-amber-500 rounded-full" style={{ width: '95%' }} />
                  </div>
                  <span className="text-xs text-amber-600 font-medium">95%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="font-medium">Samuel Ro...</span>
                <div className="flex items-center gap-2">
                  <div className="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div className="h-full bg-amber-500 rounded-full" style={{ width: '92%' }} />
                  </div>
                  <span className="text-xs text-amber-600 font-medium">92%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="font-medium">Isabella Wri...</span>
                <div className="flex items-center gap-2">
                  <div className="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div className="h-full bg-emerald-500 rounded-full" style={{ width: '85%' }} />
                  </div>
                  <span className="text-xs text-emerald-600 font-medium">85%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Metrics Summary - Stub */}
      <div>
        <h3 className="text-base font-bold text-slate-800 mb-4">Metrics Summary</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Time-to-First-Service</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">16.2h</div>
            <div className="text-xs text-emerald-600 font-medium">Band A ✓</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Unscheduled Care</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">137h</div>
            <div className="text-xs text-slate-500">10 patients</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Missed Care Rate</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">0.72%</div>
            <div className="text-xs text-amber-600 font-medium">Band C !</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-4">
            <div className="text-xs text-slate-500">Net Capacity</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">177.3h</div>
            <div className="text-xs text-emerald-600 font-medium">GREEN ✓</div>
          </div>
        </div>
      </div>

      {/* Placeholder message */}
      <div className="bg-slate-50 rounded-lg border border-dashed border-slate-300 p-6 text-center">
        <div className="text-slate-400 text-sm">
          <strong>Phase 2:</strong> This tab will be fully implemented with live data from the AutoAssignEngine and metrics services.
        </div>
      </div>
    </div>
  );
};

export default AiOverviewTab;

