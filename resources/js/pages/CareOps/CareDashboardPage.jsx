import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import { useNavigate } from 'react-router-dom';
import KpiCard from '../../components/dashboard/KpiCard';
import PartnerPerformanceTable from '../../components/dashboard/PartnerPerformanceTable';
import MissedCareModal from '../../components/dashboard/MissedCareModal';
import QualityMetricsTab from '../../components/dashboard/QualityMetricsTab';
import ReferralTimer from '../../components/Intake/ReferralTimer';

const CareDashboardPage = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState(null);
    const [fteData, setFteData] = useState(null);
    const [forecastLoading, setForecastLoading] = useState(false);
    const [isMissedCareModalOpen, setIsMissedCareModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('operations'); // 'operations' | 'partners' | 'compliance'

    useEffect(() => {
        fetchDashboardData();
        const intervalId = setInterval(() => fetchDashboardData(true), 60000);
        return () => clearInterval(intervalId);
    }, []);

    const fetchDashboardData = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const [dashboardRes, fteRes] = await Promise.all([
                api.get('/v2/dashboards/spo'),
                api.get('/v2/staffing/fte')
            ]);
            setData(dashboardRes.data);
            setFteData(fteRes.data);
        } catch (error) {
            console.error('Failed to fetch dashboard data', error);
        } finally {
            if (!silent) setLoading(false);
        }
    };

    const handleRunForecast = async () => {
        setForecastLoading(true);
        setTimeout(() => setForecastLoading(false), 2000); // Mock delay
    };

    const handleResolveAlert = async (alertId) => {
        try {
            await api.post(`/v2/jeopardy/alerts/${alertId}/resolve`);
            // Refresh dashboard data to reflect the resolved alert
            fetchDashboardData(true);
        } catch (error) {
            console.error('Failed to resolve alert', error);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-screen">
                <div className="w-8 h-8 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
            </div>
        );
    }

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">

            {/* HEADER */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SPO Command Center</h1>
                    <p className="text-slate-500 text-sm">High Intensity Bundled Care • Contract Status: <span className="text-emerald-600 font-bold">Active</span></p>
                </div>
                <button
                    onClick={handleRunForecast}
                    disabled={forecastLoading}
                    className="bg-white border border-indigo-200 hover:border-indigo-400 text-indigo-600 px-4 py-2 rounded-lg shadow-sm flex items-center gap-2 transition-all text-sm font-medium group disabled:opacity-50"
                >
                    <svg className={`w-4 h-4 ${forecastLoading ? 'animate-spin' : 'group-hover:animate-pulse'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {forecastLoading ? (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        ) : (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        )}
                    </svg>
                    {forecastLoading ? 'Forecasting...' : 'Gemini: Run Capacity Forecast'}
                </button>
            </div>

            {/* APPENDIX 1 COMPLIANCE SCORECARD */}
            <div>
                <h3 className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Appendix 1 Compliance Scorecard</h3>
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    {/* Referral Acceptance */}
                    <div 
                        className={`bg-white p-3 rounded-lg shadow-sm ${(data?.kpi?.referral_acceptance?.band ?? 'B') === 'C' ? 'border-l-4 border-l-rose-500 border-y border-r border-slate-200' : (data?.kpi?.referral_acceptance?.band ?? 'B') === 'B' ? 'border-l-4 border-l-amber-500 border-y border-r border-slate-200' : 'border border-slate-200'} relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors`}
                        onClick={() => navigate('/patients')}
                    >
                        <div className={`absolute top-0 right-0 w-8 h-8 ${(data?.kpi?.referral_acceptance?.band ?? 'B') === 'A' ? 'bg-emerald-50' : (data?.kpi?.referral_acceptance?.band ?? 'B') === 'B' ? 'bg-amber-50' : 'bg-rose-50'} rounded-bl-full -mr-4 -mt-4`}></div>
                        <div className="relative z-10 flex flex-col items-center text-center">
                            <div className="text-slate-500 text-xs font-medium uppercase h-7 flex items-center">Referral Acceptance</div>
                            <div className="text-2xl font-bold ${(data?.kpi?.referral_acceptance?.band ?? 'B') === 'A' ? 'text-emerald-600' : (data?.kpi?.referral_acceptance?.band ?? 'B') === 'B' ? 'text-amber-500' : 'text-rose-600'} h-7 flex items-center">{`${data?.kpi?.referral_acceptance?.rate_percent?.toFixed(1) ?? 0}%`}</div>
                            <div className="h-7 flex items-center justify-center">
                                <span className={`shrink-0 whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium ${(data?.kpi?.referral_acceptance?.band ?? 'B') === 'A' ? 'bg-emerald-100 text-emerald-700' : (data?.kpi?.referral_acceptance?.band ?? 'B') === 'B' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'}`}>{(data?.kpi?.referral_acceptance?.band ?? 'B') === 'A' ? 'Meets Target' : (data?.kpi?.referral_acceptance?.band ?? 'B') === 'B' ? 'Below Standard' : 'Action Required'}</span>
                            </div>
                            <div className="text-xs text-slate-400 h-7 flex items-center justify-center">{(data?.kpi?.referral_acceptance?.band ?? 'B') === 'A' ? 'Target: 100% (Meets Target)' : (data?.kpi?.referral_acceptance?.band ?? 'B') === 'B' ? `${data?.kpi?.referral_acceptance?.accepted ?? 0}/${data?.kpi?.referral_acceptance?.total ?? 0} accepted (Target: 100%)` : `${data?.kpi?.referral_acceptance?.accepted ?? 0}/${data?.kpi?.referral_acceptance?.total ?? 0} accepted (Action Required)`}</div>
                        </div>
                    </div>

                    {/* Time-to-First-Service */}
                    <div 
                        className={`bg-white p-3 rounded-lg shadow-sm ${data?.kpi?.time_to_first_service?.band === 'C' ? 'border-l-4 border-l-rose-500 border-y border-r border-slate-200' : data?.kpi?.time_to_first_service?.band === 'B' ? 'border-l-4 border-l-amber-500 border-y border-r border-slate-200' : 'border border-slate-200'} relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors`}
                        onClick={() => navigate('/metrics/tfs')}
                    >
                        <div className={`absolute top-0 right-0 w-8 h-8 ${data?.kpi?.time_to_first_service?.band === 'A' ? 'bg-emerald-50' : data?.kpi?.time_to_first_service?.band === 'B' ? 'bg-amber-50' : 'bg-rose-50'} rounded-bl-full -mr-4 -mt-4`}></div>
                        <div className="relative z-10 flex flex-col items-center text-center">
                            <div className="text-slate-500 text-xs font-medium uppercase h-7 flex items-center">Time-to-First-Service</div>
                            <div className={`text-2xl font-bold h-7 flex items-center ${data?.kpi?.time_to_first_service?.band === 'A' ? 'text-emerald-600' : data?.kpi?.time_to_first_service?.band === 'B' ? 'text-amber-500' : 'text-rose-600'}`}>{data?.kpi?.time_to_first_service?.formatted_average ?? '0h'}</div>
                            <div className="h-7 flex items-center justify-center">
                                <span className={`shrink-0 whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium ${data?.kpi?.time_to_first_service?.band === 'A' ? 'bg-emerald-100 text-emerald-700' : data?.kpi?.time_to_first_service?.band === 'B' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'}`}>{data?.kpi?.time_to_first_service?.band === 'A' ? 'Meets Target' : data?.kpi?.time_to_first_service?.band === 'B' ? 'Below Standard' : 'Action Required'}</span>
                            </div>
                            <div className="text-xs text-slate-400 h-7 flex items-center justify-center">Target: &lt; 24 Hours</div>
                        </div>
                    </div>

                    {/* Missed Care */}
                    <div
                        className={`bg-white p-3 rounded-lg shadow-sm ${data?.kpi?.missed_care?.band === 'A' ? 'border border-slate-200' : data?.kpi?.missed_care?.band === 'B' ? 'border-l-4 border-l-amber-500 border-y border-r border-slate-200' : 'border-l-4 border-l-rose-500 border-y border-r border-slate-200'} relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors`}
                        onClick={() => setIsMissedCareModalOpen(true)}
                    >
                        <div className={`absolute top-0 right-0 w-8 h-8 rounded-bl-full -mr-4 -mt-4 ${
                            data?.kpi?.missed_care?.band === 'A' ? 'bg-emerald-50' :
                            data?.kpi?.missed_care?.band === 'B' ? 'bg-amber-50' : 'bg-rose-50'
                        }`}></div>
                        <div className="relative z-10 flex flex-col items-center text-center">
                            <div className="text-slate-500 text-xs font-medium uppercase h-7 flex items-center">Missed Care Rate</div>
                            <div className={`text-2xl font-bold h-7 flex items-center ${
                                data?.kpi?.missed_care?.band === 'A' ? 'text-emerald-600' :
                                data?.kpi?.missed_care?.band === 'B' ? 'text-amber-500' : 'text-rose-600'
                            }`}>{data?.kpi?.missed_care?.rate_percent?.toFixed(2) ?? '0.00'}%</div>
                            <div className="h-7 flex items-center justify-center">
                                <span className={`shrink-0 whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium ${
                                    data?.kpi?.missed_care?.band === 'A' ? 'bg-emerald-100 text-emerald-700' :
                                    data?.kpi?.missed_care?.band === 'B' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'
                                }`}>Action Required</span>
                            </div>
                            <div className="text-xs text-slate-400 h-7 flex items-center justify-center">
                                Target: 0% {data?.kpi?.missed_care?.missed_events > 0 && `• ${data?.kpi?.missed_care?.missed_events} missed`}
                            </div>
                        </div>
                    </div>

                    {/* FTE Compliance */}
                    <div 
                        className={`bg-white p-3 rounded-lg shadow-sm ${fteData?.band === 'RED' ? 'border-l-4 border-l-rose-500 border-y border-r border-slate-200' : fteData?.band === 'YELLOW' ? 'border-l-4 border-l-amber-500 border-y border-r border-slate-200' : 'border border-slate-200'} relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors`}
                        onClick={() => navigate('/staff')}
                    >
                        <div className={`absolute top-0 right-0 w-8 h-8 rounded-bl-full -mr-4 -mt-4 ${
                            fteData?.band === 'GREEN' ? 'bg-emerald-50' : fteData?.band === 'YELLOW' ? 'bg-amber-50' : 'bg-rose-50'
                        }`}></div>
                        <div className="relative z-10 flex flex-col items-center text-center">
                            <div className="text-slate-500 text-xs font-medium uppercase h-7 flex items-center">Direct Care FTE</div>
                            <div className={`text-2xl font-bold h-7 flex items-center ${
                                fteData?.band === 'GREEN' ? 'text-emerald-600' : fteData?.band === 'YELLOW' ? 'text-amber-500' : 'text-rose-600'
                            }`}>{fteData?.fte_ratio || 0}%</div>
                            <div className="h-7 flex items-center justify-center">
                                <span className={`shrink-0 whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium ${
                                    fteData?.band === 'GREEN' ? 'bg-emerald-100 text-emerald-700' : fteData?.band === 'YELLOW' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'
                                }`}>{fteData?.band === 'GREEN' ? 'Meets Target' : fteData?.band === 'YELLOW' ? 'Below Standard' : 'Action Required'}</span>
                            </div>
                            <div className="text-xs text-slate-400 h-7 flex items-center justify-center">Target: ≥ 80% Full-Time</div>
                        </div>
                    </div>

                    {/* Active QINs */}
                    <div 
                        className={`bg-white p-3 rounded-lg shadow-sm ${(data?.kpi?.active_qins?.count ?? 0) === 0 ? 'border border-slate-200' : 'border-l-4 border-l-rose-500 border-y border-r border-slate-200'} relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors`}
                        onClick={() => navigate('/qin')}
                    >
                        <div className={`absolute top-0 right-0 w-8 h-8 rounded-bl-full -mr-4 -mt-4 ${
                            (data?.kpi?.active_qins?.count ?? 0) === 0 
                                ? 'bg-emerald-50' 
                                : 'bg-rose-50'
                        }`}></div>
                        <div className="relative z-10 flex flex-col items-center text-center">
                            <div className="text-slate-500 text-xs font-medium uppercase h-7 flex items-center">Active QINs</div>
                            <div className={`text-2xl font-bold h-7 flex items-center ${
                                (data?.kpi?.active_qins?.count ?? 0) === 0 
                                    ? 'text-emerald-600' 
                                    : 'text-rose-600'
                            }`}>{data?.kpi?.active_qins?.count ?? 0}</div>
                            <div className="h-7 flex items-center justify-center">
                                <span className={`shrink-0 whitespace-nowrap px-2 py-0.5 rounded text-xs font-medium ${
                                    (data?.kpi?.active_qins?.count ?? 0) === 0 
                                        ? 'bg-emerald-100 text-emerald-700' 
                                        : 'bg-rose-100 text-rose-700'
                                }`}>{(data?.kpi?.active_qins?.count ?? 0) === 0 ? 'Meets Target' : 'Action Required'}</span>
                            </div>
                            <div className={`text-xs h-7 flex items-center justify-center ${
                                (data?.kpi?.active_qins?.count ?? 0) === 0 
                                    ? 'text-slate-400' 
                                    : 'text-rose-600 font-medium'
                            }`}>{(data?.kpi?.active_qins?.count ?? 0) === 0 ? 'Target: 0 Active' : 'Submit QIP'}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* LEFT COLUMN: OPERATIONAL QUEUES */}
                <div className="lg:col-span-2 space-y-8">
                    
                    {/* INTAKE QUEUE */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                            <h3 className="font-bold text-slate-800">Intake Queue (HPG Gateway)</h3>
                            <span className="bg-amber-100 text-amber-800 text-xs font-medium px-2 py-1 rounded-full">
                                {data?.intake_queue?.length || 0} Pending
                            </span>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {data?.intake_queue?.length > 0 ? (
                                data.intake_queue.map((p) => (
                                    <div key={p.id} className="p-4 hover:bg-slate-50 transition-colors flex justify-between items-center">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-500">
                                                {p.name.charAt(0)}
                                            </div>
                                            <div>
                                                <div className="font-medium text-slate-900">{p.name}</div>
                                                <div className="text-xs text-slate-500">OHIP: {p.ohip} • {p.source}</div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <ReferralTimer receivedAt={p.received_at} />
                                            <button onClick={() => navigate('/patients')} className="text-teal-600 hover:text-teal-800 text-sm font-medium">Review</button>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="p-4 text-center text-slate-500 text-sm">No new referrals.</div>
                            )}
                        </div>
                    </div>

                    {/* JEOPARDY BOARD */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
                         <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                            <h3 className="font-bold text-slate-800">Jeopardy Board (Missed Care Risk)</h3>
                            <div className="flex items-center gap-2">
                                {data?.jeopardy_summary?.critical_count > 0 && (
                                    <span className="bg-rose-100 text-rose-800 text-xs font-medium px-2 py-1 rounded-full">
                                        {data.jeopardy_summary.critical_count} Critical
                                    </span>
                                )}
                                {data?.jeopardy_summary?.warning_count > 0 && (
                                    <span className="bg-amber-100 text-amber-800 text-xs font-medium px-2 py-1 rounded-full">
                                        {data.jeopardy_summary.warning_count} Warning
                                    </span>
                                )}
                                {(data?.jeopardy_summary?.total_active || 0) === 0 && (
                                    <span className="bg-slate-100 text-slate-600 text-xs font-medium px-2 py-1 rounded-full">
                                        0 Active
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="p-4 space-y-3 max-h-96 overflow-y-auto">
                            {data?.jeopardy_board?.length > 0 ? (
                                data.jeopardy_board.slice(0, 10).map((risk) => (
                                    <div key={risk.id || risk.assignment_id} className={`flex items-center justify-between p-3 border rounded-lg ${
                                        risk.risk_level === 'CRITICAL' ? 'bg-rose-50 border-rose-100' : 'bg-amber-50 border-amber-100'
                                    }`}>
                                        <div className="flex items-center gap-3">
                                            <div className={`w-2 h-2 rounded-full ${risk.risk_level === 'CRITICAL' ? 'bg-rose-500 animate-pulse' : 'bg-amber-500'}`}></div>
                                            <div>
                                                <div className="text-sm font-bold text-slate-900">{risk.reason || 'Visit Verification Overdue'}</div>
                                                <div className="text-xs text-slate-500">
                                                    {risk.care_assignment?.assigned_user?.name || 'Unassigned'} • {risk.patient?.user?.name || 'Unknown'}
                                                    {risk.risk_level === 'CRITICAL'
                                                        ? ` • Breached ${risk.breach_duration || risk.breached_days_ago + 'd ago'}`
                                                        : ` • ${risk.time_remaining} remaining`}
                                                </div>
                                                {risk.service_type && (
                                                    <div className="text-xs text-slate-400 mt-0.5">{risk.service_type}</div>
                                                )}
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => handleResolveAlert(risk.assignment_id || risk.id)}
                                            className={`text-xs bg-white border px-3 py-1 rounded transition-colors ${
                                                risk.risk_level === 'CRITICAL' ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-amber-200 text-amber-700 hover:bg-amber-50'
                                            }`}
                                        >
                                            Resolve
                                        </button>
                                    </div>
                                ))
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-slate-400">
                                    <svg className="w-8 h-8 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span className="text-sm">No visits at risk. Great job!</span>
                                </div>
                            )}
                            {data?.jeopardy_board?.length > 10 && (
                                <div className="text-center pt-2">
                                    <span className="text-xs text-slate-500">
                                        Showing 10 of {data.jeopardy_board.length} alerts
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                </div>

                {/* RIGHT COLUMN: PARTNERS & FORECAST */}
                <div className="space-y-8">
                    {/* PARTNER TABLE (Condensed) */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-100">
                            <h3 className="font-bold text-slate-800">SSPO Performance</h3>
                        </div>
                        <div className="p-4">
                            <PartnerPerformanceTable partners={data?.partners || []} compact={true} />
                        </div>
                        <div className="px-6 py-3 bg-slate-50 border-t border-slate-100 text-center">
                            <button onClick={() => navigate('/sspo-marketplace')} className="text-xs font-medium text-teal-600 hover:text-teal-800 uppercase tracking-wide">View Marketplace</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CareDashboardPage;
