import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Button from '../../components/UI/Button';

/**
 * TnpReviewDetailPage - Transition Needs Profile review with InterRAI HC integration
 *
 * Per IR-004: Extended to show InterRAI assessment data including:
 * - Assessment status (current, stale, missing)
 * - MAPLe, CPS, ADL, CHESS scores
 * - Link to complete assessment if needed
 */
const TnpReviewDetailPage = () => {
    const { patientId } = useParams();
    const navigate = useNavigate();
    const [tnp, setTnp] = useState(null);
    const [interraiData, setInterraiData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [analyzing, setAnalyzing] = useState(false);
    const [activeTab, setActiveTab] = useState('clinical'); // clinical | interrai | ai

    const fetchData = useCallback(async () => {
        try {
            setLoading(true);
            // Fetch TNP and InterRAI status in parallel
            const [tnpResponse, interraiResponse] = await Promise.all([
                axios.get(`/api/patients/${patientId}/tnp`).catch(() => ({ data: null })),
                axios.get(`/api/v2/interrai/patients/${patientId}/status`).catch(() => ({ data: { data: null } })),
            ]);

            setTnp(tnpResponse.data);
            setInterraiData(interraiResponse.data?.data || null);
        } catch (error) {
            console.error('Failed to fetch data:', error);
            setError(error.message || 'An error occurred');
        } finally {
            setLoading(false);
        }
    }, [patientId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const handleAnalyze = async () => {
        if (!tnp) return;
        setAnalyzing(true);
        try {
            await axios.post(`/api/tnp/${tnp.id}/analyze`);
            // Poll or just reload for now
            alert('Analysis queued!'); 
            window.location.reload();
        } catch (error) {
            console.error('Analysis failed:', error);
            alert('Failed to start analysis.');
        } finally {
            setAnalyzing(false);
        }
    };

    // Helper to get score color based on severity
    const getScoreColor = (score, maxScore, reverse = false) => {
        if (score === null || score === undefined) return 'text-slate-400';
        const ratio = score / maxScore;
        const adjusted = reverse ? 1 - ratio : ratio;
        if (adjusted >= 0.7) return 'text-rose-600';
        if (adjusted >= 0.4) return 'text-amber-600';
        return 'text-emerald-600';
    };

    // Helper to get MAPLe color
    const getMapleColor = (score) => {
        const colors = {
            '1': 'text-emerald-600',
            '2': 'text-emerald-500',
            '3': 'text-amber-500',
            '4': 'text-orange-500',
            '5': 'text-rose-600',
        };
        return colors[score] || 'text-slate-400';
    };

    if (loading) return <div className="p-12 text-center">Loading...</div>;
    if (error) return <div className="p-12 text-center text-red-600">Error: {error}</div>;

    const latestAssessment = interraiData?.latest_assessment;
    const requiresAssessment = interraiData?.requires_assessment;

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Transition Needs Profile</h1>
                    {interraiData?.patient_name && (
                        <p className="text-slate-500">{interraiData.patient_name}</p>
                    )}
                </div>
                <Button
                    className="bg-teal-600 hover:bg-teal-700 text-white"
                    onClick={() => navigate(`/care-bundles/create/${patientId}`)}
                >
                    Proceed to Care Delivery Plan
                </Button>
            </div>

            {/* Tab Navigation */}
            <div className="border-b border-slate-200">
                <nav className="-mb-px flex gap-6">
                    {[
                        { id: 'clinical', label: 'Clinical Context' },
                        { id: 'interrai', label: 'InterRAI HC', badge: requiresAssessment ? 'Action Needed' : null },
                        { id: 'ai', label: 'AI Analysis' },
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

            {!tnp && activeTab === 'clinical' ? (
                <Section>
                    <p>No TNP profile found for this patient.</p>
                    <Button variant="secondary" className="mt-4" onClick={() => alert('Create TNP Flow (Mock)')}>+ Create TNP</Button>
                </Section>
            ) : (
                <>
                    {/* Clinical Context Tab */}
                    {activeTab === 'clinical' && tnp && (
                        <Section title="Clinical Context">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Card title="Narrative">
                                    <p className="whitespace-pre-wrap">{tnp.narrative_summary || 'No narrative provided.'}</p>
                                </Card>
                                <Card title="Clinical Flags">
                                    {tnp.clinical_flags && tnp.clinical_flags.length > 0 ? (
                                        <ul className="list-disc list-inside">
                                            {tnp.clinical_flags.map((flag, index) => (
                                                <li key={index}>{flag}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p>No flags identified.</p>
                                    )}
                                </Card>
                            </div>
                        </Section>
                    )}

                    {/* InterRAI HC Tab */}
                    {activeTab === 'interrai' && (
                        <div className="space-y-6">
                            {/* Assessment Status Banner */}
                            {requiresAssessment && (
                                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                    <div className="flex items-start gap-3">
                                        <svg className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <div className="flex-1">
                                            <p className="font-medium text-amber-800">InterRAI Assessment Required</p>
                                            <p className="text-sm text-amber-700 mt-1">{interraiData?.message}</p>
                                            <Button
                                                size="sm"
                                                className="mt-3"
                                                onClick={() => navigate(`/interrai/complete/${patientId}`)}
                                            >
                                                Complete InterRAI HC Assessment
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Current Assessment Scores */}
                            {latestAssessment ? (
                                <>
                                    <Section title="Current InterRAI HC Assessment">
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
                                            {/* MAPLe Score */}
                                            <div className="bg-white border border-slate-200 rounded-lg p-4 text-center">
                                                <div className="text-xs font-semibold text-slate-500 uppercase mb-1">MAPLe</div>
                                                <div className={`text-3xl font-bold ${getMapleColor(latestAssessment.maple_score)}`}>
                                                    {latestAssessment.maple_score || '-'}
                                                </div>
                                                <div className="text-xs text-slate-500 mt-1">
                                                    {latestAssessment.maple_description || 'Priority Level'}
                                                </div>
                                            </div>

                                            {/* CPS Score */}
                                            <div className="bg-white border border-slate-200 rounded-lg p-4 text-center">
                                                <div className="text-xs font-semibold text-slate-500 uppercase mb-1">CPS</div>
                                                <div className={`text-3xl font-bold ${getScoreColor(latestAssessment.cps, 6)}`}>
                                                    {latestAssessment.cps ?? '-'}
                                                </div>
                                                <div className="text-xs text-slate-500 mt-1">
                                                    {latestAssessment.cps_description || 'Cognitive Performance'}
                                                </div>
                                            </div>

                                            {/* ADL Hierarchy */}
                                            <div className="bg-white border border-slate-200 rounded-lg p-4 text-center">
                                                <div className="text-xs font-semibold text-slate-500 uppercase mb-1">ADL</div>
                                                <div className={`text-3xl font-bold ${getScoreColor(latestAssessment.adl_hierarchy, 6)}`}>
                                                    {latestAssessment.adl_hierarchy ?? '-'}
                                                </div>
                                                <div className="text-xs text-slate-500 mt-1">
                                                    {latestAssessment.adl_description || 'Daily Living'}
                                                </div>
                                            </div>

                                            {/* CHESS Score */}
                                            <div className="bg-white border border-slate-200 rounded-lg p-4 text-center">
                                                <div className="text-xs font-semibold text-slate-500 uppercase mb-1">CHESS</div>
                                                <div className={`text-3xl font-bold ${getScoreColor(latestAssessment.chess_score, 5)}`}>
                                                    {latestAssessment.chess_score ?? '-'}
                                                </div>
                                                <div className="text-xs text-slate-500 mt-1">Health Instability</div>
                                            </div>
                                        </div>
                                    </Section>

                                    {/* Risk Flags */}
                                    {latestAssessment.high_risk_flags && latestAssessment.high_risk_flags.length > 0 && (
                                        <Section title="Risk Flags">
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
                                        </Section>
                                    )}

                                    {/* IAR/CHRIS Status */}
                                    <Section title="Integration Status">
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
                                    </Section>
                                </>
                            ) : (
                                <Section>
                                    <div className="text-center py-8">
                                        <svg className="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p className="text-slate-500 font-medium">No InterRAI HC Assessment on File</p>
                                        <p className="text-sm text-slate-400 mt-1">Complete an assessment to view clinical scores</p>
                                        <Button
                                            className="mt-4"
                                            onClick={() => navigate(`/interrai/complete/${patientId}`)}
                                        >
                                            Start InterRAI HC Assessment
                                        </Button>
                                    </div>
                                </Section>
                            )}
                        </div>
                    )}

                    {/* AI Analysis Tab */}
                    {activeTab === 'ai' && tnp && (
                        <Section title="AI Analysis">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-medium">Gemini Insights</h3>
                                <Button onClick={handleAnalyze} disabled={analyzing}>
                                    {analyzing ? 'Analyzing...' : 'Run AI Analysis'}
                                </Button>
                            </div>

                            <Card>
                                <div className="mb-4">
                                    <span className="font-semibold">Status: </span>
                                    <span className={`capitalize ${tnp.ai_summary_status === 'completed' ? 'text-green-600' : 'text-yellow-600'}`}>
                                        {tnp.ai_summary_status}
                                    </span>
                                </div>
                                {tnp.ai_summary_text && (
                                    <div className="prose max-w-none">
                                        <h4 className="text-md font-semibold mb-2">SBAR Summary</h4>
                                        <p>{tnp.ai_summary_text}</p>
                                    </div>
                                )}
                            </Card>
                        </Section>
                    )}

                    {activeTab === 'ai' && !tnp && (
                        <Section>
                            <p className="text-slate-500">Create a TNP profile first to enable AI analysis.</p>
                        </Section>
                    )}
                </>
            )}
        </div>
    );
};

export default TnpReviewDetailPage;
