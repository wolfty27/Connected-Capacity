import React from 'react';
import { AlertCircle, AlertTriangle } from 'lucide-react';
import type { UnscheduledCareItem } from '../types';

interface UnscheduledPanelProps {
  items: UnscheduledCareItem[];
  onAssign: (item: UnscheduledCareItem, serviceTypeId: string) => void;
}

export function UnscheduledPanel({ items, onAssign }: UnscheduledPanelProps) {
  return (
    <div className="w-80 border-r border-gray-200 bg-gray-50 overflow-y-auto">
      <div className="sticky top-0 bg-gray-50 border-b border-gray-200 px-4 py-3 z-10">
        <h2 className="text-sm">Unscheduled Care</h2>
        <p className="text-xs text-gray-500 mt-0.5">first_page</p>
      </div>

      <div className="p-4 space-y-4">
        {items.map((item) => {
          const hasUnscheduled = item.services.some(
            service => service.required - service.scheduled > 0
          );

          if (!hasUnscheduled) return null;

          return (
            <div
              key={item.patientId}
              className="bg-white rounded-lg border border-gray-200 p-4"
            >
              {/* Patient Header */}
              <div className="mb-3">
                <div className="flex items-start justify-between mb-1">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-sm">{item.patientName}</span>
                      {item.riskFlags.includes('warning') && (
                        <span className="text-orange-500 text-xs">warning</span>
                      )}
                      {item.riskFlags.includes('dangerous') && (
                        <span className="text-red-500 text-xs">dangerous</span>
                      )}
                    </div>
                    <div className="text-xs text-gray-600">
                      RUG: {item.rugCategory}
                    </div>
                  </div>
                </div>
              </div>

              {/* Services */}
              <div className="space-y-2">
                {item.services.map((service) => {
                  const remaining = service.required - service.scheduled;
                  
                  if (remaining <= 0) return null;

                  return (
                    <div
                      key={service.serviceTypeId}
                      className="border-t border-gray-100 pt-2"
                    >
                      <div className="flex items-start justify-between mb-1">
                        <div className="flex-1">
                          <div className="text-sm mb-0.5">
                            {service.serviceTypeName}
                          </div>
                          <div className="text-xs text-gray-600">
                            {service.scheduled} of {service.required} {service.unitType} scheduled
                          </div>
                        </div>
                        <button
                          onClick={() => onAssign(item, service.serviceTypeId)}
                          className="text-xs text-blue-600 hover:text-blue-700 px-2 py-1 hover:bg-blue-50 rounded"
                        >
                          Assign
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          );
        })}

        {items.filter(item => 
          item.services.some(service => service.required - service.scheduled > 0)
        ).length === 0 && (
          <div className="text-center py-8 text-gray-500 text-sm">
            <div className="mb-2">✓</div>
            <div>All required care scheduled</div>
          </div>
        )}
      </div>
    </div>
  );
}
