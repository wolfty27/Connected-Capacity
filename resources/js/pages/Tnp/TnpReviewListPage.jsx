import React, { useEffect, useState, useMemo } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import DataTable from '../../components/UI/DataTable';
import Section from '../../components/UI/Section';
import Spinner from '../../components/UI/Spinner';
import ReferralTimer from '../../components/Intake/ReferralTimer';

const TnpReviewListPage = () => {
    const [patients, setPatients] = useState([]);
    const [allPatients, setAllPatients] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchPatients = async () => {
            try {
                const response = await axios.get('/api/patients');
                const data = response.data.data || [];
                setAllPatients(data);

                // Filter for Intake Queue: patients in queue with intake/TNP statuses
                const intakePatients = data.filter(p =>
                    p.is_in_queue &&
                    ['pending_intake', 'triage_in_progress', 'triage_complete', 'tnp_in_progress', 'tnp_complete'].includes(p.queue_status)
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

    // Calculate dynamic stats from patient data
    const stats = useMemo(() => {
        const queuePatients = allPatients.filter(p => p.is_in_queue);
        const pendingIntake = queuePatients.filter(p =>
            ['pending_intake', 'triage_in_progress'].includes(p.queue_status)
        ).length;
        const tnpComplete = queuePatients.filter(p => p.queue_status === 'tnp_complete').length;
        const activePatients = allPatients.filter(p => !p.is_in_queue && p.status === 'Active').length;

        return {
            pendingAcceptance: pendingIntake,
            readyForBundle: tnpComplete,
            totalInQueue: queuePatients.length,
            activePatients: activePatients,
        };
    }, [allPatients]);

    // Queue status colors for badges
    const statusColors = {
        pending_intake: 'bg-gray-100 text-gray-700',
        triage_in_progress: 'bg-yellow-100 text-yellow-700',
        triage_complete: 'bg-blue-100 text-blue-700',
        tnp_in_progress: 'bg-yellow-100 text-yellow-700',
        tnp_complete: 'bg-green-100 text-green-700',
    };

    const columns = [
        {
            header: 'Patient Name',
            render: (row) => (
                <div>
                    <div className="font-medium text-slate-900">{row.name || 'Unknown'}</div>
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
            header: 'Queue Status',
            render: (row) => (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[row.queue_status] || 'bg-gray-100 text-gray-700'}`}>
                    {row.queue_status_label || row.queue_status || 'Pending'}
                </span>
            ),
        },
        {
            header: 'Action',
            render: (row) => {
                // Show different action based on queue status
                if (row.queue_status === 'tnp_complete') {
                    return (
                        <Link
                            to={`/care-bundles/create/${row.id}`}
                            className="font-medium text-green-600 hover:text-green-800 text-sm border border-green-200 bg-green-50 px-3 py-1 rounded-md transition-colors"
                        >
                            Build Bundle
                        </Link>
                    );
                }
                return (
                    <Link
                        to={`/tnp/${row.id}`}
                        className="font-medium text-teal-600 hover:text-teal-800 text-sm border border-teal-200 bg-teal-50 px-3 py-1 rounded-md transition-colors"
                    >
                        Review TNP
                    </Link>
                );
            },
        },
    ];

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;

    return (
        <div className="space-y-6">
            {/* Intake Performance Scorecard - Dynamic Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total In Queue</div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-2xl font-bold text-blue-600">{stats.totalInQueue}</span>
                        <span className="text-xs text-slate-500">Patients</span>
                    </div>
                    <div className="text-xs text-slate-500 mt-2">Awaiting transition</div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Active Patients</div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-2xl font-bold text-emerald-600">{stats.activePatients}</span>
                        <span className="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Active</span>
                    </div>
                    <div className="text-xs text-slate-500 mt-2">With care plans</div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pending Acceptance</div>
                    <div className="flex items-baseline gap-2">
                        <span className={`text-2xl font-bold ${stats.pendingAcceptance > 0 ? 'text-amber-500' : 'text-emerald-600'}`}>
                            {stats.pendingAcceptance}
                        </span>
                        <span className="text-xs text-slate-500">Referrals</span>
                    </div>
                    <div className={`text-xs mt-2 font-medium ${stats.pendingAcceptance > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
                        {stats.pendingAcceptance > 0 ? 'Action Required' : 'All processed'}
                    </div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Ready for Bundle</div>
                    <div className="flex items-baseline gap-2">
                        <span className={`text-2xl font-bold ${stats.readyForBundle > 0 ? 'text-green-600' : 'text-slate-400'}`}>
                            {stats.readyForBundle}
                        </span>
                        <span className="text-xs text-slate-500">TNP Complete</span>
                    </div>
                    <div className="text-xs text-green-600 mt-2 font-medium">
                        {stats.readyForBundle > 0 ? 'Ready to build' : 'None pending'}
                    </div>
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
