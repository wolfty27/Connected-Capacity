import React, { useState } from 'react';
import { X, Trash2, AlertCircle } from 'lucide-react';
import type { Assignment, StaffMember, ServiceType } from '../types';

interface EditAssignmentModalProps {
  isOpen: boolean;
  onClose: () => void;
  assignment: Assignment;
  staffData: StaffMember[];
  serviceTypes: ServiceType[];
  onUpdate: (assignment: Assignment) => void;
  onDelete: (assignmentId: string) => void;
}

export function EditAssignmentModal({
  isOpen,
  onClose,
  assignment,
  staffData,
  serviceTypes,
  onUpdate,
  onDelete,
}: EditAssignmentModalProps) {
  const [selectedStaffId, setSelectedStaffId] = useState(assignment.staffId);
  const [selectedDate, setSelectedDate] = useState(assignment.date);
  const [selectedTime, setSelectedTime] = useState(assignment.startTime);
  const [duration, setDuration] = useState(assignment.durationMinutes);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  if (!isOpen) return null;

  const calculateEndTime = (startTime: string, durationMinutes: number) => {
    const [hours, minutes] = startTime.split(':').map(Number);
    const totalMinutes = hours * 60 + minutes + durationMinutes;
    const endHours = Math.floor(totalMinutes / 60) % 24;
    const endMinutes = totalMinutes % 60;
    return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    const staff = staffData.find(s => s.id === selectedStaffId);
    if (!staff) return;

    const updatedAssignment: Assignment = {
      ...assignment,
      staffId: selectedStaffId,
      date: selectedDate,
      startTime: selectedTime,
      endTime: calculateEndTime(selectedTime, duration),
      durationMinutes: duration,
    };

    onUpdate(updatedAssignment);
  };

  const handleDelete = () => {
    onDelete(assignment.id);
  };

  const currentStaff = staffData.find(s => s.id === assignment.staffId);
  const serviceType = serviceTypes.find(st => st.id === assignment.serviceTypeId);

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <div>
            <h2 className="text-lg">Edit Assignment</h2>
            <p className="text-sm text-gray-600 mt-1">
              {assignment.patientName} • {assignment.serviceTypeName}
            </p>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <form onSubmit={handleSubmit} className="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
          {/* Assignment Info */}
          <div className="mb-6 p-4 bg-gray-50 rounded-lg">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <div className="text-gray-600 mb-1">Service Type</div>
                <div className="flex items-center gap-2">
                  <div
                    className="w-3 h-3 rounded"
                    style={{ backgroundColor: assignment.color }}
                  ></div>
                  <span>{assignment.serviceTypeName}</span>
                </div>
              </div>
              <div>
                <div className="text-gray-600 mb-1">Category</div>
                <div className="capitalize">{assignment.category}</div>
              </div>
            </div>
          </div>

          {/* Conflicts Warning */}
          {assignment.conflicts && assignment.conflicts.length > 0 && (
            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
              <div className="flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div>
                  <div className="text-sm text-red-900">Schedule Conflict</div>
                  <div className="text-xs text-red-700 mt-1">
                    This assignment has scheduling conflicts that need to be resolved.
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Reassign to Different Staff */}
          <div className="mb-6">
            <label className="block text-sm mb-2">
              Assigned Staff
            </label>
            <select
              value={selectedStaffId}
              onChange={(e) => setSelectedStaffId(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              {staffData
                .filter(s => {
                  // Filter to eligible staff based on service type
                  if (!serviceType) return true;
                  if (serviceType.category === 'nursing' && s.role.category === 'nursing') return true;
                  if (serviceType.category === 'psw' && s.role.category === 'psw') return true;
                  if (serviceType.category === 'rehab' && s.role.category === 'rehab') return true;
                  if (serviceType.category === 'behaviour' && s.role.category === 'behaviour') return true;
                  return false;
                })
                .map((staff) => (
                  <option key={staff.id} value={staff.id}>
                    {staff.name} - {staff.role.displayName} ({staff.organization})
                  </option>
                ))}
            </select>
          </div>

          {/* Date and Time */}
          <div className="grid grid-cols-2 gap-4 mb-6">
            <div>
              <label className="block text-sm mb-2">
                Date
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
                Start Time
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
            <div className="text-xs text-gray-600 mt-2">
              End time: {calculateEndTime(selectedTime, duration)}
            </div>
          </div>

          {/* Delete Section */}
          <div className="mb-6 pt-6 border-t border-gray-200">
            <button
              type="button"
              onClick={() => setShowDeleteConfirm(!showDeleteConfirm)}
              className="flex items-center gap-2 text-red-600 hover:text-red-700 text-sm"
            >
              <Trash2 className="w-4 h-4" />
              Delete Assignment
            </button>
            
            {showDeleteConfirm && (
              <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p className="text-sm text-red-900 mb-3">
                  Are you sure you want to delete this assignment? This action cannot be undone.
                </p>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={handleDelete}
                    className="px-3 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                  >
                    Yes, Delete
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowDeleteConfirm(false)}
                    className="px-3 py-1.5 bg-white text-gray-700 text-sm rounded border border-gray-300 hover:bg-gray-50"
                  >
                    Cancel
                  </button>
                </div>
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
              Update Assignment
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
