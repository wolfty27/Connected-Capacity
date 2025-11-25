import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    Users,
    Clock,
    AlertCircle,
    ChevronRight,
    Filter,
    RefreshCw,
    Play,
    FileText,
    User,
    Calendar,
    ArrowRight
} from 'lucide-react';
import patientQueueApi from '../../services/patientQueueApi';

/**
 * PatientQueueList - Displays patients in the care workflow queue
 *
 * Implements a Workday-style queue management view where patients progress
 * through defined stages from intake to having their care bundle built.
 * When a patient's bundle is completed and published, they transition
 * from this queue to their regular patient profile.
 */
const PatientQueueList = () => {
    const navigate = useNavigate();
    const [queue, setQueue] = useState([]);
    const [summary, setSummary] = useState({});
    const [loading, setLoading] = useState(true);
    const [selectedStatus, setSelectedStatus] = useState('all');
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        fetchQueue();
    }, [selectedStatus]);

    const fetchQueue = async () => {
        try {
            setLoading(true);
            const params = selectedStatus !== 'all' ? { status: selectedStatus } : {};
            const response = await patientQueueApi.getQueue(params);
            setQueue(response.data || []);
            setSummary(response.summary || {});
        } catch (error) {
            console.error('Failed to fetch queue', error);
        } finally {
            setLoading(false);
        }
    };

    const handleRefresh = async () => {
        setRefreshing(true);
        await fetchQueue();
        setRefreshing(false);
    };

    const handleStartBundle = async (queueEntry) => {
        try {
            const response = await patientQueueApi.startBundleBuilding(queueEntry.id);
            if (response.redirect_to) {
                navigate(response.redirect_to);
            }
        } catch (error) {
            console.error('Failed to start bundle building', error);
            alert(error.response?.data?.message || 'Failed to start bundle building');
        }
    };

    const handleTransition = async (queueEntry, toStatus) => {
        try {
            await patientQueueApi.transition(queueEntry.id, toStatus);
            fetchQueue();
        } catch (error) {
            console.error('Failed to transition', error);
            alert(error.response?.data?.message || 'Failed to transition');
        }
    };

    const getStatusBadgeClasses = (status) => {
        const color = patientQueueApi.getStatusColor(status);
        return {
            gray: 'bg-gray-100 text-gray-700 border-gray-200',
            yellow: 'bg-yellow-100 text-yellow-700 border-yellow-200',
            blue: 'bg-blue-100 text-blue-700 border-blue-200',
            green: 'bg-green-100 text-green-700 border-green-200',
            purple: 'bg-purple-100 text-purple-700 border-purple-200',
            orange: 'bg-orange-100 text-orange-700 border-orange-200',
            emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
            slate: 'bg-slate-100 text-slate-700 border-slate-200',
        }[color] || 'bg-gray-100 text-gray-700';
    };

    const getPriorityBadge = (priority) => {
        if (priority <= 2) {
            return <span className="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded">High</span>;
        }
        if (priority <= 5) {
            return <span className="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded">Medium</span>;
        }
        return <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Low</span>;
    };

    const formatTimeInQueue = (minutes) => {
        if (!minutes) return '--';
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h`;
        const days = Math.floor(hours / 24);
        return `${days}d`;
    };

    const statusFilterOptions = [
        { value: 'all', label: 'All Patients' },
        { value: 'pending_intake', label: 'Pending Intake' },
        { value: 'triage_in_progress', label: 'Triage In Progress' },
        { value: 'triage_complete', label: 'Triage Complete' },
        { value: 'tnp_in_progress', label: 'TNP In Progress' },
        { value: 'tnp_complete', label: 'Ready for Bundle' },
        { value: 'bundle_building', label: 'Bundle Building' },
        { value: 'bundle_review', label: 'Under Review' },
    ];

    if (loading && queue.length === 0) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="text-center text-slate-500">
                    <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2" />
                    <p>Loading queue...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="p-6 max-w-7xl mx-auto">
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900">Patient Queue</h1>
                <p className="text-slate-600 mt-1">
                    Patients awaiting care bundle assignment. Build a bundle to transition them to active care.
                </p>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                <div className="bg-white rounded-lg border border-slate-200 p-4">
                    <div className="flex items-center gap-2 text-slate-600 text-sm mb-1">
                        <Users className="w-4 h-4" />
                        <span>Total In Queue</span>
                    </div>
                    <div className="text-2xl font-bold text-slate-900">{summary.total_in_queue || 0}</div>
                </div>

                <div className="bg-white rounded-lg border border-green-200 p-4">
                    <div className="flex items-center gap-2 text-green-600 text-sm mb-1">
                        <FileText className="w-4 h-4" />
                        <span>Ready for Bundle</span>
                    </div>
                    <div className="text-2xl font-bold text-green-700">{summary.ready_for_bundle || 0}</div>
                </div>

                <div className="bg-white rounded-lg border border-yellow-200 p-4">
                    <div className="flex items-center gap-2 text-yellow-600 text-sm mb-1">
                        <Clock className="w-4 h-4" />
                        <span>Pending Intake</span>
                    </div>
                    <div className="text-2xl font-bold text-yellow-700">{summary.pending_intake || 0}</div>
                </div>

                <div className="bg-white rounded-lg border border-blue-200 p-4">
                    <div className="flex items-center gap-2 text-blue-600 text-sm mb-1">
                        <AlertCircle className="w-4 h-4" />
                        <span>Triage Complete</span>
                    </div>
                    <div className="text-2xl font-bold text-blue-700">{summary.triage_complete || 0}</div>
                </div>

                <div className="bg-white rounded-lg border border-purple-200 p-4">
                    <div className="flex items-center gap-2 text-purple-600 text-sm mb-1">
                        <Play className="w-4 h-4" />
                        <span>Bundle Building</span>
                    </div>
                    <div className="text-2xl font-bold text-purple-700">{summary.bundle_building || 0}</div>
                </div>

                <div className="bg-white rounded-lg border border-orange-200 p-4">
                    <div className="flex items-center gap-2 text-orange-600 text-sm mb-1">
                        <FileText className="w-4 h-4" />
                        <span>Under Review</span>
                    </div>
                    <div className="text-2xl font-bold text-orange-700">{summary.bundle_review || 0}</div>
                </div>
            </div>

            {/* Filters */}
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-2">
                        <Filter className="w-4 h-4 text-slate-500" />
                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            {statusFilterOptions.map(opt => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <button
                    onClick={handleRefresh}
                    disabled={refreshing}
                    className="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-md transition-colors"
                >
                    <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
                    Refresh
                </button>
            </div>

            {/* Queue List */}
            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
                {queue.length === 0 ? (
                    <div className="p-8 text-center text-slate-500">
                        <Users className="w-12 h-12 mx-auto mb-3 text-slate-300" />
                        <p className="text-lg font-medium">No patients in queue</p>
                        <p className="text-sm mt-1">Patients will appear here when they enter the intake process.</p>
                    </div>
                ) : (
                    <table className="w-full">
                        <thead className="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Patient</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Priority</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Time in Queue</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Coordinator</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {queue.map((entry) => (
                                <tr key={entry.id} className="hover:bg-slate-50 transition-colors">
                                    <td className="px-4 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center">
                                                <User className="w-5 h-5 text-slate-500" />
                                            </div>
                                            <div>
                                                <div className="font-medium text-slate-900">
                                                    {entry.patient?.user?.name || `Patient #${entry.patient_id}`}
                                                </div>
                                                <div className="text-sm text-slate-500">
                                                    ID: {entry.patient_id}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-4">
                                        <span className={`inline-flex px-3 py-1 text-xs font-medium rounded-full border ${getStatusBadgeClasses(entry.queue_status)}`}>
                                            {patientQueueApi.getStatusLabel(entry.queue_status)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-4">
                                        {getPriorityBadge(entry.priority)}
                                    </td>
                                    <td className="px-4 py-4">
                                        <div className="flex items-center gap-2 text-slate-600">
                                            <Clock className="w-4 h-4" />
                                            <span>{formatTimeInQueue(entry.time_in_queue)}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-4">
                                        {entry.assigned_coordinator ? (
                                            <div className="text-sm text-slate-900">
                                                {entry.assigned_coordinator.name}
                                            </div>
                                        ) : (
                                            <span className="text-sm text-slate-400">Unassigned</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {entry.queue_status === 'tnp_complete' && (
                                                <button
                                                    onClick={() => handleStartBundle(entry)}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors"
                                                >
                                                    <Play className="w-4 h-4" />
                                                    Build Bundle
                                                </button>
                                            )}

                                            {entry.queue_status === 'pending_intake' && (
                                                <button
                                                    onClick={() => handleTransition(entry, 'triage_in_progress')}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
                                                >
                                                    Start Triage
                                                    <ArrowRight className="w-4 h-4" />
                                                </button>
                                            )}

                                            {entry.queue_status === 'triage_in_progress' && (
                                                <button
                                                    onClick={() => handleTransition(entry, 'triage_complete')}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
                                                >
                                                    Complete Triage
                                                    <ArrowRight className="w-4 h-4" />
                                                </button>
                                            )}

                                            {entry.queue_status === 'triage_complete' && (
                                                <button
                                                    onClick={() => handleTransition(entry, 'tnp_in_progress')}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
                                                >
                                                    Start TNP
                                                    <ArrowRight className="w-4 h-4" />
                                                </button>
                                            )}

                                            {entry.queue_status === 'tnp_in_progress' && (
                                                <button
                                                    onClick={() => handleTransition(entry, 'tnp_complete')}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
                                                >
                                                    Complete TNP
                                                    <ArrowRight className="w-4 h-4" />
                                                </button>
                                            )}

                                            {entry.queue_status === 'bundle_building' && (
                                                <button
                                                    onClick={() => navigate(`/patients/${entry.patient_id}/bundle-wizard`)}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 transition-colors"
                                                >
                                                    Continue Building
                                                    <ChevronRight className="w-4 h-4" />
                                                </button>
                                            )}

                                            <button
                                                onClick={() => navigate(`/patients/${entry.patient_id}`)}
                                                className="inline-flex items-center gap-1 px-3 py-1.5 text-slate-600 text-sm font-medium rounded-md hover:bg-slate-100 transition-colors"
                                            >
                                                View
                                                <ChevronRight className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Queue Workflow Info */}
            <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 className="font-medium text-blue-900 mb-2">Queue Workflow</h3>
                <div className="flex flex-wrap items-center gap-2 text-sm text-blue-700">
                    <span className="px-2 py-1 bg-gray-200 rounded">Pending Intake</span>
                    <ArrowRight className="w-4 h-4" />
                    <span className="px-2 py-1 bg-yellow-200 rounded">Triage</span>
                    <ArrowRight className="w-4 h-4" />
                    <span className="px-2 py-1 bg-blue-200 rounded">TNP Assessment</span>
                    <ArrowRight className="w-4 h-4" />
                    <span className="px-2 py-1 bg-green-200 rounded">Ready for Bundle</span>
                    <ArrowRight className="w-4 h-4" />
                    <span className="px-2 py-1 bg-purple-200 rounded">Bundle Building</span>
                    <ArrowRight className="w-4 h-4" />
                    <span className="px-2 py-1 bg-emerald-200 rounded">Active Patient</span>
                </div>
                <p className="text-xs text-blue-600 mt-2">
                    When a care bundle is published, the patient transitions from this queue to their regular patient profile.
                </p>
            </div>
        </div>
    );
};

export default PatientQueueList;
