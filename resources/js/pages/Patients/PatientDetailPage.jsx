import React, { useEffect, useState, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../services/api';
import Card from '../../components/UI/Card';
import Spinner from '../../components/UI/Spinner';
import Button from '../../components/UI/Button';
import BundleSummary from '../../components/care/BundleSummary';
import FullInterraiAssessment from '../../components/InterRAI/FullInterraiAssessment';
import { X, Send, FileText, Calendar, Clock, CheckCircle } from 'lucide-react';

/**
 * PatientDetailPage - Central hub for patient care management
 *
 * Displays:
 * - Patient demographics
 * - Active care plans with services
 * - Clinical flags and narrative
 * - Notes / Narrative (replaces TNP)
 * - InterRAI assessment scores
 * - Care bundle creation entry point
 */
const PatientDetailPage = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [patient, setPatient] = useState(null);
    const [carePlans, setCarePlans] = useState([]);
    const [notesData, setNotesData] = useState({ summary_note: null, notes: [] });
    const [interraiData, setInterraiData] = useState(null);
    const [scheduleData, setScheduleData] = useState({ upcoming: [], history: [] });
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('overview'); // overview | interrai | notes | schedule
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [newNoteContent, setNewNoteContent] = useState('');
    const [submittingNote, setSubmittingNote] = useState(false);

    const fetchData = useCallback(async () => {
        try {
            setLoading(true);

            // Calculate date ranges for schedule
            const today = new Date();
            const startDate = today.toISOString().split('T')[0];
            const futureDate = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            const pastDate = new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

            // Fetch all data in parallel
            const [patientRes, overviewRes, carePlansRes, notesRes, interraiRes, upcomingRes, historyRes] = await Promise.all([
                api.get(`/patients/${id}`),
                api.get(`/v2/patients/${id}/overview`).catch(() => ({ data: { data: null } })),
                api.get(`/v2/care-plans?patient_id=${id}`).catch(() => ({ data: { data: [] } })),
                api.get(`/v2/patients/${id}/notes`).catch(() => ({ data: { data: { summary_note: null, notes: [] } } })),
                api.get(`/v2/interrai/patients/${id}/status`).catch(() => ({ data: { data: null } })),
                // Upcoming appointments (next 30 days)
                api.get(`/v2/scheduling/grid?patient_id=${id}&start_date=${startDate}&end_date=${futureDate}`).catch(() => ({ data: { data: { assignments: [] } } })),
                // Past appointments (last 90 days)
                api.get(`/v2/scheduling/grid?patient_id=${id}&start_date=${pastDate}&end_date=${startDate}`).catch(() => ({ data: { data: { assignments: [] } } })),
            ]);

            setPatient({
                ...patientRes.data.data,
                // Merge in overview data (clinical flags, narrative, etc.)
                active_flags: overviewRes.data?.data?.active_flags || [],
                narrative_summary: overviewRes.data?.data?.narrative_summary || null,
            });
            setCarePlans(carePlansRes.data.data || carePlansRes.data || []);
            setNotesData(notesRes.data?.data || { summary_note: null, notes: [] });
            setInterraiData(interraiRes.data?.data || null);
            setScheduleData({
                upcoming: upcomingRes.data?.data?.assignments || [],
                history: historyRes.data?.data?.assignments || [],
            });
        } catch (error) {
            console.error('Failed to fetch patient data:', error);
        } finally {
            setLoading(false);
        }
    }, [id]);

    const handleAddNote = async () => {
        if (!newNoteContent.trim()) return;

        try {
            setSubmittingNote(true);
            const response = await api.post(`/v2/patients/${id}/notes`, {
                content: newNoteContent,
                note_type: 'update',
            });

            // Add new note to the list
            setNotesData(prev => ({
                ...prev,
                notes: [response.data.data, ...prev.notes],
            }));
            setNewNoteContent('');
        } catch (error) {
            console.error('Failed to add note:', error);
            alert('Failed to add note. Please try again.');
        } finally {
            setSubmittingNote(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Helper to get MAPLe color
    const getMapleColor = (score) => {
        const colors = {
            1: 'text-emerald-600',
            2: 'text-emerald-500',
            3: 'text-amber-500',
            4: 'text-orange-500',
            5: 'text-rose-600',
        };
        return colors[score] || 'text-slate-400';
    };

    // Helper to get score color
    const getScoreColor = (score, maxScore) => {
        if (score === null || score === undefined) return 'text-slate-400';
        const ratio = score / maxScore;
        if (ratio >= 0.7) return 'text-rose-600';
        if (ratio >= 0.4) return 'text-amber-600';
        return 'text-emerald-600';
    };

    // Get status badge styling
    const getStatusBadge = (status) => {
        const styles = {
            active: 'bg-emerald-100 text-emerald-800',
            draft: 'bg-slate-100 text-slate-800',
            pending_approval: 'bg-amber-100 text-amber-800',
            approved: 'bg-blue-100 text-blue-800',
            completed: 'bg-slate-100 text-slate-600',
            cancelled: 'bg-rose-100 text-rose-800',
        };
        return styles[status] || 'bg-slate-100 text-slate-800';
    };

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;
    if (!patient) return <div className="p-12 text-center">Patient not found.</div>;

    // Sort plans by ID descending (newest first)
    const sortedPlans = [...carePlans].sort((a, b) => b.id - a.id);
    const currentPlan = sortedPlans.find(p => p.status === 'active' || p.status === 'approved');
    const historyPlans = sortedPlans.filter(p => p.id !== currentPlan?.id);

    const latestAssessment = interraiData?.latest_assessment;
    const hasActivePlan = !!currentPlan;

    // Map API services to BundleSummary format
    // All services in the current plan are considered "core" (defaultFrequency > 0)
    const summaryServices = currentPlan?.services?.map(s => ({
        ...s,
        currentFrequency: s.frequency || 1,
        defaultFrequency: 1, // All existing plan services are core services
        currentDuration: s.duration || 12,
        costPerVisit: s.cost_per_visit || 0,
    })) || [];

    // Calculate total weekly cost from services
    const calculatedWeeklyCost = summaryServices.reduce((total, s) => {
        return total + ((s.costPerVisit || 0) * (s.currentFrequency || 0));
    }, 0);

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-start">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">{patient.name}</h1>
                    <p className="text-slate-500">Patient ID: {patient.id}</p>
                </div>
                <div className="flex gap-3">
                    <Button
                        className="bg-teal-600 hover:bg-teal-700 text-white"
                        onClick={() => navigate(`/care-bundles/create/${id}`)}
                    >
                        {hasActivePlan ? 'Modify Care Plan' : 'Create Care Bundle'}
                    </Button>
                </div>
            </div>

            {/* Tab Navigation */}
            <div className="border-b border-slate-200">
                <nav className="-mb-px flex gap-6">
                    {[
                        { id: 'overview', label: 'Overview' },
                        { id: 'schedule', label: 'Schedule & History', badge: scheduleData.upcoming.length > 0 ? `${scheduleData.upcoming.length} upcoming` : null },
                        { id: 'interrai', label: 'InterRAI HC', badge: interraiData?.requires_assessment ? 'Action Needed' : null },
                        { id: 'notes', label: 'Notes / Narrative' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`py-3 px-1 border-b-2 font-medium text-sm transition-colors ${activeTab === tab.id
                                ? 'border-teal-500 text-teal-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                                }`}
                        >
                            {tab.label}
                            {tab.badge && (
                                <span className="ml-2 px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full">
                                    {tab.badge}
                                </span>
                            )}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Overview Tab */}
            {activeTab === 'overview' && (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column: Demographics */}
                    <Card title="Demographics" className="lg:col-span-1">
                        <div className="flex flex-col items-center mb-4">
                            {patient.photo && patient.photo !== '/assets/images/patients/default.png' ? (
                                <img
                                    src={patient.photo}
                                    alt={patient.name}
                                    className="w-24 h-24 rounded-full mb-2 bg-gray-200 object-cover"
                                    onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'flex'; }}
                                />
                            ) : (
                                <div className="w-24 h-24 rounded-full mb-2 bg-gray-200 flex items-center justify-center p-2 text-center">
                                    <span className="text-xs font-bold text-slate-600 leading-tight">
                                        {patient.name}
                                    </span>
                                </div>
                            )}
                            {/* Fallback for image load error (hidden by default) */}
                            <div className="w-24 h-24 rounded-full mb-2 bg-gray-200 items-center justify-center p-2 text-center hidden">
                                <span className="text-xs font-bold text-slate-600 leading-tight">
                                    {patient.name}
                                </span>
                            </div>
                            <span className={`px-3 py-1 rounded-full text-sm font-medium ${patient.status === 'active' ? 'bg-emerald-100 text-emerald-800' :
                                patient.status === 'Available' ? 'bg-emerald-100 text-emerald-800' :
                                    'bg-slate-100 text-slate-800'
                                }`}>
                                {patient.status}
                            </span>
                        </div>
                        <div className="space-y-3">
                            <div>
                                <label className="text-xs text-slate-500 uppercase">Gender</label>
                                <p className="font-medium">{patient.gender || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500 uppercase">Date of Birth</label>
                                <p className="font-medium">{patient.date_of_birth || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500 uppercase">Phone</label>
                                <p className="font-medium">{patient.phone || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500 uppercase">Email</label>
                                <p className="font-medium truncate">{patient.email || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500 uppercase">Address</label>
                                <p className="font-medium text-sm">
                                    {patient.address_line_1 || patient.address || 'N/A'}
                                    {patient.city && `, ${patient.city}`}
                                    {patient.postal_code && ` ${patient.postal_code}`}
                                </p>
                            </div>
                        </div>
                    </Card>

                    {/* Right Column: Care Context */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Current Care Plan */}
                        <Card
                            title="Current Care Plan"
                            action={
                                hasActivePlan ? (
                                    <div className="flex gap-3">
                                        <Button variant="outline" onClick={() => setShowDetailsModal(true)}>
                                            View Details
                                        </Button>
                                        <Button variant="link" onClick={() => navigate(`/care-bundles/create/${id}`)}>
                                            Modify Plan
                                        </Button>
                                    </div>
                                ) : null
                            }
                        >
                            {hasActivePlan ? (
                                <div className="space-y-4">
                                    <div className="p-4 bg-slate-50 rounded-lg border border-slate-200">
                                        <div className="flex justify-between items-start mb-3">
                                            <div>
                                                <h4 className="font-semibold text-slate-900">
                                                    {currentPlan.bundle?.name || currentPlan.bundle_name || 'Care Plan'}
                                                </h4>
                                                <p className="text-sm text-slate-500">
                                                    Started: {currentPlan.start_date?.split('T')[0] || 'N/A'}
                                                </p>
                                            </div>
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusBadge(currentPlan.status)}`}>
                                                {currentPlan.status?.replace('_', ' ')}
                                            </span>
                                        </div>

                                        {/* Services Summary */}
                                        {currentPlan.services && currentPlan.services.length > 0 && (
                                            <div className="mt-3 pt-3 border-t border-slate-200">
                                                <p className="text-xs text-slate-500 uppercase mb-2">Active Services</p>
                                                <div className="flex flex-wrap gap-2">
                                                    {currentPlan.services.map((service, idx) => (
                                                        <span key={idx} className="px-2 py-1 bg-white border border-slate-200 rounded text-xs text-slate-700">
                                                            {service.name || service.service_type?.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-6">
                                    <svg className="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p className="text-slate-500 font-medium">No active Care Plan</p>
                                    <p className="text-sm text-slate-400 mt-1">Create a care bundle to activate services for this patient</p>
                                    <Button
                                        className="mt-4"
                                        onClick={() => navigate(`/care-bundles/create/${id}`)}
                                    >
                                        Create Care Bundle
                                    </Button>
                                </div>
                            )}
                        </Card>

                        {/* Plan History */}
                        {historyPlans.length > 0 && (
                            <Card title="Plan History">
                                <div className="space-y-3">
                                    {historyPlans.map((plan) => (
                                        <div key={plan.id} className="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                                            <div>
                                                <div className="font-medium text-slate-900 text-sm">
                                                    {plan.bundle?.name || plan.bundle_name || 'Care Plan'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {plan.start_date?.split('T')[0]} — {plan.status === 'active' ? 'Present' : 'Ended'}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className={`px-2 py-1 rounded-full text-xs font-medium ${plan.status === 'active' ? 'bg-slate-200 text-slate-600' : // Older active plans shown as inactive/replaced
                                                    'bg-slate-100 text-slate-500'
                                                    }`}>
                                                    {plan.status === 'active' ? 'Revised' : plan.status}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

                        {/* Quick Stats */}
                        {latestAssessment && (
                            <Card title="Clinical Snapshot">
                                <div className="grid grid-cols-4 gap-4">
                                    <div className="text-center p-3 bg-slate-50 rounded-lg">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">MAPLe</div>
                                        <div className={`text-2xl font-bold ${getMapleColor(latestAssessment.maple_score)}`}>
                                            {latestAssessment.maple_score || '-'}
                                        </div>
                                    </div>
                                    <div className="text-center p-3 bg-slate-50 rounded-lg">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">CPS</div>
                                        <div className={`text-2xl font-bold ${getScoreColor(latestAssessment.cps, 6)}`}>
                                            {latestAssessment.cps ?? '-'}
                                        </div>
                                    </div>
                                    <div className="text-center p-3 bg-slate-50 rounded-lg">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">ADL</div>
                                        <div className={`text-2xl font-bold ${getScoreColor(latestAssessment.adl_hierarchy, 6)}`}>
                                            {latestAssessment.adl_hierarchy ?? '-'}
                                        </div>
                                    </div>
                                    <div className="text-center p-3 bg-slate-50 rounded-lg">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">CHESS</div>
                                        <div className={`text-2xl font-bold ${getScoreColor(latestAssessment.chess_score, 5)}`}>
                                            {latestAssessment.chess_score ?? '-'}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setActiveTab('interrai')}
                                    className="mt-3 text-sm text-teal-600 hover:text-teal-700 font-medium"
                                >
                                    View full InterRAI assessment →
                                </button>
                            </Card>
                        )}

                        {/* Clinical Flags */}
                        <Card title="Clinical Flags">
                            {patient.active_flags && patient.active_flags.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {patient.active_flags.map((flag, index) => (
                                        <span
                                            key={index}
                                            className={`px-3 py-1 rounded-full text-sm font-medium ${
                                                flag.severity === 'danger'
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : flag.severity === 'warning'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : 'bg-blue-100 text-blue-800'
                                            }`}
                                        >
                                            {flag.label}
                                        </span>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-slate-500 text-sm">No major risk flags identified.</p>
                            )}
                        </Card>
                    </div>
                </div>
            )}

            {/* Schedule & History Tab */}
            {activeTab === 'schedule' && (
                <div className="space-y-6">
                    {/* Upcoming Appointments */}
                    <Card title="Upcoming Appointments">
                        {scheduleData.upcoming.length > 0 ? (
                            <div className="space-y-3">
                                {scheduleData.upcoming.slice(0, 10).map((appointment, index) => (
                                    <div key={appointment.id || index} className="flex items-center justify-between p-4 bg-slate-50 rounded-lg border border-slate-200">
                                        <div className="flex items-start gap-4">
                                            <div className="p-2 bg-teal-100 rounded-lg">
                                                <Calendar className="w-5 h-5 text-teal-600" />
                                            </div>
                                            <div>
                                                <div className="font-medium text-slate-900">
                                                    {appointment.service_type_name || 'Service Visit'}
                                                </div>
                                                <div className="text-sm text-slate-500 flex items-center gap-2 mt-1">
                                                    <Clock className="w-4 h-4" />
                                                    {appointment.date} at {appointment.start_time || 'TBD'}
                                                    {appointment.duration_minutes && ` (${appointment.duration_minutes} min)`}
                                                </div>
                                                {appointment.staff_name && (
                                                    <div className="text-sm text-slate-500 mt-1">
                                                        Provider: {appointment.staff_name}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className={`px-2 py-1 text-xs rounded-full font-medium ${
                                                appointment.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                                                appointment.status === 'planned' ? 'bg-blue-100 text-blue-700' :
                                                appointment.status === 'cancelled' ? 'bg-slate-100 text-slate-600' :
                                                'bg-amber-100 text-amber-700'
                                            }`}>
                                                {appointment.status || 'scheduled'}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {scheduleData.upcoming.length > 10 && (
                                    <div className="text-center text-sm text-slate-500 pt-2">
                                        +{scheduleData.upcoming.length - 10} more appointments
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <Calendar className="w-12 h-12 text-slate-300 mx-auto mb-3" />
                                <p className="text-slate-500 font-medium">No upcoming appointments</p>
                                <p className="text-sm text-slate-400 mt-1">Appointments will appear here once scheduled</p>
                                <Button
                                    variant="outline"
                                    className="mt-4"
                                    onClick={() => navigate(`/spo/scheduling?patient_id=${id}`)}
                                >
                                    View Schedule
                                </Button>
                            </div>
                        )}
                    </Card>

                    {/* Service History */}
                    <Card title="Service History (Last 90 Days)">
                        {scheduleData.history.length > 0 ? (
                            <div className="space-y-3">
                                {scheduleData.history.slice(0, 20).map((visit, index) => (
                                    <div key={visit.id || index} className="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                                        <div className="flex items-center gap-3">
                                            <div className={`p-1.5 rounded ${
                                                visit.status === 'completed' ? 'bg-emerald-100' :
                                                visit.status === 'missed' ? 'bg-rose-100' :
                                                'bg-slate-100'
                                            }`}>
                                                {visit.status === 'completed' ? (
                                                    <CheckCircle className="w-4 h-4 text-emerald-600" />
                                                ) : (
                                                    <Clock className="w-4 h-4 text-slate-500" />
                                                )}
                                            </div>
                                            <div>
                                                <div className="font-medium text-slate-800 text-sm">
                                                    {visit.service_type_name || 'Service Visit'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {visit.date} • {visit.staff_name || 'Unassigned'}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {visit.verification_status && (
                                                <span className={`px-2 py-0.5 text-xs rounded font-medium ${
                                                    visit.verification_status === 'VERIFIED' ? 'bg-emerald-100 text-emerald-700' :
                                                    visit.verification_status === 'MISSED' ? 'bg-rose-100 text-rose-700' :
                                                    'bg-amber-100 text-amber-700'
                                                }`}>
                                                    {visit.verification_status.toLowerCase()}
                                                </span>
                                            )}
                                            <span className={`px-2 py-0.5 text-xs rounded font-medium ${
                                                visit.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                                                visit.status === 'cancelled' ? 'bg-slate-100 text-slate-600' :
                                                visit.status === 'missed' ? 'bg-rose-100 text-rose-700' :
                                                'bg-blue-100 text-blue-700'
                                            }`}>
                                                {visit.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {scheduleData.history.length > 20 && (
                                    <div className="text-center text-sm text-slate-500 pt-2">
                                        Showing 20 of {scheduleData.history.length} visits
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-6">
                                <Clock className="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                <p className="text-slate-500 text-sm">No service history available</p>
                            </div>
                        )}
                    </Card>
                </div>
            )}

            {/* Notes / Narrative Tab */}
            {activeTab === 'notes' && (
                <div className="space-y-6">
                    {/* Narrative Summary */}
                    {notesData.summary_note && (
                        <Card title="Narrative Summary">
                            <div className="flex items-start gap-3 mb-2">
                                <FileText className="w-5 h-5 text-teal-600 flex-shrink-0 mt-0.5" />
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 mb-2">
                                        <span className="text-xs font-medium text-slate-500 uppercase">
                                            Summary at Intake
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            {notesData.summary_note.created_at_formatted}
                                        </span>
                                    </div>
                                    <p className="text-slate-700 whitespace-pre-wrap leading-relaxed">
                                        {notesData.summary_note.content}
                                    </p>
                                    <div className="mt-2 text-xs text-slate-400">
                                        Source: {notesData.summary_note.source}
                                    </div>
                                </div>
                            </div>
                        </Card>
                    )}

                    {/* Add Note Form */}
                    <Card title="Add Note">
                        <div className="space-y-3">
                            <textarea
                                value={newNoteContent}
                                onChange={(e) => setNewNoteContent(e.target.value)}
                                placeholder="Enter a new note or update..."
                                rows={3}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400 resize-none"
                            />
                            <div className="flex justify-end">
                                <Button
                                    onClick={handleAddNote}
                                    disabled={submittingNote || !newNoteContent.trim()}
                                    className="flex items-center gap-2"
                                >
                                    <Send className="w-4 h-4" />
                                    {submittingNote ? 'Saving...' : 'Add Note'}
                                </Button>
                            </div>
                        </div>
                    </Card>

                    {/* Notes List */}
                    <Card title="Notes & Updates">
                        {notesData.notes && notesData.notes.length > 0 ? (
                            <div className="space-y-4">
                                {notesData.notes.map((note, index) => (
                                    <div
                                        key={note.id || index}
                                        className="p-4 bg-slate-50 rounded-lg border border-slate-200"
                                    >
                                        <div className="flex items-start justify-between mb-2">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium text-slate-700">
                                                    {note.source}
                                                </span>
                                                {note.author_name && (
                                                    <span className="text-xs text-slate-500">
                                                        by {note.author_name}
                                                    </span>
                                                )}
                                            </div>
                                            <span className="text-xs text-slate-400">
                                                {note.created_at_formatted}
                                            </span>
                                        </div>
                                        <p className="text-slate-600 text-sm whitespace-pre-wrap">
                                            {note.content}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-6">
                                <FileText className="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                <p className="text-slate-500 text-sm">No notes or updates yet.</p>
                                <p className="text-xs text-slate-400 mt-1">
                                    Add a note above to track updates for this patient.
                                </p>
                            </div>
                        )}
                    </Card>
                </div>
            )}

            {/* InterRAI Tab */}
            {activeTab === 'interrai' && (
                <div className="space-y-6">
                    {/* Assessment Required Banner */}
                    {interraiData?.requires_assessment && (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div className="flex-1">
                                    <p className="font-medium text-amber-800">InterRAI Assessment Required</p>
                                    <p className="text-sm text-amber-700 mt-1">{interraiData?.message}</p>
                                    <Button
                                        className="mt-3"
                                        onClick={() => navigate(`/interrai/assess/${id}`)}
                                    >
                                        Complete InterRAI HC Assessment
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}

                    {latestAssessment ? (
                        <>
                            {/* Assessment Info */}
                            <Card title="Current InterRAI HC Assessment">
                                <div className="flex justify-between items-center mb-4">
                                    <div className="text-sm text-slate-500 flex items-center gap-2 flex-wrap">
                                        <span>
                                            Assessment Date: <span className="font-medium text-slate-700">
                                                {latestAssessment.assessment_date?.split('T')[0]}
                                            </span>
                                        </span>
                                        {latestAssessment.workflow_status === 'completed' ? (
                                            <span className="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-xs rounded-full font-medium">
                                                Complete
                                            </span>
                                        ) : latestAssessment.workflow_status === 'in_progress' ? (
                                            <span className="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full font-medium">
                                                In Progress
                                            </span>
                                        ) : latestAssessment.workflow_status === 'draft' ? (
                                            <span className="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs rounded-full font-medium">
                                                Draft
                                            </span>
                                        ) : null}
                                        {latestAssessment.is_stale && (
                                            <span className="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full font-medium">
                                                Stale ({'>'}90 days)
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-sm">
                                        Type: <span className="font-medium capitalize">{latestAssessment.assessment_type === 'hc' ? 'Home Care' : latestAssessment.assessment_type}</span>
                                    </div>
                                </div>

                                {/* RUG Classification */}
                                {latestAssessment.rug_classification && (
                                    <div className="mb-4 p-3 bg-teal-50 border border-teal-200 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <span className="text-xs font-semibold text-teal-600 uppercase">RUG Classification</span>
                                                <div className="text-lg font-bold text-teal-800">
                                                    {latestAssessment.rug_classification.rug_group}
                                                </div>
                                                <div className="text-sm text-teal-600">
                                                    {latestAssessment.rug_classification.rug_category}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-xs text-teal-500">
                                                    {latestAssessment.rug_classification.category_description}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Score Cards Grid */}
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">MAPLe</div>
                                        <div className={`text-3xl font-bold ${getMapleColor(latestAssessment.maple_score)}`}>
                                            {latestAssessment.maple_score || '-'}
                                        </div>
                                        <div className="text-xs text-slate-500 mt-1">Priority Level</div>
                                    </div>
                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">CPS</div>
                                        <div className={`text-3xl font-bold ${getScoreColor(latestAssessment.cps, 6)}`}>
                                            {latestAssessment.cps ?? '-'}
                                        </div>
                                        <div className="text-xs text-slate-500 mt-1">Cognitive Performance</div>
                                    </div>
                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">ADL</div>
                                        <div className={`text-3xl font-bold ${getScoreColor(latestAssessment.adl_hierarchy, 6)}`}>
                                            {latestAssessment.adl_hierarchy ?? '-'}
                                        </div>
                                        <div className="text-xs text-slate-500 mt-1">Daily Living</div>
                                    </div>
                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
                                        <div className="text-xs font-semibold text-slate-500 uppercase mb-1">CHESS</div>
                                        <div className={`text-3xl font-bold ${getScoreColor(latestAssessment.chess_score, 5)}`}>
                                            {latestAssessment.chess_score ?? '-'}
                                        </div>
                                        <div className="text-xs text-slate-500 mt-1">Health Instability</div>
                                    </div>
                                </div>
                            </Card>

                            {/* Risk Flags */}
                            {latestAssessment.high_risk_flags && latestAssessment.high_risk_flags.length > 0 && (
                                <Card title="Risk Flags">
                                    <div className="flex flex-wrap gap-2">
                                        {latestAssessment.high_risk_flags.map((flag, index) => (
                                            <span
                                                key={index}
                                                className="px-3 py-1 bg-rose-100 text-rose-800 text-sm rounded-full font-medium"
                                            >
                                                {flag}
                                            </span>
                                        ))}
                                    </div>
                                </Card>
                            )}

                            {/* Full Assessment Sections */}
                            {latestAssessment.sections && Object.keys(latestAssessment.sections).length > 0 && (
                                <Card>
                                    <FullInterraiAssessment assessment={latestAssessment} />
                                </Card>
                            )}

                            {/* Integration Status */}
                            <Card title="Integration Status">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="p-4 bg-slate-50 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-slate-700">IAR Upload</span>
                                            <span className={`px-2 py-1 text-xs rounded-full font-medium ${latestAssessment.iar_status === 'uploaded'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : latestAssessment.iar_status === 'failed'
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : 'bg-amber-100 text-amber-800'
                                                }`}>
                                                {latestAssessment.iar_status || 'pending'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="p-4 bg-slate-50 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-slate-700">CHRIS Sync</span>
                                            <span className={`px-2 py-1 text-xs rounded-full font-medium ${latestAssessment.chris_status === 'synced'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : latestAssessment.chris_status === 'failed'
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : 'bg-amber-100 text-amber-800'
                                                }`}>
                                                {latestAssessment.chris_status || 'pending'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </>
                    ) : (
                        <Card>
                            <div className="text-center py-8">
                                <svg className="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p className="text-slate-500 font-medium">No InterRAI HC Assessment</p>
                                <p className="text-sm text-slate-400 mt-1">Complete an assessment to view clinical scores</p>
                                <Button
                                    className="mt-4"
                                    onClick={() => navigate(`/interrai/assess/${id}`)}
                                >
                                    Start InterRAI HC Assessment
                                </Button>
                            </div>
                        </Card>
                    )}
                </div>
            )}
            {/* Details Modal */}
            {showDetailsModal && createPortal(
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center p-6 border-b border-slate-100">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">Care Plan Details</h2>
                                <p className="text-sm text-slate-500">
                                    {currentPlan?.bundle?.name || 'Custom Bundle'} • Started {currentPlan?.start_date?.split('T')[0]}
                                </p>
                            </div>
                            <button
                                onClick={() => setShowDetailsModal(false)}
                                className="p-2 hover:bg-slate-100 rounded-full text-slate-500"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-6">
                            <BundleSummary
                                services={summaryServices}
                                bundleName={currentPlan?.bundle || currentPlan?.bundle_name}
                                totalCost={calculatedWeeklyCost || currentPlan?.total_cost || 0}
                            />
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
};

export default PatientDetailPage;
