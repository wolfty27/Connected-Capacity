import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../services/api';
import Card from '../../components/UI/Card';
import Spinner from '../../components/UI/Spinner';
import Button from '../../components/UI/Button';

/**
 * PatientDetailPage - Central hub for patient care management
 *
 * Displays:
 * - Patient demographics
 * - Active care plans with services
 * - Transition Needs Profile (TNP)
 * - InterRAI assessment scores
 * - Care bundle creation entry point
 */
const PatientDetailPage = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [patient, setPatient] = useState(null);
    const [carePlans, setCarePlans] = useState([]);
    const [tnp, setTnp] = useState(null);
    const [interraiData, setInterraiData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('overview'); // overview | tnp | interrai

    const fetchData = useCallback(async () => {
        try {
            setLoading(true);

            // Fetch all data in parallel
            const [patientRes, carePlansRes, tnpRes, interraiRes] = await Promise.all([
                api.get(`/api/patients/${id}`),
                api.get(`/api/v2/care-plans?patient_id=${id}`).catch(() => ({ data: { data: [] } })),
                api.get(`/api/patients/${id}/tnp`).catch(() => ({ data: null })),
                api.get(`/api/v2/interrai/patients/${id}/status`).catch(() => ({ data: { data: null } })),
            ]);

            setPatient(patientRes.data.data);
            setCarePlans(carePlansRes.data.data || carePlansRes.data || []);
            setTnp(tnpRes.data);
            setInterraiData(interraiRes.data?.data || null);
        } catch (error) {
            console.error('Failed to fetch patient data:', error);
        } finally {
            setLoading(false);
        }
    }, [id]);

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

    const activePlans = carePlans.filter(p => p.status === 'active' || p.status === 'approved');
    const latestAssessment = interraiData?.latest_assessment;
    const hasActivePlan = activePlans.length > 0;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-start">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">{patient.name}</h1>
                    <p className="text-slate-500">Patient ID: {patient.id}</p>
                </div>
                <div className="flex gap-3">
                    {!hasActivePlan && (
                        <Button
                            className="bg-teal-600 hover:bg-teal-700 text-white"
                            onClick={() => navigate(`/care-bundles/create/${id}`)}
                        >
                            Create Care Bundle
                        </Button>
                    )}
                </div>
            </div>

            {/* Tab Navigation */}
            <div className="border-b border-slate-200">
                <nav className="-mb-px flex gap-6">
                    {[
                        { id: 'overview', label: 'Overview' },
                        { id: 'tnp', label: 'Transition Needs', badge: !tnp ? 'Missing' : null },
                        { id: 'interrai', label: 'InterRAI HC', badge: interraiData?.requires_assessment ? 'Action Needed' : null },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`py-3 px-1 border-b-2 font-medium text-sm transition-colors ${
                                activeTab === tab.id
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
                            <img
                                src={patient.photo || '/assets/images/patients/default.png'}
                                alt={patient.name}
                                className="w-24 h-24 rounded-full mb-2 bg-gray-200 object-cover"
                            />
                            <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                                patient.status === 'active' ? 'bg-emerald-100 text-emerald-800' :
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
                        {/* Active Care Plans */}
                        <Card
                            title="Care Context"
                            action={
                                hasActivePlan ? (
                                    <Button variant="link" onClick={() => navigate(`/care-bundles/create/${id}`)}>
                                        Modify Plan
                                    </Button>
                                ) : null
                            }
                        >
                            {hasActivePlan ? (
                                <div className="space-y-4">
                                    {activePlans.map((plan) => (
                                        <div key={plan.id} className="p-4 bg-slate-50 rounded-lg">
                                            <div className="flex justify-between items-start mb-3">
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">
                                                        {plan.bundle?.name || plan.bundle_name || 'Care Plan'}
                                                    </h4>
                                                    <p className="text-sm text-slate-500">
                                                        Started: {plan.start_date?.split('T')[0] || 'N/A'}
                                                    </p>
                                                </div>
                                                <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusBadge(plan.status)}`}>
                                                    {plan.status?.replace('_', ' ')}
                                                </span>
                                            </div>

                                            {/* Services Summary */}
                                            {plan.services && plan.services.length > 0 && (
                                                <div className="mt-3 pt-3 border-t border-slate-200">
                                                    <p className="text-xs text-slate-500 uppercase mb-2">Active Services</p>
                                                    <div className="flex flex-wrap gap-2">
                                                        {plan.services.slice(0, 5).map((service, idx) => (
                                                            <span key={idx} className="px-2 py-1 bg-white border border-slate-200 rounded text-xs text-slate-700">
                                                                {service.name || service.service_type?.name}
                                                            </span>
                                                        ))}
                                                        {plan.services.length > 5 && (
                                                            <span className="px-2 py-1 text-xs text-slate-500">
                                                                +{plan.services.length - 5} more
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
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
                    </div>
                </div>
            )}

            {/* TNP Tab */}
            {activeTab === 'tnp' && (
                <div className="space-y-6">
                    {tnp ? (
                        <>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <Card title="Narrative Summary">
                                    <p className="whitespace-pre-wrap text-slate-700">
                                        {tnp.narrative_summary || 'No narrative provided.'}
                                    </p>
                                </Card>
                                <Card title="Clinical Flags">
                                    {tnp.clinical_flags && tnp.clinical_flags.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {tnp.clinical_flags.map((flag, index) => (
                                                <span
                                                    key={index}
                                                    className="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-sm font-medium"
                                                >
                                                    {flag}
                                                </span>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-slate-500">No clinical flags identified.</p>
                                    )}
                                </Card>
                            </div>

                            {/* AI Summary */}
                            {tnp.ai_summary_text && (
                                <Card title="AI Analysis (SBAR)">
                                    <div className="flex items-center gap-2 mb-3">
                                        <span className="text-sm text-slate-500">Status:</span>
                                        <span className={`px-2 py-1 rounded text-xs font-medium ${
                                            tnp.ai_summary_status === 'completed'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-amber-100 text-amber-800'
                                        }`}>
                                            {tnp.ai_summary_status}
                                        </span>
                                    </div>
                                    <p className="text-slate-700 whitespace-pre-wrap">{tnp.ai_summary_text}</p>
                                </Card>
                            )}
                        </>
                    ) : (
                        <Card>
                            <div className="text-center py-8">
                                <svg className="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p className="text-slate-500 font-medium">No Transition Needs Profile</p>
                                <p className="text-sm text-slate-400 mt-1">A TNP has not been created for this patient yet.</p>
                                <Button
                                    className="mt-4"
                                    onClick={() => alert('Create TNP workflow - coming soon')}
                                >
                                    Create TNP
                                </Button>
                            </div>
                        </Card>
                    )}
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
                                        onClick={() => navigate(`/interrai/complete/${id}`)}
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
                                    <div className="text-sm text-slate-500">
                                        Assessment Date: <span className="font-medium text-slate-700">
                                            {latestAssessment.assessment_date?.split('T')[0]}
                                        </span>
                                        {latestAssessment.is_stale && (
                                            <span className="ml-2 px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full font-medium">
                                                Stale ({'>'}90 days)
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-sm">
                                        Source: <span className="font-medium">{latestAssessment.source}</span>
                                    </div>
                                </div>

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

                            {/* Integration Status */}
                            <Card title="Integration Status">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="p-4 bg-slate-50 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-slate-700">IAR Upload</span>
                                            <span className={`px-2 py-1 text-xs rounded-full font-medium ${
                                                latestAssessment.iar_status === 'uploaded'
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
                                            <span className={`px-2 py-1 text-xs rounded-full font-medium ${
                                                latestAssessment.chris_status === 'synced'
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
                                    onClick={() => navigate(`/interrai/complete/${id}`)}
                                >
                                    Start InterRAI HC Assessment
                                </Button>
                            </div>
                        </Card>
                    )}
                </div>
            )}
        </div>
    );
};

export default PatientDetailPage;
