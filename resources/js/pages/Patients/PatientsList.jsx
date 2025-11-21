import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';

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
            setPatients(response.data.data);
            setLoading(false);
        } catch (err) {
            setError('Failed to load patients.');
            setLoading(false);
            console.error(err);
        }
    };

    if (loading) return <div className="text-white p-6">Loading patients...</div>;
    if (error) return <div className="text-red-400 p-6">{error}</div>;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-white">Patients</h1>
                    <p className="text-gray-400">Manage your organization's patients</p>
                </div>
                <button className="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg transition-all duration-200 backdrop-blur-sm bg-opacity-80 border border-blue-400/30">
                    + Add Patient
                </button>
            </div>

            {/* Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {patients.map((patient) => (
                    <div key={patient.id} className="bg-white/10 backdrop-blur-md border border-white/10 rounded-xl p-6 hover:bg-white/15 transition-all duration-300 group">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center space-x-4">
                                <img
                                    src={patient.photo || '/assets/images/patients/default.png'}
                                    alt={patient.name}
                                    className="w-12 h-12 rounded-full object-cover border-2 border-blue-500/50"
                                />
                                <div>
                                    <h3 className="text-lg font-semibold text-white group-hover:text-blue-300 transition-colors">
                                        {patient.name}
                                    </h3>
                                    <span className={`text-xs px-2 py-1 rounded-full ${patient.status === 'Available' ? 'bg-green-500/20 text-green-300 border border-green-500/30' :
                                            patient.status === 'Inactive' ? 'bg-gray-500/20 text-gray-300 border border-gray-500/30' :
                                                'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30'
                                        }`}>
                                        {patient.status}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-400">Gender</span>
                                <span className="text-gray-200">{patient.gender}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-400">Hospital</span>
                                <span className="text-gray-200">{patient.hospital}</span>
                            </div>
                        </div>

                        <div className="mt-6 pt-4 border-t border-white/10 flex justify-end">
                            <Link
                                to={`/patients/${patient.id}`}
                                className="text-sm text-blue-400 hover:text-blue-300 font-medium"
                            >
                                View Details &rarr;
                            </Link>
                        </div>
                    </div>
                ))}
            </div>

            {patients.length === 0 && (
                <div className="text-center py-12 bg-white/5 rounded-xl border border-white/10">
                    <p className="text-gray-400">No patients found.</p>
                </div>
            )}
        </div>
    );
};

export default PatientsList;
