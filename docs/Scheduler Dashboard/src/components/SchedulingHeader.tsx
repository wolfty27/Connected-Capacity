import React, { useState } from 'react';
import { ChevronLeft, ChevronRight, Calendar, X, Plus } from 'lucide-react';
import type { FilterState } from '../types';

interface SchedulingHeaderProps {
  view: 'week' | 'month';
  onViewChange: (view: 'week' | 'month') => void;
  filters: FilterState;
  onFiltersChange: (filters: FilterState) => void;
  weekStartDate: Date;
  onWeekChange: (date: Date) => void;
  selectedStaffId: string | null;
  selectedPatientId: string | null;
  onClearStaffFilter: () => void;
  onClearPatientFilter: () => void;
}

export function SchedulingHeader({
  view,
  onViewChange,
  filters,
  onFiltersChange,
  weekStartDate,
  onWeekChange,
  selectedStaffId,
  selectedPatientId,
  onClearStaffFilter,
  onClearPatientFilter,
}: SchedulingHeaderProps) {
  const [showOrgDropdown, setShowOrgDropdown] = useState(false);
  const [showRoleDropdown, setShowRoleDropdown] = useState(false);
  const [showEmploymentDropdown, setShowEmploymentDropdown] = useState(false);
  const [showStatusDropdown, setShowStatusDropdown] = useState(false);

  const formatDateRange = () => {
    const endDate = new Date(weekStartDate);
    endDate.setDate(weekStartDate.getDate() + 6);
    
    const options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
    return `${weekStartDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}, ${weekStartDate.getFullYear()}`;
  };

  const navigateWeek = (direction: 'prev' | 'next') => {
    const newDate = new Date(weekStartDate);
    newDate.setDate(weekStartDate.getDate() + (direction === 'next' ? 7 : -7));
    onWeekChange(newDate);
  };

  const goToToday = () => {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
    const monday = new Date(today);
    monday.setDate(today.getDate() + diff);
    onWeekChange(monday);
  };

  return (
    <div className="bg-white border-b border-gray-200">
      {/* Top Navigation Bar */}
      <div className="flex items-center justify-between px-6 py-3 border-b border-gray-100">
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2">
            <div className="w-6 h-6 bg-blue-500 rounded"></div>
            <span className="text-lg">Connected Capacity</span>
          </div>
          <nav className="flex gap-6 ml-8">
            <a href="#" className="text-gray-600 hover:text-gray-900">Dashboard</a>
            <a href="#" className="text-gray-900">Schedules</a>
            <a href="#" className="text-gray-600 hover:text-gray-900">Staff</a>
            <a href="#" className="text-gray-600 hover:text-gray-900">Patients</a>
            <a href="#" className="text-gray-600 hover:text-gray-900">Reports</a>
          </nav>
        </div>
        <div className="flex items-center gap-4">
          <button className="text-gray-600 hover:text-gray-900">notifications</button>
          <button className="text-gray-600 hover:text-gray-900">help</button>
          <button className="text-gray-600 hover:text-gray-900">settings</button>
          <div className="w-8 h-8 bg-gray-300 rounded-full"></div>
        </div>
      </div>

      {/* Page Title */}
      <div className="px-6 py-6 border-b border-gray-100">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl mb-1">Staff Scheduling</h1>
            <p className="text-gray-600">Assign and manage care assignments for SPO and SSPO staff.</p>
          </div>
          <button className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <Plus className="w-4 h-4" />
            New Assignment
          </button>
        </div>
      </div>

      {/* Filters and Controls */}
      <div className="px-6 py-4">
        <div className="flex items-center justify-between mb-4">
          {/* Filters */}
          <div className="flex items-center gap-3">
            {/* Organization Filter */}
            <div className="relative">
              <button
                onClick={() => setShowOrgDropdown(!showOrgDropdown)}
                className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2"
              >
                <span className="text-gray-600">Org:</span>
                <span>{filters.organization === 'all' ? 'SPO Midwest' : filters.organization}</span>
                <span className="text-gray-400">expand_more</span>
              </button>
              {showOrgDropdown && (
                <div className="absolute top-full mt-1 left-0 bg-white border border-gray-200 rounded-lg shadow-lg z-10 min-w-[200px]">
                  <button
                    onClick={() => {
                      onFiltersChange({ ...filters, organization: 'all' });
                      setShowOrgDropdown(false);
                    }}
                    className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                  >
                    SPO Midwest
                  </button>
                  <button
                    onClick={() => {
                      onFiltersChange({ ...filters, organization: 'SPO' });
                      setShowOrgDropdown(false);
                    }}
                    className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                  >
                    SPO Only
                  </button>
                  <button
                    onClick={() => {
                      onFiltersChange({ ...filters, organization: 'SSPO' });
                      setShowOrgDropdown(false);
                    }}
                    className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                  >
                    SSPO Only
                  </button>
                </div>
              )}
            </div>

            {/* Role Filter */}
            <div className="relative">
              <button
                onClick={() => setShowRoleDropdown(!showRoleDropdown)}
                className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2"
              >
                <span className="text-gray-600">Role:</span>
                <span>All</span>
                <span className="text-gray-400">expand_more</span>
              </button>
            </div>

            {/* Employment Type Filter */}
            <div className="relative">
              <button
                onClick={() => setShowEmploymentDropdown(!showEmploymentDropdown)}
                className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2"
              >
                <span className="text-gray-600">Employment:</span>
                <span>All</span>
                <span className="text-gray-400">expand_more</span>
              </button>
            </div>

            {/* Status Filter */}
            <div className="relative">
              <button
                onClick={() => setShowStatusDropdown(!showStatusDropdown)}
                className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2"
              >
                <span className="text-gray-600">Status:</span>
                <span>Active</span>
                <span className="text-gray-400">expand_more</span>
              </button>
            </div>

            {/* Active Filter Pills */}
            {selectedStaffId && (
              <div className="flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 text-sm rounded border border-blue-200">
                <span>Filtered to 1 staff</span>
                <button onClick={onClearStaffFilter} className="hover:text-blue-900">
                  <X className="w-3 h-3" />
                </button>
              </div>
            )}
            {selectedPatientId && (
              <div className="flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 text-sm rounded border border-blue-200">
                <span>Filtered to 1 patient</span>
                <button onClick={onClearPatientFilter} className="hover:text-blue-900">
                  <X className="w-3 h-3" />
                </button>
              </div>
            )}
          </div>
        </div>

        {/* Legend */}
        <div className="flex items-center gap-4 text-xs text-gray-600 mb-3">
          <span className="text-gray-500">Service Types:</span>
          <div className="flex items-center gap-1">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: '#DBEAFE' }}></div>
            <span>Rehab</span>
          </div>
          <div className="flex items-center gap-1">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: '#FEE2E2' }}></div>
            <span>Nursing</span>
          </div>
          <div className="flex items-center gap-1">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: '#E0E7FF' }}></div>
            <span>PSW</span>
          </div>
          <div className="flex items-center gap-1">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: '#FCE7F3' }}></div>
            <span>Wound Care</span>
          </div>
        </div>

        {/* View Toggle and Date Range */}
        <div className="flex items-center gap-4">
          {/* Week/Month Toggle */}
          <div className="flex border border-gray-300 rounded-lg overflow-hidden">
            <button
              onClick={() => onViewChange('week')}
              className={`px-4 py-1.5 text-sm ${
                view === 'week'
                  ? 'bg-blue-600 text-white'
                  : 'bg-white text-gray-700 hover:bg-gray-50'
              }`}
            >
              Week
            </button>
            <button
              onClick={() => onViewChange('month')}
              className={`px-4 py-1.5 text-sm border-l border-gray-300 ${
                view === 'month'
                  ? 'bg-blue-600 text-white'
                  : 'bg-white text-gray-700 hover:bg-gray-50'
              }`}
            >
              Month
            </button>
          </div>

          {/* Date Navigation */}
          <div className="flex items-center gap-2">
            <button
              onClick={() => navigateWeek('prev')}
              className="p-1.5 hover:bg-gray-100 rounded"
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
            <button
              onClick={goToToday}
              className="px-3 py-1.5 text-sm hover:bg-gray-100 rounded"
            >
              Today
            </button>
            <button
              onClick={() => navigateWeek('next')}
              className="p-1.5 hover:bg-gray-100 rounded"
            >
              <ChevronRight className="w-5 h-5" />
            </button>
            <div className="flex items-center gap-2 px-3 py-1.5 text-sm">
              <Calendar className="w-4 h-4 text-gray-400" />
              <span>{formatDateRange()}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
