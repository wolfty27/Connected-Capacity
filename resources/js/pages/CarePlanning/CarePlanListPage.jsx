import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import { Link } from 'react-router-dom';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';

const CarePlanListPage = () => {
    const [plans, setPlans] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchPlans = async () => {
            try {
                const response = await api.get('/api/v2/care-plans');
                setPlans(response.data);
                setLoading(false);
            } catch (error) {
                console.error('Failed to fetch plans', error);
                setLoading(false);
            }
        };
        fetchPlans();
    }, []);

    const columns = [
        { header: 'Patient', accessor: 'patient' },
        { header: 'Care Bundle', accessor: 'bundle' },
        { header: 'Start Date', accessor: 'start_date' },
        { 
            header: 'Status', 
            accessor: (row) => (
                <span className="px-2 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    {row.status}
                </span>
            )
        },
        {
            header: 'Action',
            accessor: (row) => (
                <Button size="sm" variant="secondary">View CDP</Button>
            )
        }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Care Delivery Plans (CDP)</h1>
                    <p className="text-slate-500 text-sm">Manage active care bundles and schedule 3 compliance.</p>
                </div>
                <Button>+ Create New Plan</Button>
            </div>

            <Section title="Active Plans">
                <DataTable columns={columns} data={plans} keyField="id" />
            </Section>
        </div>
    );
};

export default CarePlanListPage;