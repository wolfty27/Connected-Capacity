/**
 * Scheduler 2.0 Module Exports
 * 
 * AI-First Scheduling Control Center
 * @see docs/CC21 Scheduler 2.0 prelim – Design & Implementation Spec.txt
 */

export { default as SchedulingShell } from './SchedulingShell';
export { SchedulerProvider, useSchedulerContext, VIEW_MODES, SCHEDULE_SUB_MODES } from './SchedulerContext';
export { useSchedulerData } from './hooks/useSchedulerData';
export { useAiOverviewData } from './hooks/useAiOverviewData';

// Tab exports for testing/standalone use
export { default as AiOverviewTab } from './tabs/AiOverviewTab';
export { default as ScheduleTab } from './tabs/ScheduleTab';
export { default as ReviewTab } from './tabs/ReviewTab';
export { default as ConflictsTab } from './tabs/ConflictsTab';

