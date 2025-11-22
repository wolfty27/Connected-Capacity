import React, { useEffect, useState } from 'react';
import axios from 'axios';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import DataTable from '../../components/UI/DataTable';

const CareDashboardPage = () => {
    const [metrics, setMetrics] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchMetrics = async () => {
            try {
                // Using the generic dashboard endpoint for now, which returns role-specific data
                const response = await axios.get('/api/v2/dashboard');
                setMetrics(response.data);
            } catch (error) {
                console.error('Failed to fetch dashboard metrics:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchMetrics();
    }, []);

    if (loading) return <div>Loading...</div>;

    return (
        <Section title="Care Operations Dashboard">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <Card title="Patients">
                    <div className="text-3xl font-bold text-blue-600">
                        {metrics?.patientCount || metrics?.patientsCount || 0}
                    </div>
                </Card>
                <Card title="Appointments">
                    <div className="text-3xl font-bold text-green-600">
                        {metrics?.AppointmentCount || metrics?.appointmentCount || 0}
                    </div>
                </Card>
                <Card title="Offers / Placements">
                    <div className="text-3xl font-bold text-purple-600">
                        {metrics?.offerCount || metrics?.mypatientCount || 0}
                    </div>
                </Card>
            </div>

            {/* Placeholder for future widgets like "Missed Visits" or "Unfilled Shifts" */}
            <Card title="Recent Activity">
                <p className="text-gray-500">Real-time alerts and operational updates will appear here.</p>
            </Card>
        </Section>
    );
};

export default CareDashboardPage;
