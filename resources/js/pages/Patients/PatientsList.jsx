import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Spinner from '../../components/UI/Spinner';

const PatientsList = () => {
    const [patients, setPatients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchPatients();
    }, []);

    const fetchPatients = async () => {
        try {
            const response = await axios.get('/api/patients');
            setPatients(response.data.data || []);
            setLoading(false);
        } catch (err) {
            setError('Failed to load patients.');
            setLoading(false);
            console.error(err);
        }
    };

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;
    if (error) return <div className="text-red-600 p-6">{error}</div>;

    return (
        <Section 
            title="Patients" 
            description="Manage your organization's patients"
            actions={
                <Button onClick={() => console.log('Add Patient clicked')}>
                    + Add Patient
                </Button>
            }
        >
            {/* Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {patients.map((patient) => (
                    <div key={patient.id} className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-all duration-200">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xl font-bold">
                                    {patient.name ? patient.name.charAt(0) : '?'}
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900">
                                        {patient.name}
                                    </h3>
                                    <span className={`text-xs px-2 py-1 rounded-full ${
                                        patient.status === 'Available' ? 'bg-green-100 text-green-800' :
                                        patient.status === 'Inactive' ? 'bg-gray-100 text-gray-800' :
                                        'bg-yellow-100 text-yellow-800'
                                    }`}>
                                        {patient.status}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Gender</span>
                                <span className="text-gray-900 font-medium">{patient.gender}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Hospital</span>
                                <span className="text-gray-900 font-medium">{patient.hospital}</span>
                            </div>
                        </div>

                        <div className="mt-6 pt-4 border-t border-gray-100 flex justify-end">
                            <Link
                                to={`/patients/${patient.id}`}
                                className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                            >
                                View Details &rarr;
                            </Link>
                        </div>
                    </div>
                ))}
            </div>

            {patients.length === 0 && (
                <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-200 border-dashed">
                    <p className="text-gray-500">No patients found.</p>
                </div>
            )}
        </Section>
    );
};

export default PatientsList;
