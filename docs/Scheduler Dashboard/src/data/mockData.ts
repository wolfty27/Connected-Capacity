import type { StaffMember, UnscheduledCareItem, Assignment, ServiceType } from '../types';

export const mockServiceTypes: ServiceType[] = [
  {
    id: 'st-psw',
    name: 'psw_care',
    displayName: 'PSW Care',
    category: 'psw',
    color: '#E0E7FF',
    unitType: 'hours',
  },
  {
    id: 'st-nursing',
    name: 'skilled_nursing',
    displayName: 'Skilled Nursing',
    category: 'nursing',
    color: '#FEE2E2',
    unitType: 'visits',
  },
  {
    id: 'st-pt',
    name: 'physical_therapy',
    displayName: 'Physical Therapy',
    category: 'rehab',
    color: '#DBEAFE',
    unitType: 'visits',
  },
  {
    id: 'st-ot',
    name: 'occupational_therapy',
    displayName: 'Occupational Therapy',
    category: 'rehab',
    color: '#DBEAFE',
    unitType: 'visits',
  },
  {
    id: 'st-st',
    name: 'speech_therapy',
    displayName: 'Speech Therapy',
    category: 'rehab',
    color: '#DBEAFE',
    unitType: 'visits',
  },
  {
    id: 'st-wound',
    name: 'wound_care',
    displayName: 'Wound Care',
    category: 'nursing',
    color: '#FCE7F3',
    unitType: 'visits',
  },
  {
    id: 'st-med-mgmt',
    name: 'medication_management',
    displayName: 'Medication Management',
    category: 'nursing',
    color: '#FEF3C7',
    unitType: 'visits',
  },
  {
    id: 'st-behaviour',
    name: 'behavioural_supports',
    displayName: 'Behavioural Supports',
    category: 'behaviour',
    color: '#E0F2FE',
    unitType: 'visits',
  },
];

export const mockStaffData: StaffMember[] = [
  {
    id: 'staff-1',
    name: 'David Lee',
    role: {
      id: 'role-pta',
      name: 'pta',
      displayName: 'PTA',
      category: 'rehab',
    },
    employmentType: {
      id: 'emp-contractor',
      name: 'contractor',
      displayName: 'Contractor',
    },
    organization: 'SSPO',
    weeklyCapacityHours: 40,
    status: 'active',
    availability: [
      { dayOfWeek: 1, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 2, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 3, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 4, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 5, startTime: '08:00', endTime: '16:00', isAvailable: true },
    ],
  },
  {
    id: 'staff-2',
    name: 'Sophia Rodriguez',
    role: {
      id: 'role-ot',
      name: 'occupational_therapist',
      displayName: 'OT',
      category: 'rehab',
    },
    employmentType: {
      id: 'emp-fulltime',
      name: 'full_time',
      displayName: 'Full-time',
    },
    organization: 'SPO',
    weeklyCapacityHours: 40,
    status: 'active',
    availability: [
      { dayOfWeek: 1, startTime: '09:00', endTime: '17:00', isAvailable: true },
      { dayOfWeek: 2, startTime: '09:00', endTime: '17:00', isAvailable: true },
      { dayOfWeek: 3, startTime: '09:00', endTime: '17:00', isAvailable: true },
      { dayOfWeek: 4, startTime: '09:00', endTime: '17:00', isAvailable: true },
      { dayOfWeek: 5, startTime: '09:00', endTime: '17:00', isAvailable: true },
    ],
  },
  {
    id: 'staff-3',
    name: 'Michael Chen',
    role: {
      id: 'role-rn',
      name: 'registered_nurse',
      displayName: 'RN',
      category: 'nursing',
    },
    employmentType: {
      id: 'emp-parttime',
      name: 'part_time',
      displayName: 'Part-time',
    },
    organization: 'SPO',
    weeklyCapacityHours: 24,
    status: 'active',
    availability: [
      { dayOfWeek: 1, startTime: '08:00', endTime: '14:00', isAvailable: true },
      { dayOfWeek: 2, startTime: '08:00', endTime: '14:00', isAvailable: true },
      { dayOfWeek: 3, startTime: '08:00', endTime: '14:00', isAvailable: true },
      { dayOfWeek: 4, startTime: '08:00', endTime: '14:00', isAvailable: true },
    ],
  },
  {
    id: 'staff-4',
    name: 'Jennifer Walsh',
    role: {
      id: 'role-psw',
      name: 'personal_support_worker',
      displayName: 'PSW',
      category: 'psw',
    },
    employmentType: {
      id: 'emp-fulltime',
      name: 'full_time',
      displayName: 'Full-time',
    },
    organization: 'SPO',
    weeklyCapacityHours: 40,
    status: 'active',
    availability: [
      { dayOfWeek: 1, startTime: '07:00', endTime: '15:00', isAvailable: true },
      { dayOfWeek: 2, startTime: '07:00', endTime: '15:00', isAvailable: true },
      { dayOfWeek: 3, startTime: '07:00', endTime: '15:00', isAvailable: true },
      { dayOfWeek: 4, startTime: '07:00', endTime: '15:00', isAvailable: true },
      { dayOfWeek: 5, startTime: '07:00', endTime: '15:00', isAvailable: true },
    ],
  },
  {
    id: 'staff-5',
    name: 'Ahmed Patel',
    role: {
      id: 'role-pt',
      name: 'physical_therapist',
      displayName: 'PT',
      category: 'rehab',
    },
    employmentType: {
      id: 'emp-fulltime',
      name: 'full_time',
      displayName: 'Full-time',
    },
    organization: 'SPO',
    weeklyCapacityHours: 40,
    status: 'active',
    availability: [
      { dayOfWeek: 1, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 2, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 3, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 4, startTime: '08:00', endTime: '16:00', isAvailable: true },
      { dayOfWeek: 5, startTime: '08:00', endTime: '16:00', isAvailable: true },
    ],
  },
];

