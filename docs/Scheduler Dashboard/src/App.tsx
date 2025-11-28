import React, { useState } from 'react';
import { SchedulingDashboard } from './components/SchedulingDashboard';
import { AssignCareServiceModal } from './components/AssignCareServiceModal';
import { EditAssignmentModal } from './components/EditAssignmentModal';
import { NavigationDemo } from './components/NavigationDemo';
import { mockStaffData, mockUnscheduledCare, mockAssignments, mockServiceTypes } from './data/mockData';
import type { StaffMember, UnscheduledCareItem, Assignment, ServiceType } from './types';

export default function App() {
  const [selectedView, setSelectedView] = useState<'week' | 'month'>('week');
  const [selectedStaffId, setSelectedStaffId] = useState<string | null>(null);
  const [selectedPatientId, setSelectedPatientId] = useState<string | null>(null);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [assignModalContext, setAssignModalContext] = useState<{
    staffId?: string;
    patientId?: string;
    serviceTypeId?: string;
    date?: string;
    time?: string;
  } | null>(null);
  const [selectedAssignment, setSelectedAssignment] = useState<Assignment | null>(null);
  const [assignments, setAssignments] = useState<Assignment[]>(mockAssignments);
  const [unscheduledCare, setUnscheduledCare] = useState<UnscheduledCareItem[]>(mockUnscheduledCare);

  // Parse URL params for deep linking
  React.useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const staffId = params.get('staff_id');
    const patientId = params.get('patient_id');
    
    if (staffId) {
      setSelectedStaffId(staffId);
    }
    if (patientId) {
      setSelectedPatientId(patientId);
    }
  }, []);

  const handleAssignFromUnscheduled = (item: UnscheduledCareItem, serviceTypeId: string) => {
    setAssignModalContext({
      patientId: item.patientId,
      serviceTypeId: serviceTypeId,
    });
    setShowAssignModal(true);
  };

  const handleAssignFromGrid = (staffId: string, date: string, time: string) => {
    setAssignModalContext({
      staffId: staffId,
      date: date,
      time: time,
    });
    setShowAssignModal(true);
  };

  const handleEditAssignment = (assignment: Assignment) => {
    setSelectedAssignment(assignment);
    setShowEditModal(true);
  };

  const handleAssignmentCreated = (newAssignment: Assignment) => {
    setAssignments([...assignments, newAssignment]);
    
    // Update unscheduled care counts
    setUnscheduledCare(prevCare => 
      prevCare.map(item => {
        if (item.patientId === newAssignment.patientId) {
          return {
            ...item,
            services: item.services.map(service => {
              if (service.serviceTypeId === newAssignment.serviceTypeId) {
                return {
                  ...service,
                  scheduled: service.scheduled + (newAssignment.durationMinutes / 60),
                };
              }
              return service;
            }),
          };
        }
        return item;
      })
    );
    
    setShowAssignModal(false);
    setAssignModalContext(null);
  };

  const handleAssignmentUpdated = (updatedAssignment: Assignment) => {
    setAssignments(prevAssignments =>
      prevAssignments.map(a => a.id === updatedAssignment.id ? updatedAssignment : a)
    );
    setShowEditModal(false);
    setSelectedAssignment(null);
  };

  const handleAssignmentDeleted = (assignmentId: string) => {
    const deletedAssignment = assignments.find(a => a.id === assignmentId);
    
    setAssignments(prevAssignments =>
      prevAssignments.filter(a => a.id !== assignmentId)
    );
    
    // Restore unscheduled care counts
    if (deletedAssignment) {
      setUnscheduledCare(prevCare => 
        prevCare.map(item => {
          if (item.patientId === deletedAssignment.patientId) {
            return {
              ...item,
              services: item.services.map(service => {
                if (service.serviceTypeId === deletedAssignment.serviceTypeId) {
                  return {
                    ...service,
                    scheduled: Math.max(0, service.scheduled - (deletedAssignment.durationMinutes / 60)),
                  };
                }
                return service;
              }),
            };
          }
          return item;
        })
      );
    }
    
    setShowEditModal(false);
    setSelectedAssignment(null);
  };

  return (
    <div className="flex flex-col min-h-screen bg-gray-50">
      {/* Navigation Demo - Remove in production */}
      <div className="p-6 bg-gray-50">
        <NavigationDemo />
      </div>
      
      <div className="flex-1 flex flex-col">
        <SchedulingDashboard
          view={selectedView}
          onViewChange={setSelectedView}
          staffData={mockStaffData}
          unscheduledCare={unscheduledCare}
          assignments={assignments}
          selectedStaffId={selectedStaffId}
          selectedPatientId={selectedPatientId}
          onClearStaffFilter={() => setSelectedStaffId(null)}
          onClearPatientFilter={() => setSelectedPatientId(null)}
          onAssignFromUnscheduled={handleAssignFromUnscheduled}
          onAssignFromGrid={handleAssignFromGrid}
          onEditAssignment={handleEditAssignment}
        />
      </div>
      
      {showAssignModal && (
        <AssignCareServiceModal
          isOpen={showAssignModal}
          onClose={() => {
            setShowAssignModal(false);
            setAssignModalContext(null);
          }}
          context={assignModalContext}
          staffData={mockStaffData}
          serviceTypes={mockServiceTypes}
          onAssignmentCreated={handleAssignmentCreated}
        />
      )}
      
      {showEditModal && selectedAssignment && (
        <EditAssignmentModal
          isOpen={showEditModal}
          onClose={() => {
            setShowEditModal(false);
            setSelectedAssignment(null);
          }}
          assignment={selectedAssignment}
          staffData={mockStaffData}
          serviceTypes={mockServiceTypes}
          onUpdate={handleAssignmentUpdated}
          onDelete={handleAssignmentDeleted}
        />
      )}
    </div>
  );
}
