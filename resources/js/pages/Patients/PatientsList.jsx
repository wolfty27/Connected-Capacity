import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import { Link, useNavigate } from 'react-router-dom';
import { Users, UserPlus, Clock, CheckCircle, AlertCircle, Play } from 'lucide-react';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Spinner from '../../components/UI/Spinner';

/**
 * PatientsList - Displays patients with queue/active status filtering
 *
 * Shows patients in two views:
 * - Active Patients: Patients who have completed the queue workflow and have active care plans
 * - In Queue: Patients still in the intake/assessment workflow
 */
const PatientsList = () => {
    const navigate = useNavigate();
    const [patients, setPatients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('all'); // 'all', 'active', 'queue'
    const [summary, setSummary] = useState({ total: 0, active: 0, in_queue: 0 });

    useEffect(() => {
        fetchPatients();
    }, []);

    const fetchPatients = async () => {
        try {
            const response = await api.get('/patients');
            setPatients(response.data.data || []);
            setSummary(response.data.summary || { total: 0, active: 0, in_queue: 0 });
            setLoading(false);
        } catch (err) {
            setError('Failed to load patients.');
            setLoading(false);
            console.error(err);
        }
    };

    const handleStartBundle = (patient) => {
        navigate(`/care-bundles/create/${patient.id}`);
    };

    const filteredPatients = patients.filter(patient => {
        if (activeTab === 'active') {
            return !patient.is_in_queue;
        }
        if (activeTab === 'queue') {
            return patient.is_in_queue;
        }
        return true;
    });

    const getStatusBadge = (patient) => {
        if (patient.is_in_queue) {
            const queueStatus = patient.queue_status || 'pending_intake';
            const colors = {
                pending_intake: 'bg-gray-100 text-gray-700',
                triage_in_progress: 'bg-yellow-100 text-yellow-700',
                triage_complete: 'bg-blue-100 text-blue-700',
                assessment_in_progress: 'bg-yellow-100 text-yellow-700',
                assessment_complete: 'bg-green-100 text-green-700',
                bundle_building: 'bg-purple-100 text-purple-700',
                bundle_review: 'bg-orange-100 text-orange-700',
                bundle_approved: 'bg-emerald-100 text-emerald-700',
            };
            return (
                <span className={`text-xs px-2 py-1 rounded-full ${colors[queueStatus] || 'bg-gray-100 text-gray-700'}`}>
                    {patient.queue_status_label || 'In Queue'}
                </span>
            );
        }

        const statusColors = {
            'Active': 'bg-green-100 text-green-800',
            'Available': 'bg-green-100 text-green-800',
            'Inactive': 'bg-gray-100 text-gray-800',
            'Pending': 'bg-yellow-100 text-yellow-800',
        };

        return (
            <span className={`text-xs px-2 py-1 rounded-full ${statusColors[patient.status] || 'bg-gray-100 text-gray-800'}`}>
                {patient.status}
            </span>
        );
    };

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;
    if (error) return <div className="text-red-600 p-6">{error}</div>;

    return (
        <Section
            title="Patients"
            description="Manage your organization's patients"
            actions={
                <div className="flex gap-2">
                    <Button variant="outline" onClick={() => navigate('/tnp')}>
                        <Clock className="w-4 h-4 mr-2" />
                        View Intake Queue
                    </Button>
                    <Button onClick={() => console.log('Add Patient clicked')}>
                        <UserPlus className="w-4 h-4 mr-2" />
                        Add Patient
                    </Button>
                </div>
            }
        >
            {/* Summary Stats */}
            <div className="grid grid-cols-3 gap-4 mb-6">
                <div
                    onClick={() => setActiveTab('all')}
                    className={`bg-white border rounded-lg p-4 cursor-pointer transition-all ${activeTab === 'all' ? 'border-blue-500 ring-2 ring-blue-100 shadow-md' : 'border-gray-200 hover:border-blue-300 hover:shadow-sm'
                        }`}
                >
                    <div className="flex items-center gap-2 text-gray-600 text-sm mb-1">
                        <Users className="w-4 h-4" />
                        Total Patients
                    </div>
                    <div className="text-2xl font-bold text-gray-900">{summary.total}</div>
                </div>
                <div
                    onClick={() => setActiveTab('active')}
                    className={`bg-white border rounded-lg p-4 cursor-pointer transition-all ${activeTab === 'active' ? 'border-green-500 ring-2 ring-green-100 shadow-md' : 'border-green-200 hover:border-green-400 hover:shadow-sm'
                        }`}
                >
                    <div className="flex items-center gap-2 text-green-600 text-sm mb-1">
                        <CheckCircle className="w-4 h-4" />
                        Active
                    </div>
                    <div className="text-2xl font-bold text-green-700">{summary.active}</div>
                </div>
                <div
                    onClick={() => setActiveTab('queue')}
                    className={`bg-white border rounded-lg p-4 cursor-pointer transition-all ${activeTab === 'queue' ? 'border-yellow-500 ring-2 ring-yellow-100 shadow-md' : 'border-yellow-200 hover:border-yellow-400 hover:shadow-sm'
                        }`}
                >
                    <div className="flex items-center gap-2 text-yellow-600 text-sm mb-1">
                        <Clock className="w-4 h-4" />
                        In Queue
                    </div>
                    <div className="text-2xl font-bold text-yellow-700">{summary.in_queue}</div>
                </div>
            </div>



            {/* Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {filteredPatients.map((patient) => (
                    <div key={patient.id} className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-all duration-200">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xl font-bold">
                                    {patient.name ? patient.name.charAt(0) : '?'}
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900">
                                        {patient.name || 'Unknown'}
                                    </h3>
                                    {getStatusBadge(patient)}
                                </div>
                            </div>
                            {patient.is_in_queue && (
                                <div className="w-2 h-2 rounded-full bg-yellow-400 animate-pulse" title="In Queue"></div>
                            )}
                        </div>

                        <div className="mt-4 space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Gender</span>
                                <span className="text-gray-900 font-medium">{patient.gender || '-'}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Hospital</span>
                                <span className="text-gray-900 font-medium">{patient.hospital || '-'}</span>
                            </div>
                            {patient.rug_group ? (
                                <div className="space-y-1">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-500">RUG Group</span>
                                        <span className="text-gray-900 font-medium">{patient.rug_group}</span>
                                    </div>
                                    <div className="text-xs text-gray-600 bg-gray-50 rounded px-2 py-1">
                                        {patient.rug_description || patient.rug_category}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500">RUG Group</span>
                                    <span className="text-gray-400 italic">Not yet classified</span>
                                </div>
                            )}
                            {patient.is_in_queue && patient.queue_status && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500">Queue Status</span>
                                    <span className="text-gray-900 font-medium text-xs">
                                        {patient.queue_status_label}
                                    </span>
                                </div>
                            )}
                        </div>

                        <div className="mt-6 pt-4 border-t border-gray-100 flex justify-between items-center">
                            {patient.is_in_queue && patient.queue_status === 'assessment_complete' ? (
                                <button
                                    onClick={() => handleStartBundle(patient)}
                                    className="text-sm text-green-600 hover:text-green-800 font-medium flex items-center gap-1"
                                >
                                    <Play className="w-4 h-4" />
                                    Build Bundle
                                </button>
                            ) : patient.is_in_queue ? (
                                <span className="text-xs text-gray-400 flex items-center gap-1">
                                    <AlertCircle className="w-3 h-3" />
                                    InterRAI HC Assessment Pending
                                </span>
                            ) : (
                                <span></span>
                            )}
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

            {filteredPatients.length === 0 && (
                <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-200 border-dashed">
                    <Users className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                    <p className="text-gray-500">
                        {activeTab === 'queue'
                            ? 'No patients in queue.'
                            : activeTab === 'active'
                                ? 'No active patients.'
                                : 'No patients found.'}
                    </p>
                    {activeTab === 'queue' && (
                        <p className="text-sm text-gray-400 mt-1">
                            Add patients to the queue to begin the intake process.
                        </p>
                    )}
                </div>
            )}
        </Section>
    );
};

export default PatientsList;
