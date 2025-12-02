import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Spinner from '../../components/UI/Spinner';
import DataTable from '../../components/UI/DataTable';
import { ArrowLeft, Calendar, Clock, MapPin, Award, TrendingUp, AlertTriangle, Lock, Unlock, User, Phone, Mail, Briefcase } from 'lucide-react';

/**
 * StaffProfilePage - Comprehensive staff member profile view
 * 
 * All data comes from the API - no business logic in this component.
 * Labels, colors, and computed values are returned by backend services.
 */
const StaffProfilePage = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    
    const [loading, setLoading] = useState(true);
    const [profile, setProfile] = useState(null);
    const [schedule, setSchedule] = useState(null);
    const [availability, setAvailability] = useState([]);
    const [unavailabilities, setUnavailabilities] = useState([]);
    const [skills, setSkills] = useState([]);
    const [satisfaction, setSatisfaction] = useState(null);
    const [travel, setTravel] = useState(null);
    const [activeTab, setActiveTab] = useState('overview');
    const [error, setError] = useState(null);
    
    // Modals
    const [showStatusModal, setShowStatusModal] = useState(false);
    const [showAvailabilityModal, setShowAvailabilityModal] = useState(false);
    const [showTimeOffModal, setShowTimeOffModal] = useState(false);
    const [showSkillModal, setShowSkillModal] = useState(false);
    
    // Fetch profile data
    const fetchProfile = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/profile`);
            setProfile(response.data.data);
        } catch (err) {
            console.error('Failed to fetch profile:', err);
            setError('Failed to load staff profile');
        }
    }, [id]);
    
    // Fetch schedule data
    const fetchSchedule = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/schedule`);
            setSchedule(response.data.data);
        } catch (err) {
            console.error('Failed to fetch schedule:', err);
        }
    }, [id]);
    
    // Fetch availability
    const fetchAvailability = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/availability`);
            setAvailability(response.data.data || []);
        } catch (err) {
            console.error('Failed to fetch availability:', err);
        }
    }, [id]);
    
    // Fetch unavailabilities
    const fetchUnavailabilities = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/unavailabilities`);
            setUnavailabilities(response.data.data || []);
        } catch (err) {
            console.error('Failed to fetch unavailabilities:', err);
        }
    }, [id]);
    
    // Fetch skills
    const fetchSkills = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/skills`);
            setSkills(response.data.data || []);
        } catch (err) {
            console.error('Failed to fetch skills:', err);
        }
    }, [id]);
    
    // Fetch satisfaction
    const fetchSatisfaction = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/satisfaction`);
            setSatisfaction(response.data.data);
        } catch (err) {
            console.error('Failed to fetch satisfaction:', err);
        }
    }, [id]);
    
    // Fetch travel metrics
    const fetchTravel = useCallback(async () => {
        try {
            const response = await api.get(`/v2/staff/${id}/travel`);
            setTravel(response.data.data);
        } catch (err) {
            console.error('Failed to fetch travel:', err);
        }
    }, [id]);
    
    // Initial load
    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            await fetchProfile();
            setLoading(false);
        };
        loadData();
    }, [fetchProfile]);
    
    // Tab-specific data loading
    useEffect(() => {
        if (activeTab === 'schedule') fetchSchedule();
        if (activeTab === 'availability') fetchAvailability();
        if (activeTab === 'timeoff') fetchUnavailabilities();
        if (activeTab === 'skills') fetchSkills();
        if (activeTab === 'satisfaction') fetchSatisfaction();
        if (activeTab === 'travel') fetchTravel();
    }, [activeTab, fetchSchedule, fetchAvailability, fetchUnavailabilities, fetchSkills, fetchSatisfaction, fetchTravel]);
    
    // Status badge colors (from API)
    const getStatusBadgeClasses = (color) => {
        const colors = {
            green: 'bg-emerald-100 text-emerald-800',
            amber: 'bg-amber-100 text-amber-800',
            red: 'bg-red-100 text-red-800',
            gray: 'bg-slate-100 text-slate-600',
        };
        return colors[color] || colors.gray;
    };
    
    // Handle status change
    const handleStatusChange = async (newStatus, options = {}) => {
        try {
            await api.patch(`/v2/staff/${id}/status`, { status: newStatus, ...options });
            fetchProfile();
            setShowStatusModal(false);
        } catch (err) {
            console.error('Failed to update status:', err);
        }
    };
    
    // Handle scheduling lock toggle
    const handleLockToggle = async () => {
        try {
            if (profile?.is_scheduling_locked) {
                await api.delete(`/v2/staff/${id}/scheduling-lock`);
            } else {
                await api.post(`/v2/staff/${id}/scheduling-lock`, { reason: 'Manual lock' });
            }
            fetchProfile();
        } catch (err) {
            console.error('Failed to toggle lock:', err);
        }
    };
    
    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <Spinner className="w-8 h-8 text-teal-600" />
            </div>
        );
    }
    
    if (error || !profile) {
        return (
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                    <p className="text-red-600">{error || 'Staff member not found'}</p>
                    <Button variant="secondary" onClick={() => navigate('/workforce')} className="mt-4">
                        Back to Workforce
                    </Button>
                </div>
            </div>
        );
    }
    
    const tabs = [
        { id: 'overview', label: 'Overview', icon: User },
        { id: 'schedule', label: 'Schedule', icon: Calendar },
        { id: 'availability', label: 'Availability', icon: Clock },
        { id: 'timeoff', label: 'Time Off', icon: Calendar },
        { id: 'skills', label: 'Skills', icon: Award },
        { id: 'travel', label: 'Travel', icon: MapPin },
    ];
    
    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            {/* Back Button */}
            <button
                onClick={() => navigate('/workforce')}
                className="flex items-center gap-2 text-slate-600 hover:text-slate-800 mb-6"
            >
                <ArrowLeft className="w-5 h-5" />
                <span className="text-sm font-medium">Back to Workforce</span>
            </button>
            
            {/* Profile Header */}
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    {/* Avatar and Name */}
                    <div className="flex items-center gap-4">
                        <div className="h-16 w-16 rounded-full bg-gradient-to-br from-teal-500 to-teal-700 text-white flex items-center justify-center font-bold text-xl shadow-md">
                            {profile.avatar_initials}
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-800">{profile.name}</h1>
                            <div className="flex items-center gap-3 mt-1">
                                {profile.staff_role && (
                                    <span className={`px-2 py-0.5 rounded text-xs font-medium bg-${profile.staff_role.badge_color || 'blue'}-100 text-${profile.staff_role.badge_color || 'blue'}-800`}>
                                        {profile.staff_role.name}
                                    </span>
                                )}
                                {profile.employment_type && (
                                    <span className={`px-2 py-0.5 rounded text-xs font-medium bg-${profile.employment_type.badge_color || 'green'}-100 text-${profile.employment_type.badge_color || 'green'}-800`}>
                                        {profile.employment_type.name}
                                    </span>
                                )}
                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${getStatusBadgeClasses(profile.staff_status_color)}`}>
                                    {profile.staff_status_label}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    {/* Actions */}
                    <div className="flex items-center gap-2">
                        {profile.is_scheduling_locked ? (
                            <Button variant="secondary" onClick={handleLockToggle} className="flex items-center gap-2">
                                <Unlock className="w-4 h-4" /> Unlock Scheduling
                            </Button>
                        ) : (
                            <Button variant="secondary" onClick={handleLockToggle} className="flex items-center gap-2">
                                <Lock className="w-4 h-4" /> Lock Scheduling
                            </Button>
                        )}
                        <Button variant="secondary" onClick={() => setShowStatusModal(true)}>
                            Change Status
                        </Button>
                    </div>
                </div>
                
                {/* Scheduling Lock Warning */}
                {profile.is_scheduling_locked && (
                    <div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-2">
                        <AlertTriangle className="w-5 h-5 text-amber-600" />
                        <span className="text-sm text-amber-800">
                            Scheduling is locked: {profile.scheduling_locked_reason || 'No reason provided'}
                        </span>
                    </div>
                )}
                
                {/* Contact Info */}
                <div className="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="flex items-center gap-2 text-sm text-slate-600">
                        <Mail className="w-4 h-4" />
                        <span>{profile.email}</span>
                    </div>
                    {profile.phone && (
                        <div className="flex items-center gap-2 text-sm text-slate-600">
                            <Phone className="w-4 h-4" />
                            <span>{profile.phone}</span>
                        </div>
                    )}
                    {profile.organization && (
                        <div className="flex items-center gap-2 text-sm text-slate-600">
                            <Briefcase className="w-4 h-4" />
                            <span>{profile.organization.name}</span>
                        </div>
                    )}
                </div>
            </div>
            
            {/* Stats Cards */}
            {profile.stats && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
                        <p className="text-sm text-slate-500">This Week</p>
                        <p className="text-2xl font-bold text-slate-800">{profile.stats.weekly_scheduled_hours}h</p>
                        <p className="text-xs text-slate-400">of {profile.max_weekly_hours}h capacity</p>
                    </div>
                    <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
                        <p className="text-sm text-slate-500">Utilization</p>
                        <p className="text-2xl font-bold text-slate-800">{profile.stats.utilization_percent}%</p>
                        <p className="text-xs text-slate-400">of weekly capacity</p>
                    </div>
                    <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
                        <p className="text-sm text-slate-500">Satisfaction</p>
                        <p className="text-2xl font-bold text-slate-800">
                            {profile.stats.satisfaction_score !== null ? `${profile.stats.satisfaction_score}%` : 'N/A'}
                        </p>
                        <p className="text-xs text-slate-400">{profile.stats.satisfaction_label}</p>
                    </div>
                    <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
                        <p className="text-sm text-slate-500">Upcoming</p>
                        <p className="text-2xl font-bold text-slate-800">{profile.stats.upcoming_appointments_count}</p>
                        <p className="text-xs text-slate-400">appointments</p>
                    </div>
                </div>
            )}
            
            {/* Tab Navigation */}
            <div className="border-b border-slate-200 mb-6">
                <nav className="flex gap-4 overflow-x-auto">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
                                activeTab === tab.id
                                    ? 'border-teal-600 text-teal-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            <tab.icon className="w-4 h-4" />
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>
            
            {/* Tab Content */}
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                {activeTab === 'overview' && <OverviewTab profile={profile} />}
                {activeTab === 'schedule' && <ScheduleTab schedule={schedule} navigate={navigate} />}
                {activeTab === 'availability' && <AvailabilityTab availability={availability} onAdd={() => setShowAvailabilityModal(true)} />}
                {activeTab === 'timeoff' && <TimeOffTab unavailabilities={unavailabilities} onAdd={() => setShowTimeOffModal(true)} />}
                {activeTab === 'skills' && <SkillsTab skills={skills} onAdd={() => setShowSkillModal(true)} staffId={id} onRefresh={fetchSkills} />}
                {activeTab === 'travel' && <TravelTab travel={travel} />}
            </div>
        </div>
    );
};

// Overview Tab Component
const OverviewTab = ({ profile }) => {
    return (
        <div className="space-y-6">
            {/* Employment Details */}
            <div>
                <h3 className="text-lg font-semibold text-slate-800 mb-3">Employment Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="flex justify-between py-2 border-b border-slate-100">
                        <span className="text-sm text-slate-500">Hire Date</span>
                        <span className="text-sm font-medium text-slate-800">
                            {profile.hire_date || 'Not set'}
                        </span>
                    </div>
                    <div className="flex justify-between py-2 border-b border-slate-100">
                        <span className="text-sm text-slate-500">Tenure</span>
                        <span className="text-sm font-medium text-slate-800">
                            {profile.tenure?.display || 'N/A'}
                        </span>
                    </div>
                    <div className="flex justify-between py-2 border-b border-slate-100">
                        <span className="text-sm text-slate-500">Max Weekly Hours</span>
                        <span className="text-sm font-medium text-slate-800">{profile.max_weekly_hours}h</span>
                    </div>
                    <div className="flex justify-between py-2 border-b border-slate-100">
                        <span className="text-sm text-slate-500">FTE Value</span>
                        <span className="text-sm font-medium text-slate-800">{profile.fte_value}</span>
                    </div>
                    <div className="flex justify-between py-2 border-b border-slate-100">
                        <span className="text-sm text-slate-500">FTE Eligible</span>
                        <span className="text-sm font-medium text-slate-800">
                            {profile.fte_eligible ? 'Yes' : 'No'}
                        </span>
                    </div>
                    <div className="flex justify-between py-2 border-b border-slate-100">
                        <span className="text-sm text-slate-500">Skills</span>
                        <span className="text-sm font-medium text-slate-800">
                            {profile.skills_count} total, {profile.expiring_skills_count} expiring soon
                        </span>
                    </div>
                </div>
            </div>
            
            {/* This Week Summary */}
            {profile.stats?.this_week && (
                <div>
                    <h3 className="text-lg font-semibold text-slate-800 mb-3">This Week</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="bg-slate-50 rounded-lg p-3 text-center">
                            <p className="text-2xl font-bold text-slate-800">{profile.stats.this_week.total_assignments}</p>
                            <p className="text-xs text-slate-500">Total Visits</p>
                        </div>
                        <div className="bg-emerald-50 rounded-lg p-3 text-center">
                            <p className="text-2xl font-bold text-emerald-700">{profile.stats.this_week.completed}</p>
                            <p className="text-xs text-slate-500">Completed</p>
                        </div>
                        <div className="bg-blue-50 rounded-lg p-3 text-center">
                            <p className="text-2xl font-bold text-blue-700">{profile.stats.this_week.scheduled}</p>
                            <p className="text-xs text-slate-500">Scheduled</p>
                        </div>
                        <div className="bg-red-50 rounded-lg p-3 text-center">
                            <p className="text-2xl font-bold text-red-700">{profile.stats.this_week.missed}</p>
                            <p className="text-xs text-slate-500">Missed</p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

// Schedule Tab Component
const ScheduleTab = ({ schedule, navigate }) => {
    if (!schedule) {
        return <div className="flex justify-center py-8"><Spinner className="w-6 h-6 text-teal-600" /></div>;
    }
    
    return (
        <div className="space-y-6">
            {/* Next Appointment */}
            {schedule.summary?.next_appointment && (
                <div className="bg-teal-50 border border-teal-200 rounded-lg p-4">
                    <h4 className="text-sm font-medium text-teal-800 mb-2">Next Appointment</h4>
                    <p className="text-lg font-semibold text-teal-900">
                        {schedule.summary.next_appointment.patient_name}
                    </p>
                    <p className="text-sm text-teal-700">
                        {schedule.summary.next_appointment.scheduled_time} - {schedule.summary.next_appointment.service_type_name}
                    </p>
                </div>
            )}
            
            {/* Weekly Schedule */}
            <div>
                <h3 className="text-lg font-semibold text-slate-800 mb-3">Weekly Schedule</h3>
                <div className="grid grid-cols-7 gap-2">
                    {schedule.weekly_schedule?.map((day) => (
                        <div key={day.date} className="bg-slate-50 rounded-lg p-3">
                            <p className="text-xs font-medium text-slate-600 text-center">{day.day_short}</p>
                            <p className="text-lg font-bold text-slate-800 text-center">{day.count}</p>
                            <p className="text-xs text-slate-400 text-center">{day.total_hours}h</p>
                        </div>
                    ))}
                </div>
            </div>
            
            {/* Upcoming Appointments */}
            <div>
                <h3 className="text-lg font-semibold text-slate-800 mb-3">Upcoming Appointments</h3>
                {schedule.upcoming?.length > 0 ? (
                    <div className="space-y-2">
                        {schedule.upcoming.slice(0, 10).map((apt) => (
                            <div key={apt.id} className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                <div>
                                    <p className="font-medium text-slate-800">{apt.patient_name}</p>
                                    <p className="text-sm text-slate-500">
                                        {apt.scheduled_date} at {apt.scheduled_time} • {apt.service_type_name}
                                    </p>
                                </div>
                                <span className={`px-2 py-1 rounded text-xs font-medium bg-${apt.status_color}-100 text-${apt.status_color}-800`}>
                                    {apt.status_label}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-slate-500 text-center py-4">No upcoming appointments</p>
                )}
            </div>
        </div>
    );
};

// Availability Tab Component
const AvailabilityTab = ({ availability, onAdd }) => {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-slate-800">Weekly Availability</h3>
                <Button onClick={onAdd}>Add Availability</Button>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-7 gap-2">
                {days.map((day, index) => {
                    const dayBlocks = availability.filter(a => a.day_of_week === index);
                    return (
                        <div key={day} className="bg-slate-50 rounded-lg p-3">
                            <p className="text-sm font-medium text-slate-600 mb-2">{day}</p>
                            {dayBlocks.length > 0 ? (
                                dayBlocks.map((block) => (
                                    <div key={block.id} className="bg-teal-100 text-teal-800 rounded px-2 py-1 text-xs mb-1">
                                        {block.start_time} - {block.end_time}
                                    </div>
                                ))
                            ) : (
                                <p className="text-xs text-slate-400">No availability</p>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

// Time Off Tab Component
const TimeOffTab = ({ unavailabilities, onAdd }) => {
    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-slate-800">Time Off Requests</h3>
                <Button onClick={onAdd}>Request Time Off</Button>
            </div>
            
            {unavailabilities.length > 0 ? (
                <div className="space-y-3">
                    {unavailabilities.map((u) => (
                        <div key={u.id} className={`p-4 rounded-lg border ${u.is_current ? 'bg-amber-50 border-amber-200' : 'bg-slate-50 border-slate-200'}`}>
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className="font-medium text-slate-800">{u.type_label}</p>
                                    <p className="text-sm text-slate-500">
                                        {u.start_date} to {u.end_date}
                                    </p>
                                    {u.reason && <p className="text-sm text-slate-600 mt-1">{u.reason}</p>}
                                </div>
                                <span className={`px-2 py-1 rounded text-xs font-medium ${
                                    u.approval_status === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                                    u.approval_status === 'denied' ? 'bg-red-100 text-red-800' :
                                    'bg-amber-100 text-amber-800'
                                }`}>
                                    {u.approval_status_label}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-slate-500 text-center py-8">No time off requests</p>
            )}
        </div>
    );
};

// Skills Tab Component
const SkillsTab = ({ skills, onAdd, staffId, onRefresh }) => {
    const handleRemoveSkill = async (skillId) => {
        try {
            await api.delete(`/v2/staff/${staffId}/skills/${skillId}`);
            onRefresh();
        } catch (err) {
            console.error('Failed to remove skill:', err);
        }
    };
    
    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-slate-800">Skills & Certifications</h3>
                <Button onClick={onAdd}>Add Skill</Button>
            </div>
            
            {skills.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {skills.map((skill) => (
                        <div key={skill.id} className={`p-4 rounded-lg border ${
                            skill.is_expired ? 'bg-red-50 border-red-200' :
                            skill.is_expiring_soon ? 'bg-amber-50 border-amber-200' :
                            'bg-slate-50 border-slate-200'
                        }`}>
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className="font-medium text-slate-800">{skill.name}</p>
                                    <p className="text-xs text-slate-500">{skill.category}</p>
                                    {skill.expires_at && (
                                        <p className={`text-xs mt-1 ${skill.is_expired ? 'text-red-600' : skill.is_expiring_soon ? 'text-amber-600' : 'text-slate-500'}`}>
                                            {skill.is_expired ? 'Expired' : skill.is_expiring_soon ? 'Expiring soon' : `Expires: ${skill.expires_at}`}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`px-2 py-0.5 rounded text-xs font-medium bg-${skill.status_color}-100 text-${skill.status_color}-800`}>
                                        {skill.proficiency_level}
                                    </span>
                                    <button
                                        onClick={() => handleRemoveSkill(skill.id)}
                                        className="text-slate-400 hover:text-red-600"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-slate-500 text-center py-8">No skills assigned</p>
            )}
        </div>
    );
};

// Travel Tab Component
const TravelTab = ({ travel }) => {
    if (!travel) {
        return <div className="flex justify-center py-8"><Spinner className="w-6 h-6 text-teal-600" /></div>;
    }
    
    return (
        <div className="space-y-6">
            {/* Weekly Summary */}
            <div>
                <h3 className="text-lg font-semibold text-slate-800 mb-3">This Week's Travel</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-slate-50 rounded-lg p-4 text-center">
                        <p className="text-2xl font-bold text-slate-800">{travel.weekly_metrics?.total_travel_hours || 0}h</p>
                        <p className="text-xs text-slate-500">Total Travel</p>
                    </div>
                    <div className="bg-slate-50 rounded-lg p-4 text-center">
                        <p className="text-2xl font-bold text-slate-800">{travel.weekly_metrics?.total_assignments || 0}</p>
                        <p className="text-xs text-slate-500">Appointments</p>
                    </div>
                    <div className="bg-slate-50 rounded-lg p-4 text-center">
                        <p className="text-2xl font-bold text-slate-800">{travel.weekly_metrics?.average_travel_per_assignment || 0}</p>
                        <p className="text-xs text-slate-500">Avg mins/visit</p>
                    </div>
                    <div className="bg-slate-50 rounded-lg p-4 text-center">
                        <p className="text-2xl font-bold text-slate-800">{travel.estimated_weekly_overhead || 0}h</p>
                        <p className="text-xs text-slate-500">Estimated Weekly</p>
                    </div>
                </div>
            </div>
            
            {/* Travel by Day */}
            {travel.weekly_metrics?.by_day?.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-slate-800 mb-3">Travel by Day</h3>
                    <div className="grid grid-cols-7 gap-2">
                        {travel.weekly_metrics.by_day.map((day) => (
                            <div key={day.date} className="bg-slate-50 rounded-lg p-3 text-center">
                                <p className="text-xs font-medium text-slate-600">{day.day_name.substring(0, 3)}</p>
                                <p className="text-lg font-bold text-slate-800">{day.total_travel_minutes}m</p>
                                <p className="text-xs text-slate-400">{day.assignment_count} visits</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
            
            {/* Recent Trips */}
            {travel.assignment_details?.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-slate-800 mb-3">Recent Travel</h3>
                    <div className="space-y-2">
                        {travel.assignment_details.slice(0, 10).map((apt, index) => (
                            apt.travel_minutes > 0 && (
                                <div key={index} className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <div>
                                        <p className="font-medium text-slate-800">{apt.patient_name}</p>
                                        <p className="text-sm text-slate-500">{apt.scheduled_date}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium text-slate-800">{apt.travel_minutes} mins</p>
                                        <p className="text-xs text-slate-400">from {apt.travel_from}</p>
                                    </div>
                                </div>
                            )
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default StaffProfilePage;
