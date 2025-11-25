import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Spinner from '../../components/UI/Spinner';
import Button from '../../components/UI/Button';

const PatientDetailPage = () => {
    const { id } = useParams();
    const [patient, setPatient] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchPatient = async () => {
            try {
                const response = await axios.get(`/api/patients/${id}`);
                setPatient(response.data.data);
            } catch (error) {
                console.error('Failed to fetch patient:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchPatient();
    }, [id]);

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;
    if (!patient) return <div className="p-12 text-center">Patient not found.</div>;

    return (
        <Section 
            title={patient.name} 
            description={`Patient ID: ${patient.id}`}
            actions={
                <Link to={`/tnp/${patient.id}`}>
                    <Button variant="secondary">View Transition Needs</Button>
                </Link>
            }
        >
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Profile Card */}
                <Card title="Demographics" className="md:col-span-1">
                    <div className="flex flex-col items-center mb-4">
                        <img 
                            src={patient.photo || '/assets/images/patients/default.png'} 
                            alt={patient.name} 
                            className="w-24 h-24 rounded-full mb-2 bg-gray-200"
                        />
                        <span className={`px-3 py-1 rounded-full text-sm ${
                            patient.status === 'Available' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                        }`}>
                            {patient.status}
                        </span>
                    </div>
                    <div className="space-y-3">
                        <div>
                            <label className="text-xs text-gray-500 uppercase">Gender</label>
                            <p className="font-medium">{patient.gender}</p>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 uppercase">Email</label>
                            <p className="font-medium truncate">{patient.email || 'N/A'}</p>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 uppercase">Phone</label>
                            <p className="font-medium">{patient.phone || 'N/A'}</p>
                        </div>
                    </div>
                </Card>

                {/* Clinical / Care Context */}
                <div className="md:col-span-2 space-y-6">
                    <Card title="Care Context">
                        <p className="text-gray-500 italic">No active care plan found.</p>
                    </Card>

                    <Card title="Recent Activity">
                        <ul className="space-y-3 text-sm">
                            <li className="flex justify-between text-gray-600">
                                <span>Profile accessed by Admin</span>
                                <span>Just now</span>
                            </li>
                        </ul>
                    </Card>
                </div>
            </div>
        </Section>
    );
};

export default PatientDetailPage;
