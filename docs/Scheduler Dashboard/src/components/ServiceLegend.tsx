import React from 'react';
import type { ServiceType } from '../types';

interface ServiceLegendProps {
  serviceTypes: ServiceType[];
}

export function ServiceLegend({ serviceTypes }: ServiceLegendProps) {
  // Group by category
  const categories = serviceTypes.reduce((acc, st) => {
    if (!acc[st.category]) {
      acc[st.category] = [];
    }
    acc[st.category].push(st);
    return acc;
  }, {} as Record<string, ServiceType[]>);

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4">
      <h3 className="text-sm mb-3">Service Type Legend</h3>
      <div className="grid grid-cols-2 gap-4">
        {Object.entries(categories).map(([category, types]) => (
          <div key={category} className="space-y-2">
            <div className="text-xs text-gray-600 uppercase tracking-wide">
              {category}
            </div>
            <div className="space-y-1">
              {types.map(type => (
                <div key={type.id} className="flex items-center gap-2">
                  <div
                    className="w-4 h-4 rounded"
                    style={{ backgroundColor: type.color }}
                  ></div>
                  <span className="text-xs">{type.displayName}</span>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
