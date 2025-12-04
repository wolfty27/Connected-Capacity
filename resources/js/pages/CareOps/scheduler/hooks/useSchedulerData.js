import { useState, useEffect, useCallback } from 'react';
import api from '../../../../services/api';
import { useSchedulerContext } from '../SchedulerContext';

/**
 * useSchedulerData
 * 
 * Fetches and manages core scheduler data:
 * - Grid data (staff + assignments)
 * - Unscheduled care requirements
 * - Metadata (roles, employment types, navigation examples)
 * - Staff and patient lists for filters
 * 
 * Data is refetched when week or filters change.
 */
export function useSchedulerData() {
  const {
    isSspoMode,
    weekRange,
    filters,
  } = useSchedulerContext();

  // ============================================
  // Data State
  // ============================================
  const [loading, setLoading] = useState(true);
  const [gridData, setGridData] = useState({ staff: [], assignments: [], week: {} });
  const [requirements, setRequirements] = useState({ data: [], summary: {} });
  const [navExamples, setNavExamples] = useState({ staff: null, patient: null });
  const [roles, setRoles] = useState([]);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [allStaff, setAllStaff] = useState([]);
  const [allPatients, setAllPatients] = useState([]);

  // ============================================
  // Fetch Functions
  // ============================================
  
  const fetchGridData = useCallback(async (staffIdParam, patientIdParam) => {
    try {
      const params = new URLSearchParams({
        start_date: weekRange.start,
        end_date: weekRange.end,
        ...(staffIdParam && { staff_id: staffIdParam }),
        ...(patientIdParam && { patient_id: patientIdParam }),
        ...(filters.roleFilter && { 'role_codes[]': filters.roleFilter }),
        ...(filters.empTypeFilter && { 'employment_type_codes[]': filters.empTypeFilter }),
      });
      const response = await api.get(`/v2/scheduling/grid?${params}`);
      setGridData(response.data.data);
      return response.data.data;
    } catch (error) {
      console.error('Failed to fetch grid data:', error);
      return null;
    }
  }, [weekRange.start, weekRange.end, filters.roleFilter, filters.empTypeFilter]);

  const fetchRequirements = useCallback(async (patientIdParam) => {
    try {
      const params = new URLSearchParams({
        start_date: weekRange.start,
        end_date: weekRange.end,
        ...(patientIdParam && { patient_id: patientIdParam }),
        // Only SSPO mode filters by provider_type; SPO sees everything
        ...(isSspoMode && { provider_type: 'sspo' }),
      });
      const response = await api.get(`/v2/scheduling/requirements?${params}`);
      setRequirements(response.data);
      return response.data;
    } catch (error) {
      console.error('Failed to fetch requirements:', error);
      return null;
    }
  }, [weekRange.start, weekRange.end, isSspoMode]);

  const fetchMetadata = useCallback(async (staffIdParam, patientIdParam) => {
    try {
      const params = new URLSearchParams();
      if (staffIdParam) params.set('current_staff_id', staffIdParam);
      if (patientIdParam) params.set('current_patient_id', patientIdParam);

      const [rolesRes, empTypesRes, navRes, staffRes, patientsRes] = await Promise.all([
        api.get('/v2/workforce/metadata/roles'),
        api.get('/v2/workforce/metadata/employment-types'),
        api.get(`/v2/scheduling/navigation-examples?${params}`),
        api.get('/v2/workforce/staff?status=active&limit=100'),
        api.get('/patients?status=Active&limit=100'),
      ]);
      setRoles(rolesRes.data.data || []);
      setEmploymentTypes(empTypesRes.data.data || []);
      setNavExamples(navRes.data.data || {});
      setAllStaff(staffRes.data.data || []);
      setAllPatients(patientsRes.data.data || []);
    } catch (error) {
      console.error('Failed to fetch metadata:', error);
    }
  }, []);

  // ============================================
  // Load All Data
  // ============================================
  const loadAllData = useCallback(async (staffIdParam, patientIdParam) => {
    setLoading(true);
    await Promise.all([
      fetchGridData(staffIdParam, patientIdParam),
      fetchRequirements(patientIdParam),
      fetchMetadata(staffIdParam, patientIdParam),
    ]);
    setLoading(false);
  }, [fetchGridData, fetchRequirements, fetchMetadata]);

  // ============================================
  // Refresh Functions (for after mutations)
  // ============================================
  const refreshGridData = useCallback(async (staffIdParam, patientIdParam) => {
    await fetchGridData(staffIdParam, patientIdParam);
  }, [fetchGridData]);

  const refreshRequirements = useCallback(async (patientIdParam) => {
    await fetchRequirements(patientIdParam);
  }, [fetchRequirements]);

  const refreshAll = useCallback(async (staffIdParam, patientIdParam) => {
    await Promise.all([
      fetchGridData(staffIdParam, patientIdParam),
      fetchRequirements(patientIdParam),
    ]);
  }, [fetchGridData, fetchRequirements]);

  // ============================================
  // Helper Functions
  // ============================================
  
  /**
   * Get assignments for a specific staff member on a specific date
   */
  const getAssignmentsForCell = useCallback((staffId, dateString) => {
    return gridData.assignments.filter(
      a => a.staff_id === staffId && a.date === dateString && a.status !== 'cancelled'
    );
  }, [gridData.assignments]);

  /**
   * Check if staff is available on a specific day of week
   */
  const isStaffAvailable = useCallback((staff, dayOfWeek) => {
    return staff.availability?.some(a => a.day_of_week === dayOfWeek);
  }, []);

  /**
   * Get category color for service assignment chips
   */
  const getCategoryColor = useCallback((category) => {
    const colors = {
      nursing: '#DBEAFE',
      psw: '#D1FAE5',
      personal_support: '#D1FAE5',
      homemaking: '#FEF3C7',
      behaviour: '#FEE2E2',
      behavioral: '#FEE2E2',
      rehab: '#E9D5FF',
      therapy: '#E9D5FF',
    };
    return colors[category?.toLowerCase()] || '#F3F4F6';
  }, []);

  /**
   * Format date for display
   */
  const formatDate = useCallback((date) => {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${days[date.getDay()]} ${months[date.getMonth()]} ${date.getDate()}`;
  }, []);

  return {
    // Loading state
    loading,
    setLoading,
    
    // Data
    gridData,
    requirements,
    navExamples,
    roles,
    employmentTypes,
    allStaff,
    allPatients,
    
    // Fetch functions
    loadAllData,
    refreshGridData,
    refreshRequirements,
    refreshAll,
    
    // Helpers
    getAssignmentsForCell,
    isStaffAvailable,
    getCategoryColor,
    formatDate,
  };
}

export default useSchedulerData;

