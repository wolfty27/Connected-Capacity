export interface ServiceType {
  id: string;
  name: string;
  displayName: string;
  category: 'nursing' | 'psw' | 'homemaking' | 'behaviour' | 'rehab' | 'other';
  color: string;
  unitType: 'hours' | 'visits';
}

export interface StaffRole {
  id: string;
  name: string;
  displayName: string;
  category: string;
}

export interface EmploymentType {
  id: string;
  name: string;
  displayName: string;
}

export interface StaffMember {
  id: string;
  name: string;
  role: StaffRole;
  employmentType: EmploymentType;
  organization: 'SPO' | 'SSPO';
  weeklyCapacityHours: number;
  status: 'active' | 'on_leave';
  availability: AvailabilityBlock[];
}

export interface AvailabilityBlock {
  dayOfWeek: number; // 0 = Sunday, 6 = Saturday
  startTime: string; // HH:mm format
  endTime: string;
  isAvailable: boolean;
}

export interface UnscheduledService {
  serviceTypeId: string;
  serviceTypeName: string;
  category: string;
  required: number; // hours or visits
  scheduled: number;
  unitType: 'hours' | 'visits';
  color: string;
}

export interface UnscheduledCareItem {
  patientId: string;
  patientName: string;
  rugCategory: string;
  riskFlags: string[];
  services: UnscheduledService[];
}

export interface Assignment {
  id: string;
  staffId: string;
  patientId: string;
  patientName: string;
  serviceTypeId: string;
  serviceTypeName: string;
  category: string;
  color: string;
  date: string; // YYYY-MM-DD
  startTime: string; // HH:mm
  endTime: string;
  durationMinutes: number;
  status: 'scheduled' | 'completed' | 'cancelled';
  conflicts?: string[];
}

export interface FilterState {
  organization: 'all' | 'SPO' | 'SSPO';
  roles: string[];
  employmentTypes: string[];
  status: string[];
}
