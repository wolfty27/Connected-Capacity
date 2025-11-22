import React, { useEffect, useState } from 'react';
import axios from 'axios';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';

const FieldStaffWorklistPage = () => {
    const [assignments, setAssignments] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchAssignments = async () => {
            try {
                const response = await axios.get('/api/care-assignments');
                setAssignments(response.data);
            } catch (error) {
                console.error('Failed to fetch assignments:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchAssignments();
    }, []);

    if (loading) return <div>Loading...</div>;

    const columns = [
        { header: 'Patient', accessor: 'patient.user.name' }, // Nested accessor logic needed in DataTable or map data first
        { header: 'Status', accessor: 'status' },
        { header: 'Start Date', accessor: 'start_date' },
        { header: 'Actions', accessor: 'actions' },
    ];

    // Flatten data for simple table
    const tableData = assignments.map(a => ({
        id: a.id,
        patient_name: a.patient?.user?.name || 'Unknown', // Assuming patient relationship loaded
        status: a.status,
        start_date: a.start_date,
        actions: 'View' // Placeholder
    }));

    return (
        <Section title="My Worklist">
            <div className="mb-6">
                <h3 className="text-lg font-medium mb-2">Today's Assignments</h3>
                <DataTable 
                    columns={[
                        { header: 'Patient', accessor: 'patient_name' },
                        { header: 'Status', accessor: 'status' },
                        { header: 'Start Date', accessor: 'start_date' },
                    ]}
                    data={tableData}
                />
            </div>
        </Section>
    );
};

export default FieldStaffWorklistPage;