export const mockUnscheduledCare: UnscheduledCareItem[] = [
  {
    patientId: 'patient-1',
    patientName: 'Johnathan Smith',
    rugCategory: 'Ultra High',
    riskFlags: ['warning', 'dangerous'],
    services: [
      {
        serviceTypeId: 'st-pt',
        serviceTypeName: 'Physical Therapy',
        category: 'rehab',
        required: 2,
        scheduled: 0,
        unitType: 'visits',
        color: '#DBEAFE',
      },
      {
        serviceTypeId: 'st-nursing',
        serviceTypeName: 'Skilled Nursing',
        category: 'nursing',
        required: 2,
        scheduled: 0,
        unitType: 'visits',
        color: '#FEE2E2',
      },
    ],
  },
  {
    patientId: 'patient-2',
    patientName: 'Eleanor Vance',
    rugCategory: 'High',
    riskFlags: [],
    services: [
      {
        serviceTypeId: 'st-ot',
        serviceTypeName: 'Occupational Therapy',
        category: 'rehab',
        required: 3,
        scheduled: 1,
        unitType: 'visits',
        color: '#DBEAFE',
      },
    ],
  },
  {
    patientId: 'patient-3',
    patientName: 'Marcus Holloway',
    rugCategory: 'Medium',
    riskFlags: ['warning'],
    services: [
      {
        serviceTypeId: 'st-st',
        serviceTypeName: 'Speech Therapy',
        category: 'rehab',
        required: 1,
        scheduled: 0,
        unitType: 'visits',
        color: '#DBEAFE',
      },
    ],
  },
];

// Helper to get a date string for a specific day offset
const getDateString = (daysOffset: number): string => {
  const date = new Date();
  const monday = new Date(date);
  const dayOfWeek = date.getDay();
  const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
  monday.setDate(date.getDate() + diff + daysOffset);
  return monday.toISOString().split('T')[0];
};

