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
                api.get('/api/v2/dashboards/spo'),
                api.get('/api/v2/staffing/fte')
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

    if (loading) {
        return (
            <div className="flex items-center justify-center h-screen">
                <div className="w-8 h-8 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
            </div>
        );
    }

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

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
                <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Appendix 1 Compliance Scorecard</h3>
                <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                    {/* Referral Acceptance */}
                    <div 
                        className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors"
                        onClick={() => navigate('/tnp')}
                    >
                        <div className="absolute top-0 right-0 w-16 h-16 bg-amber-50 rounded-bl-full -mr-8 -mt-8"></div>
                        <div className="relative z-10">
                            <div className="text-slate-500 text-xs font-bold uppercase mb-1">Referral Acceptance</div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-3xl font-bold text-amber-500">98.5%</span>
                                <span className="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-700">Band B</span>
                            </div>
                            <div className="text-xs text-slate-400 mt-2">Target: 100% (Needs Improvement)</div>
                        </div>
                    </div>

                    {/* Time-to-First-Service */}
                    <div 
                        className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors"
                        onClick={() => navigate('/tnp')}
                    >
                        <div className="absolute top-0 right-0 w-16 h-16 bg-emerald-50 rounded-bl-full -mr-8 -mt-8"></div>
                        <div className="relative z-10">
                            <div className="text-slate-500 text-xs font-bold uppercase mb-1">Time-to-First-Service</div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-3xl font-bold text-emerald-600">18.2h</span>
                                <span className="px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Band A</span>
                            </div>
                            <div className="text-xs text-slate-400 mt-2">Target: &lt; 24 Hours</div>
                        </div>
                    </div>

                    {/* Missed Care */}
                    <div 
                        className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors"
                        onClick={() => setIsMissedCareModalOpen(true)}
                    >
                        <div className="absolute top-0 right-0 w-16 h-16 bg-emerald-50 rounded-bl-full -mr-8 -mt-8"></div>
                        <div className="relative z-10">
                            <div className="text-slate-500 text-xs font-bold uppercase mb-1">Missed Care Rate</div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-3xl font-bold text-emerald-600">0.02%</span>
                                <span className="px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Band A</span>
                            </div>
                            <div className="text-xs text-slate-400 mt-2">Target: 0%</div>
                        </div>
                    </div>

                    {/* FTE Compliance (New) */}
                    <div 
                        className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 relative overflow-hidden cursor-pointer hover:bg-slate-50 transition-colors"
                        onClick={() => navigate('/staff')}
                    >
                        <div className={`absolute top-0 right-0 w-16 h-16 rounded-bl-full -mr-8 -mt-8 ${
                            fteData?.band === 'GREEN' ? 'bg-emerald-50' : fteData?.band === 'YELLOW' ? 'bg-amber-50' : 'bg-rose-50'
                        }`}></div>
                        <div className="relative z-10">
                            <div className="text-slate-500 text-xs font-bold uppercase mb-1">Direct Care FTE</div>
                            <div className="flex items-baseline gap-2">
                                <span className={`text-3xl font-bold ${
                                    fteData?.band === 'GREEN' ? 'text-emerald-600' : fteData?.band === 'YELLOW' ? 'text-amber-500' : 'text-rose-600'
                                }`}>{fteData?.fte_ratio || 0}%</span>
                                <span className={`px-2 py-0.5 rounded text-xs font-bold ${
                                    fteData?.band === 'GREEN' ? 'bg-emerald-100 text-emerald-700' : fteData?.band === 'YELLOW' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'
                                }`}>{fteData?.band || 'N/A'}</span>
                            </div>
                            <div className="text-xs text-slate-400 mt-2">Target: &ge; 80% Full-Time</div>
                        </div>
                    </div>

                    {/* Active QINs */}
                    <div className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-l-rose-500 border-y border-r border-slate-200 cursor-pointer hover:bg-rose-50 transition-colors" onClick={() => navigate('/qin')}>
                        <div className="flex justify-between items-start">
                            <div>
                                <div className="text-slate-500 text-xs font-bold uppercase mb-1">Active QINs</div>
                                <div className="text-3xl font-bold text-rose-600">1</div>
                            </div>
                            <svg className="w-6 h-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div className="text-xs text-rose-700 font-medium mt-2">Action Required: Submit QIP</div>
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
                            <span className="bg-amber-100 text-amber-800 text-xs font-bold px-2 py-1 rounded-full">
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
                                            <button onClick={() => navigate('/tnp')} className="text-teal-600 hover:text-teal-800 text-sm font-medium">Review</button>
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
                            <span className={`${data?.jeopardy_board?.length > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'} text-xs font-bold px-2 py-1 rounded-full`}>
                                {data?.jeopardy_board?.length || 0} Active
                            </span>
                        </div>
                        <div className="p-4 space-y-3">
                            {data?.jeopardy_board?.length > 0 ? (
                                data.jeopardy_board.map((risk, idx) => (
                                    <div key={idx} className={`flex items-center justify-between p-3 border rounded-lg ${
                                        risk.risk_level === 'CRITICAL' ? 'bg-rose-50 border-rose-100' : 'bg-amber-50 border-amber-100'
                                    }`}>
                                        <div className="flex items-center gap-3">
                                            <div className={`w-2 h-2 rounded-full ${risk.risk_level === 'CRITICAL' ? 'bg-rose-500 animate-pulse' : 'bg-amber-500'}`}></div>
                                            <div>
                                                <div className="text-sm font-bold text-slate-900">{risk.reason || 'Service Risk Detected'}</div>
                                                <div className="text-xs text-slate-500">
                                                    {risk.care_assignment?.assigned_user?.name || 'Unassigned'} • {risk.patient?.user?.name || 'Unknown'} • 
                                                    {risk.risk_level === 'CRITICAL' ? ` Breached ${risk.breach_duration}` : ` Ends in ${risk.time_remaining}`}
                                                </div>
                                            </div>
                                        </div>
                                        <button className={`text-xs bg-white border px-3 py-1 rounded transition-colors ${
                                            risk.risk_level === 'CRITICAL' ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-amber-200 text-amber-700 hover:bg-amber-50'
                                        }`}>
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
                            <button onClick={() => navigate('/sspo-marketplace')} className="text-xs font-bold text-teal-600 hover:text-teal-800 uppercase tracking-wide">View Marketplace</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CareDashboardPage;
