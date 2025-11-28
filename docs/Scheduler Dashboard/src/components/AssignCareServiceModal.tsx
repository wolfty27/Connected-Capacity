import React, { useState, useMemo } from 'react';
import { X, AlertCircle, Clock, User } from 'lucide-react';
import type { StaffMember, ServiceType, Assignment } from '../types';

interface AssignCareServiceModalProps {
  isOpen: boolean;
  onClose: () => void;
  context: {
    staffId?: string;
    patientId?: string;
    serviceTypeId?: string;
    date?: string;
    time?: string;
  } | null;
  staffData: StaffMember[];
  serviceTypes: ServiceType[];
  onAssignmentCreated: (assignment: Assignment) => void;
}

export function AssignCareServiceModal({
  isOpen,
  onClose,
  context,
  staffData,
  serviceTypes,
  onAssignmentCreated,
}: AssignCareServiceModalProps) {
  const [selectedStaffId, setSelectedStaffId] = useState(context?.staffId || '');
  const [selectedServiceTypeId, setSelectedServiceTypeId] = useState(context?.serviceTypeId || '');
  const [selectedDate, setSelectedDate] = useState(context?.date || '');
  const [selectedTime, setSelectedTime] = useState(context?.time || '09:00');
  const [duration, setDuration] = useState(60);

  if (!isOpen) return null;

  const eligibleStaff = useMemo(() => {
    if (!selectedServiceTypeId) return staffData;
    
    const serviceType = serviceTypes.find(st => st.id === selectedServiceTypeId);
    if (!serviceType) return staffData;

    // Filter staff based on role category matching service category
    return staffData.filter(staff => {
      // Simple eligibility: match role category with service category
      if (serviceType.category === 'nursing' && staff.role.category === 'nursing') return true;
      if (serviceType.category === 'psw' && staff.role.category === 'psw') return true;
      if (serviceType.category === 'rehab' && staff.role.category === 'rehab') return true;
      if (serviceType.category === 'behaviour' && staff.role.category === 'behaviour') return true;
      return false;
    });
  }, [selectedServiceTypeId, staffData, serviceTypes]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!selectedStaffId || !selectedServiceTypeId || !selectedDate || !selectedTime) {
      alert('Please fill in all required fields');
      return;
    }

    const staff = staffData.find(s => s.id === selectedStaffId);
    const serviceType = serviceTypes.find(st => st.id === selectedServiceTypeId);

    if (!staff || !serviceType) return;

    const startTime = selectedTime;
    const endTime = calculateEndTime(selectedTime, duration);

    const newAssignment: Assignment = {
      id: `assign-${Date.now()}`,
      staffId: selectedStaffId,
      patientId: context?.patientId || 'patient-new',
      patientName: 'New Patient',
      serviceTypeId: selectedServiceTypeId,
      serviceTypeName: serviceType.displayName,
      category: serviceType.category,
      color: serviceType.color,
      date: selectedDate,
      startTime: startTime,
      endTime: endTime,
      durationMinutes: duration,
      status: 'scheduled',
    };

    onAssignmentCreated(newAssignment);
  };

  const calculateEndTime = (startTime: string, durationMinutes: number) => {
    const [hours, minutes] = startTime.split(':').map(Number);
    const totalMinutes = hours * 60 + minutes + durationMinutes;
    const endHours = Math.floor(totalMinutes / 60) % 24;
    const endMinutes = totalMinutes % 60;
    return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`;
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg">Assign Care Service</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <form onSubmit={handleSubmit} className="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
          {/* Context Information */}
          {context?.patientId && (
            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div>
                  <div className="text-sm">Patient Context</div>
                  <div className="text-xs text-gray-600 mt-1">
                    Assigning service for patient from unscheduled care list
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Service Type Selection */}
          <div className="mb-6">
            <label className="block text-sm mb-2">
              Service Type <span className="text-red-500">*</span>
            </label>
            <select
              value={selectedServiceTypeId}
              onChange={(e) => setSelectedServiceTypeId(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="">Select a service type...</option>
              {serviceTypes.map((st) => (
                <option key={st.id} value={st.id}>
                  {st.displayName} ({st.category})
                </option>
              ))}
            </select>
          </div>

          {/* Eligible Staff */}
          <div className="mb-6">
            <label className="block text-sm mb-2">
              Assign to Staff <span className="text-red-500">*</span>
            </label>
            <div className="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-2">
              {eligibleStaff.length === 0 ? (
                <div className="text-center py-4 text-gray-500 text-sm">
                  No eligible staff for selected service type
                </div>
              ) : (
                eligibleStaff.map((staff) => (
                  <label
                    key={staff.id}
                    className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                      selectedStaffId === staff.id
                        ? 'border-blue-500 bg-blue-50'
                        : 'border-gray-200 hover:bg-gray-50'
                    }`}
                  >
                    <input
                      type="radio"
                      name="staff"
                      value={staff.id}
                      checked={selectedStaffId === staff.id}
                      onChange={(e) => setSelectedStaffId(e.target.value)}
                      className="w-4 h-4 text-blue-600"
                    />
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <User className="w-4 h-4 text-gray-400" />
                        <span className="text-sm">{staff.name}</span>
                      </div>
                      <div className="text-xs text-gray-600 ml-6">
                        {staff.role.displayName} • {staff.employmentType.displayName} • {staff.organization}
                      </div>
                    </div>
                    <div className="text-xs text-gray-500">
                      {staff.weeklyCapacityHours}h/week
                    </div>
                  </label>
                ))
              )}
            </div>
          </div>

          {/* Date and Time */}
          <div className="grid grid-cols-2 gap-4 mb-6">
            <div>
              <label className="block text-sm mb-2">
                Date <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>
            <div>
              <label className="block text-sm mb-2">
                Start Time <span className="text-red-500">*</span>
              </label>
              <input
                type="time"
                value={selectedTime}
                onChange={(e) => setSelectedTime(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>
          </div>

          {/* Duration */}
          <div className="mb-6">
            <label className="block text-sm mb-2">
              Duration (minutes)
            </label>
            <select
              value={duration}
              onChange={(e) => setDuration(Number(e.target.value))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value={30}>30 minutes</option>
              <option value={60}>1 hour</option>
              <option value={90}>1.5 hours</option>
              <option value={120}>2 hours</option>
              <option value={180}>3 hours</option>
            </select>
            {selectedTime && (
              <div className="flex items-center gap-2 mt-2 text-xs text-gray-600">
                <Clock className="w-4 h-4" />
                <span>
                  Assignment will run from {selectedTime} to {calculateEndTime(selectedTime, duration)}
                </span>
              </div>
            )}
          </div>

          {/* Actions */}
          <div className="flex gap-3 justify-end pt-4 border-t border-gray-200">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              Create Assignment
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
