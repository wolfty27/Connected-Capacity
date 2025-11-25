import React, { useState, useEffect } from 'react';
import axios from 'axios';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';

const SpoStaffPage = () => {
    const [staff, setStaff] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // Mock fetch for now
        setStaff([
            { id: 1, name: 'Nurse Joy', role: 'Nursing', type: 'Full-Time', active_hours: 32, capacity: 40 },
            { id: 2, name: 'Nurse Jackie', role: 'Nursing', type: 'Part-Time', active_hours: 15, capacity: 20 },
            { id: 3, name: 'Greg House', role: 'Nursing', type: 'Casual', active_hours: 5, capacity: 0 },
            { id: 4, name: 'PSW Team A', role: 'PSW', type: 'Full-Time', active_hours: 35, capacity: 40 },
        ]);
        setLoading(false);
    }, []);

    const columns = [
        { header: 'Name', accessor: 'name' },
        { header: 'Role', accessor: 'role' },
        { 
            header: 'Employment Type', 
            accessor: (row) => (
                <span className={`px-2 py-1 rounded-full text-xs font-bold ${
                    row.type === 'Full-Time' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                }`}>
                    {row.type}
                </span>
            )
        },
        { header: 'Active Hours', accessor: (row) => `${row.active_hours} / ${row.capacity} h` },
        {
            header: 'Action',
            accessor: (row) => <Button size="sm" variant="secondary">Edit Profile</Button>
        }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SPO Workforce Management</h1>
                    <p className="text-slate-500 text-sm">Manage direct care staff, credentials, and FTE compliance.</p>
                </div>
                <Button>+ Add Staff</Button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Total Staff</div>
                    <div className="text-3xl font-bold text-slate-700">{staff.length}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Full-Time Ratio</div>
                    <div className="text-3xl font-bold text-emerald-600">50%</div>
                    <div className="text-xs text-slate-400">Target: 80%</div>
                </div>
            </div>

            <Section title="Staff Directory">
                <DataTable columns={columns} data={staff} keyField="id" />
            </Section>
        </div>
    );
};

export default SpoStaffPage;