export const mockAssignments: Assignment[] = [
  // David Lee - PTA (Tuesday)
  {
    id: 'assign-1',
    staffId: 'staff-1',
    patientId: 'patient-4',
    patientName: 'Robert Chen',
    serviceTypeId: 'st-nursing',
    serviceTypeName: 'SN Visit',
    category: 'nursing',
    color: '#FEE2E2',
    date: getDateString(1), // Tuesday
    startTime: '10:30',
    endTime: '11:00',
    durationMinutes: 30,
    status: 'scheduled',
    conflicts: ['error'],
  },
  // Sophia Rodriguez - OT (Wednesday)
  {
    id: 'assign-2',
    staffId: 'staff-2',
    patientId: 'patient-2',
    patientName: 'Eleanor Vance',
    serviceTypeId: 'st-ot',
    serviceTypeName: 'OT Svc',
    category: 'rehab',
    color: '#DBEAFE',
    date: getDateString(2), // Wednesday
    startTime: '15:00',
    endTime: '16:00',
    durationMinutes: 60,
    status: 'scheduled',
  },
  // David Lee - Wound Care (Wednesday)
  {
    id: 'assign-3',
    staffId: 'staff-1',
    patientId: 'patient-5',
    patientName: 'Maria Garcia',
    serviceTypeId: 'st-wound',
    serviceTypeName: 'Wound Care',
    category: 'nursing',
    color: '#FCE7F3',
    date: getDateString(2), // Wednesday
    startTime: '10:15',
    endTime: '10:45',
    durationMinutes: 30,
    status: 'scheduled',
  },
  // Michael Chen - Med Mgmt (Monday)
  {
    id: 'assign-4',
    staffId: 'staff-3',
    patientId: 'patient-6',
    patientName: 'Patricia Wong',
    serviceTypeId: 'st-med-mgmt',
    serviceTypeName: 'Meds Mgmt',
    category: 'nursing',
    color: '#FEF3C7',
    date: getDateString(0), // Monday
    startTime: '08:00',
    endTime: '09:30',
    durationMinutes: 90,
    status: 'scheduled',
  },
  // Michael Chen - PT Eval (Thursday)
  {
    id: 'assign-5',
    staffId: 'staff-3',
    patientId: 'patient-7',
    patientName: 'James Peterson',
    serviceTypeId: 'st-pt',
    serviceTypeName: 'PT Eval',
    category: 'rehab',
    color: '#DBEAFE',
    date: getDateString(3), // Thursday
    startTime: '13:00',
    endTime: '14:00',
    durationMinutes: 60,
    status: 'scheduled',
  },
  // Additional assignments
  {
    id: 'assign-6',
    staffId: 'staff-4',
    patientId: 'patient-8',
    patientName: 'Betty White',
    serviceTypeId: 'st-psw',
    serviceTypeName: 'PSW Care',
    category: 'psw',
    color: '#E0E7FF',
    date: getDateString(0), // Monday
    startTime: '09:00',
    endTime: '11:00',
    durationMinutes: 120,
    status: 'scheduled',
  },
  {
    id: 'assign-7',
    staffId: 'staff-4',
    patientId: 'patient-9',
    patientName: 'George Martin',
    serviceTypeId: 'st-psw',
    serviceTypeName: 'PSW Care',
    category: 'psw',
    color: '#E0E7FF',
    date: getDateString(1), // Tuesday
    startTime: '10:00',
    endTime: '12:00',
    durationMinutes: 120,
    status: 'scheduled',
  },
  {
    id: 'assign-8',
    staffId: 'staff-5',
    patientId: 'patient-10',
    patientName: 'Susan Clark',
    serviceTypeId: 'st-pt',
    serviceTypeName: 'PT Session',
    category: 'rehab',
    color: '#DBEAFE',
    date: getDateString(1), // Tuesday
    startTime: '14:00',
    endTime: '15:00',
    durationMinutes: 60,
    status: 'scheduled',
  },
];
