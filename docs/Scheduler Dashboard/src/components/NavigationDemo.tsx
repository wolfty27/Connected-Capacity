import React from 'react';
import { ExternalLink, User, Calendar, AlertCircle } from 'lucide-react';

export function NavigationDemo() {
  const handleNavigate = (url: string) => {
    window.history.pushState({}, '', url);
    window.location.reload();
  };

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
      <h3 className="text-sm mb-4 flex items-center gap-2">
        <ExternalLink className="w-4 h-4" />
        Navigation Examples
      </h3>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Staff-centric */}
        <div className="bg-white rounded-lg p-4 border border-blue-100">
          <div className="flex items-center gap-2 mb-3">
            <User className="w-4 h-4 text-blue-600" />
            <span className="text-sm">Staff-Centric View</span>
          </div>
          <p className="text-xs text-gray-600 mb-3">
            Filter to a specific staff member's schedule
          </p>
          <button
            onClick={() => handleNavigate('?staff_id=staff-2')}
            className="text-xs text-blue-600 hover:text-blue-700 underline"
          >
            View Sophia Rodriguez's schedule
          </button>
        </div>

        {/* Patient-centric */}
        <div className="bg-white rounded-lg p-4 border border-blue-100">
          <div className="flex items-center gap-2 mb-3">
            <Calendar className="w-4 h-4 text-blue-600" />
            <span className="text-sm">Patient-Centric View</span>
          </div>
          <p className="text-xs text-gray-600 mb-3">
            Focus on a patient's care requirements
          </p>
          <button
            onClick={() => handleNavigate('?patient_id=patient-1')}
            className="text-xs text-blue-600 hover:text-blue-700 underline"
          >
            View Johnathan Smith's care
          </button>
        </div>

        {/* Full view */}
        <div className="bg-white rounded-lg p-4 border border-blue-100">
          <div className="flex items-center gap-2 mb-3">
            <AlertCircle className="w-4 h-4 text-blue-600" />
            <span className="text-sm">Full Dashboard</span>
          </div>
          <p className="text-xs text-gray-600 mb-3">
            Clear all filters to see all staff
          </p>
          <button
            onClick={() => handleNavigate('/')}
            className="text-xs text-blue-600 hover:text-blue-700 underline"
          >
            View all schedules
          </button>
        </div>
      </div>

      <div className="mt-4 pt-4 border-t border-blue-200">
        <p className="text-xs text-gray-600">
          💡 <strong>Tip:</strong> In production, these links would come from Staff Directory ("Schedule" button), 
          Patient Care Plans ("View Scheduled Services"), and Command Center metrics.
        </p>
      </div>
    </div>
  );
}
