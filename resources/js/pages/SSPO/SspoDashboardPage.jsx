import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';
import { useAuth } from '../../contexts/AuthContext';

const SspoDashboardPage = () => {
    const { user } = useAuth();
    const [assignments, setAssignments] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchAssignments = async () => {
            try {
                const response = await api.get('/v2/sspo/assignments');
                setAssignments(response.data);
            } catch (error) {
                console.error('Failed to fetch SSPO assignments', error);
            } finally {
                setLoading(false);
            }
        };

        fetchAssignments();
    }, []);

    const columns = [
        { header: 'Patient', accessor: 'patient' },
        { header: 'Service Type', accessor: 'service' },
        {
            header: 'Status',
            accessor: (row) => (
                <span className="px-2 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    {row.status}
                </span>
            )
        },
        { header: 'Frequency', accessor: 'frequency' },
        { header: 'Next Scheduled', accessor: 'next_visit' },
        {
            header: 'Action',
            accessor: (row) => (
                <Button size="sm" variant="secondary">View Care Plan</Button>
            )
        }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SSPO Partner Portal</h1>
                    <p className="text-slate-500 text-sm">Organization: <span className="font-bold text-teal-700">{user?.organization?.name || 'Alexis Lodge'}</span></p>
                </div>
                <div className="flex gap-2">
                    <Button variant="secondary">Manage Staff</Button>
                    <Button>Update Capacity</Button>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Active Patients</div>
                    <div className="text-3xl font-bold text-teal-600">{assignments.length}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Pending Referrals</div>
                    <div className="text-3xl font-bold text-amber-500">0</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Today's Visits</div>
                    <div className="text-3xl font-bold text-blue-600">1</div>
                </div>
            </div>

            <Section title="My Caseload (Referrals from SPO)">
                <DataTable columns={columns} data={assignments} keyField="id" />
            </Section>
        </div>
    );
};

export default SspoDashboardPage;