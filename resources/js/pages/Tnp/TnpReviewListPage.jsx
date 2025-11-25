import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import DataTable from '../../components/UI/DataTable';
import Section from '../../components/UI/Section';
import Spinner from '../../components/UI/Spinner';
import ReferralTimer from '../../components/Intake/ReferralTimer';

const TnpReviewListPage = () => {
    const [patients, setPatients] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchPatients = async () => {
            try {
                const response = await axios.get('/api/patients');
                // Filter for Intake Queue: only new referrals or TNP in progress
                const intakePatients = (response.data.data || []).filter(p => 
                    ['referral_received', 'tnp_in_progress'].includes(p.status) || !p.status // Fallback if null
                );
                setPatients(intakePatients);
            } catch (error) {
                console.error('Failed to fetch patients:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchPatients();
    }, []);

    const columns = [
        {
            header: 'Patient Name',
            render: (row) => (
                <div>
                    <div className="font-medium text-slate-900">{row.user?.name || 'Unknown'}</div>
                    <div className="text-xs text-slate-500">OHIP: {row.ohip || 'N/A'}</div>
                </div>
            ),
        },
        {
            header: 'Intake SLA (15m)',
            render: (row) => (
                // Use created_at or fallback to now-10min for demo if missing
                <ReferralTimer receivedAt={row.created_at || new Date(Date.now() - 1000 * 60 * 10).toISOString()} />
            ),
        },
        {
            header: 'Status',
            render: (row) => (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {row.status || 'New Referral'}
                </span>
            ),
        },
        {
            header: 'Action',
            render: (row) => (
                <Link 
                    to={`/tnp/${row.id}`} 
                    className="font-medium text-teal-600 hover:text-teal-800 text-sm border border-teal-200 bg-teal-50 px-3 py-1 rounded-md transition-colors"
                >
                    Review TNP
                </Link>
            ),
        },
    ];

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;

    return (
        <div className="space-y-6">
            {/* Intake Performance Scorecard (Appendix 1) */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Referral Acceptance</div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-2xl font-bold text-emerald-600">98.5%</span>
                        <span className="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Band A</span>
                    </div>
                    <div className="text-xs text-slate-500 mt-2">Target: 100% (Last 30 Days)</div>
                </div>
                
                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Time-to-First-Service</div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-2xl font-bold text-emerald-600">18.2h</span>
                        <span className="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Band A</span>
                    </div>
                    <div className="text-xs text-slate-500 mt-2">Target: &lt; 24 Hours</div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pending Acceptance</div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-2xl font-bold text-amber-500">3</span>
                        <span className="text-xs text-slate-500">Referrals</span>
                    </div>
                    <div className="text-xs text-amber-600 mt-2 font-medium">Action Required &lt; 10m</div>
                </div>
            </div>

            <Section title="Intake Queue & Transition Reviews">
                <DataTable 
                    columns={columns} 
                    data={patients} 
                    keyField="id"
                />
            </Section>
        </div>
    );
};

export default TnpReviewListPage;
