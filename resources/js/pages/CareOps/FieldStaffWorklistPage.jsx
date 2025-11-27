import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import { Link } from 'react-router-dom';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Spinner from '../../components/UI/Spinner';
import Button from '../../components/UI/Button';

const FieldStaffWorklistPage = () => {
    const [assignments, setAssignments] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchAssignments = async () => {
            try {
                const response = await api.get('/care-assignments');
                setAssignments(response.data || []);
            } catch (error) {
                console.error('Failed to fetch assignments:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchAssignments();
    }, []);

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;

    // Flatten data for simple table
    const tableData = assignments.map(a => ({
        id: a.id,
        patient_name: a.patient?.user?.name || 'Unknown',
        status: a.status,
        start_date: a.start_date,
        // actions is handled by column renderer
    }));

    const columns = [
        { header: 'Patient', accessor: 'patient_name' },
        { 
            header: 'Status', 
            accessor: 'status',
            render: (row) => (
                <span className={`px-2 py-1 rounded-full text-xs ${
                    row.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                }`}>
                    {row.status}
                </span>
            )
        },
        { header: 'Start Date', accessor: 'start_date' },
        {
            header: 'Actions',
            accessor: (row) => (
                <Link to={`/assignments/${row.id}`}>
                    <Button size="sm" variant="secondary">
                        View
                    </Button>
                </Link>
            )
        }
    ];

    return (
        <Section title="My Worklist">
            <div className="mb-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Today's Assignments</h3>
                {tableData.length > 0 ? (
                    <DataTable 
                        columns={columns}
                        data={tableData}
                        keyField="id"
                    />
                ) : (
                    <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-200 border-dashed">
                        <p className="text-gray-500">No assignments found for today.</p>
                    </div>
                )}
            </div>
        </Section>
    );
};

export default FieldStaffWorklistPage;