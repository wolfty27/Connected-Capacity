import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import DataTable from '../../components/UI/DataTable';
import Section from '../../components/UI/Section';

const TnpReviewListPage = () => {
    const [patients, setPatients] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchPatients = async () => {
            try {
                const response = await axios.get('/api/patients');
                setPatients(response.data.data || []); // Assuming pagination wrapper
            } catch (error) {
                console.error('Failed to fetch patients:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchPatients();
    }, []);

    if (loading) return <div>Loading...</div>;

    return (
        <Section title="Transition Needs Profiles">
            <div className="overflow-x-auto relative">
                <table className="w-full text-sm text-left text-gray-500">
                    <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" className="py-3 px-6">Patient Name</th>
                            <th scope="col" className="py-3 px-6">Status</th>
                            <th scope="col" className="py-3 px-6">Gender</th>
                            <th scope="col" className="py-3 px-6">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {patients.length > 0 ? (
                            patients.map((patient) => (
                                <tr key={patient.id} className="bg-white border-b">
                                    <td className="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">
                                        {patient.user?.name || 'Unknown'}
                                    </td>
                                    <td className="py-4 px-6">{patient.status}</td>
                                    <td className="py-4 px-6">{patient.gender}</td>
                                    <td className="py-4 px-6">
                                        <Link 
                                            to={`/tnp/${patient.id}`} 
                                            className="font-medium text-blue-600 hover:underline"
                                        >
                                            View TNP
                                        </Link>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="4" className="py-4 px-6 text-center">No patients found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </Section>
    );
};

export default TnpReviewListPage;
