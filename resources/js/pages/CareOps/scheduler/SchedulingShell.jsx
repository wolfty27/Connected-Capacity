import React from 'react';
import Spinner from '../../../components/UI/Spinner';
import Button from '../../../components/UI/Button';
import { SchedulerProvider, useSchedulerContext, VIEW_MODES } from './SchedulerContext';
import AiOverviewTab from './tabs/AiOverviewTab';
import ScheduleTab from './tabs/ScheduleTab';
import ReviewTab from './tabs/ReviewTab';
import ConflictsTab from './tabs/ConflictsTab';

/**
 * SchedulingShell
 * 
 * Main wrapper component for the Scheduler 2.0 AI-first control center.
 * Provides tabbed navigation between internal views:
 * - AI Overview: Monday morning view with AI insights and quick actions
 * - Schedule: Primary working surface with calendar and unscheduled care
 * - Review: Batch proposals and scenario review
 * - Conflicts: Aggregated conflicts and no-match items
 * 
 * @see docs/CC21 Scheduler 2.0 prelim – Design & Implementation Spec.txt
 */
const SchedulingShell = ({ isSspoMode = false }) => {
  return (
    <SchedulerProvider isSspoMode={isSspoMode}>
      <SchedulerShellContent />
    </SchedulerProvider>
  );
};

/**
 * SchedulerShellContent
 * 
 * Inner component that consumes the scheduler context.
 */
const SchedulerShellContent = () => {
  const {
    isSspoMode,
    viewMode,
    setViewMode,
    weekRange,
    goToPreviousWeek,
    goToNextWeek,
    goToCurrentWeek,
    weekOffset,
    ui,
  } = useSchedulerContext();

  const tabs = [
    { id: VIEW_MODES.AI_OVERVIEW, label: 'AI Overview', icon: '🧠' },
    { id: VIEW_MODES.SCHEDULE, label: 'Schedule', icon: '📅' },
    { id: VIEW_MODES.REVIEW, label: 'Review', icon: '📋' },
    { id: VIEW_MODES.CONFLICTS, label: 'Conflicts', icon: '⚠️' },
  ];

  return (
    <div className="min-h-screen bg-slate-50 py-12">
      {/* Header */}
      <div className={`border-b px-6 py-4 ${isSspoMode ? 'bg-purple-50 border-purple-200' : 'bg-white border-slate-200'}`}>
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            {/* Title */}
            <div>
              <h1 className={`text-xl font-bold ${isSspoMode ? 'text-purple-900' : 'text-slate-900'}`}>
                {isSspoMode ? 'SSPO Scheduler' : 'Scheduler'}
              </h1>
              <p className={`text-sm ${isSspoMode ? 'text-purple-600' : 'text-slate-500'}`}>
                {isSspoMode
                  ? 'Nursing, Allied Health & Specialized Services'
                  : 'AI-Assisted Scheduling Control Center'}
              </p>
            </div>

            {/* Week Navigation */}
            <div className="flex items-center gap-2">
              <Button
                variant="secondary"
                size="sm"
                onClick={goToPreviousWeek}
              >
                &larr; Prev
              </Button>
              <span className="text-sm font-medium px-3 whitespace-nowrap">
                {weekRange.startDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} - {weekRange.endDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
              </span>
              <Button
                variant="secondary"
                size="sm"
                onClick={goToNextWeek}
              >
                Next &rarr;
              </Button>
              {weekOffset !== 0 && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToCurrentWeek}
                >
                  Today
                </Button>
              )}
            </div>
          </div>

          {/* Tab Navigation */}
          <div className="mt-4 flex gap-1 border-b border-transparent -mb-px">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setViewMode(tab.id)}
                className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg border border-b-0 transition-colors ${
                  viewMode === tab.id
                    ? 'bg-white border-slate-200 text-slate-900 -mb-px'
                    : 'bg-transparent border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100'
                }`}
              >
                <span>{tab.icon}</span>
                <span>{tab.label}</span>
                {tab.id === VIEW_MODES.CONFLICTS && (
                  <span className="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">
                    4
                  </span>
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-6 py-6">
        {viewMode === VIEW_MODES.AI_OVERVIEW && <AiOverviewTab />}
        {viewMode === VIEW_MODES.SCHEDULE && <ScheduleTab />}
        {viewMode === VIEW_MODES.REVIEW && <ReviewTab />}
        {viewMode === VIEW_MODES.CONFLICTS && <ConflictsTab />}
      </div>
    </div>
  );
};

export default SchedulingShell;

