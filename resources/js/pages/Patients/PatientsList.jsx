import React, { useEffect, useState, useMemo } from 'react';
import api from '../../services/api';
import { Link, useNavigate } from 'react-router-dom';
import { Users, UserPlus, Clock, CheckCircle, AlertCircle, Play, ArrowUpDown } from 'lucide-react';
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
    const [sortOption, setSortOption] = useState('name'); // 'name', 'rug', 'queue'

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

    // Filter patients by tab
    const filteredPatients = patients.filter(patient => {
        if (activeTab === 'active') {
            return !patient.is_in_queue;
        }
        if (activeTab === 'queue') {
            return patient.is_in_queue;
        }
        return true;
    });

    // Sort patients based on selected option
    const sortedPatients = useMemo(() => {
        return [...filteredPatients].sort((a, b) => {
            switch (sortOption) {
                case 'name':
                    return (a.name || '').localeCompare(b.name || '');
                case 'rug':
                    // Sort by RUG numeric rank (lower = higher acuity), then by name
                    const rankA = a.rug_numeric_rank ?? 999;
                    const rankB = b.rug_numeric_rank ?? 999;
                    if (rankA !== rankB) return rankA - rankB;
                    return (a.name || '').localeCompare(b.name || '');
                case 'queue':
                    // Sort by queue status: Active first, then Ready for Bundle, then Pending
                    const getQueueOrder = (p) => {
                        if (!p.is_in_queue) return 0; // Active patients first
                        if (p.queue_status === 'assessment_complete') return 1; // Ready for bundle
                        return 2; // Pending
                    };
                    const orderA = getQueueOrder(a);
                    const orderB = getQueueOrder(b);
                    if (orderA !== orderB) return orderA - orderB;
                    return (a.name || '').localeCompare(b.name || '');
                default:
                    return 0;
            }
        });
    }, [filteredPatients, sortOption]);

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
            // Shorten label for assessment_complete to fit on one line
            const label = queueStatus === 'assessment_complete'
                ? 'InterRAI HC Complete – Ready for Bundle'
                : (patient.queue_status_label || 'In Queue');
            return (
                <span className={`inline-flex items-center whitespace-nowrap text-xs px-2 py-0.5 rounded-full ${colors[queueStatus] || 'bg-gray-100 text-gray-700'}`}>
                    {label}
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
            <span className={`inline-flex items-center whitespace-nowrap text-xs px-2 py-0.5 rounded-full ${statusColors[patient.status] || 'bg-gray-100 text-gray-800'}`}>
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
                <Button onClick={() => navigate('/patients/add')}>
                    <UserPlus className="w-4 h-4 mr-2" />
                    Add Patient
                </Button>
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

            {/* Sort Controls */}
            <div className="flex items-center justify-between mb-4">
                <div className="text-sm text-gray-500">
                    Showing {sortedPatients.length} {sortedPatients.length === 1 ? 'patient' : 'patients'}
                </div>
                <div className="flex items-center gap-2">
                    <ArrowUpDown className="w-4 h-4 text-gray-400" />
                    <label htmlFor="sort-select" className="text-sm text-gray-600">Sort by:</label>
                    <select
                        id="sort-select"
                        value={sortOption}
                        onChange={(e) => setSortOption(e.target.value)}
                        className="text-sm border border-gray-300 rounded-md px-2 py-1 bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400"
                    >
                        <option value="name">Name (A–Z)</option>
                        <option value="rug">RUG Group</option>
                        <option value="queue">Queue Status</option>
                    </select>
                </div>
            </div>

            {/* Grid - more compact cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {sortedPatients.map((patient) => (
                    <div key={patient.id} className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-all duration-200">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center space-x-3">
                                <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-lg font-bold flex-shrink-0">
                                    {patient.name ? patient.name.charAt(0) : '?'}
                                </div>
                                <div className="min-w-0">
                                    <h3 className="text-base font-semibold text-gray-900 truncate">
                                        {patient.name || 'Unknown'}
                                    </h3>
                                    {getStatusBadge(patient)}
                                </div>
                            </div>
                            {patient.is_in_queue && (
                                <div className="w-2 h-2 rounded-full bg-yellow-400 animate-pulse flex-shrink-0" title="In Queue"></div>
                            )}
                        </div>

                        <div className="mt-3 space-y-1.5">
                            <div className="flex justify-between text-xs">
                                <span className="text-gray-500">Gender</span>
                                <span className="text-gray-900 font-medium">{patient.gender || '-'}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span className="text-gray-500">Hospital</span>
                                <span className="text-gray-900 font-medium truncate ml-2">{patient.hospital || '-'}</span>
                            </div>
                            {patient.rug_group ? (
                                <div className="flex justify-between text-xs items-start">
                                    <span className="text-gray-500">RUG</span>
                                    <div className="text-right">
                                        <span className="text-gray-900 font-medium">{patient.rug_group}</span>
                                        <span className="text-gray-500 ml-1">({patient.rug_category})</span>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex justify-between text-xs">
                                    <span className="text-gray-500">RUG</span>
                                    <span className="text-gray-400 italic">Not classified</span>
                                </div>
                            )}
                            {/* Clinical Flags */}
                            {patient.top_clinical_flags && patient.top_clinical_flags.length > 0 && (
                                <div className="pt-1.5">
                                    <div className="flex flex-wrap gap-1">
                                        {patient.top_clinical_flags.map((flag, idx) => (
                                            <span
                                                key={idx}
                                                className={`inline-flex items-center text-[10px] px-1.5 py-0.5 rounded-full font-medium ${
                                                    flag.severity === 'danger'
                                                        ? 'bg-rose-100 text-rose-700'
                                                        : flag.severity === 'warning'
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-blue-100 text-blue-700'
                                                }`}
                                            >
                                                {flag.label}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center">
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

            {sortedPatients.length === 0 && (
                <div className="text-center py-8 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
                    <Users className="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p className="text-gray-500 text-sm">
                        {activeTab === 'queue'
                            ? 'No patients in queue.'
                            : activeTab === 'active'
                                ? 'No active patients.'
                                : 'No patients found.'}
                    </p>
                    {activeTab === 'queue' && (
                        <p className="text-xs text-gray-400 mt-1">
                            Add patients to the queue to begin the intake process.
                        </p>
                    )}
                </div>
            )}
        </Section>
    );
};

export default PatientsList;
