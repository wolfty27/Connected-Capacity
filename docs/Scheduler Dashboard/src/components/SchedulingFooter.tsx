import React from 'react';
import { Info } from 'lucide-react';

export function SchedulingFooter() {
  return (
    <div className="bg-white border-t border-gray-200 px-6 py-3">
      <div className="flex items-center justify-between text-xs text-gray-600">
        <div className="flex items-center gap-6">
          <div className="flex items-center gap-2">
            <Info className="w-4 h-4" />
            <span>Click empty cells to create assignments • Click blocks to edit</span>
          </div>
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-1">
              <div className="w-3 h-3 bg-green-500 rounded"></div>
              <span>{'<'}75% capacity</span>
            </div>
            <div className="flex items-center gap-1">
              <div className="w-3 h-3 bg-yellow-500 rounded"></div>
              <span>75-90% capacity</span>
            </div>
            <div className="flex items-center gap-1">
              <div className="w-3 h-3 bg-red-500 rounded"></div>
              <span>{'>'}90% capacity</span>
            </div>
          </div>
        </div>
        <div className="text-gray-500">
          Metadata-driven scheduling engine • CC21 v2.1
        </div>
      </div>
    </div>
  );
}
