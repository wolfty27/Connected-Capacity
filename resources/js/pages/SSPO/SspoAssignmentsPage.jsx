import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';
import Modal from '../../components/UI/Modal';
import KpiCard from '../../components/dashboard/KpiCard';
import { useAuth } from '../../contexts/AuthContext';

/**
 * SspoAssignmentsPage - SSPO assignment acceptance portal
 *
 * Per SSPO-003: UI for SSPO partners to:
 * - View pending assignment requests from SPO
 * - Accept or decline assignments with reason
 * - Track assignment metrics and performance
 */
const SspoAssignmentsPage = () => {
    const { user } = useAuth();
    const [assignments, setAssignments] = useState([]);
    const [metrics, setMetrics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [selectedAssignment, setSelectedAssignment] = useState(null);
    const [showDeclineModal, setShowDeclineModal] = useState(false);
    const [declineReason, setDeclineReason] = useState('');
    const [actionLoading, setActionLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('pending');

    const fetchData = useCallback(async () => {
        try {
            setLoading(true);
            const [assignmentsRes, metricsRes] = await Promise.all([
                api.get('/api/v2/assignments/pending-sspo'),
                api.get('/api/v2/assignments/sspo-metrics'),
            ]);
            setAssignments(assignmentsRes.data.data || []);
            setMetrics(metricsRes.data.data || {});
        } catch (error) {
            console.error('Failed to fetch SSPO assignments:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const handleAccept = async (assignment) => {
        try {
            setActionLoading(true);
            await api.post(`/api/v2/assignments/${assignment.id}/accept`);
            await fetchData();
        } catch (error) {
            console.error('Failed to accept assignment:', error);
            alert(error.response?.data?.message || 'Failed to accept assignment');
        } finally {
            setActionLoading(false);
        }
    };

    const handleDeclineClick = (assignment) => {
        setSelectedAssignment(assignment);
        setDeclineReason('');
        setShowDeclineModal(true);
    };

    const handleDeclineSubmit = async () => {
        if (!declineReason || declineReason.length < 10) {
            alert('Please provide a reason (at least 10 characters)');
            return;
        }

        try {
            setActionLoading(true);
            await api.post(`/api/v2/assignments/${selectedAssignment.id}/decline`, {
                reason: declineReason,
            });
            setShowDeclineModal(false);
            setSelectedAssignment(null);
            setDeclineReason('');
            await fetchData();
        } catch (error) {
            console.error('Failed to decline assignment:', error);
            alert(error.response?.data?.message || 'Failed to decline assignment');
        } finally {
            setActionLoading(false);
        }
    };

    const formatTime = (isoString) => {
        if (!isoString) return '-';
        return new Date(isoString).toLocaleString('en-CA', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getTimeSinceNotified = (notifiedAt) => {
        if (!notifiedAt) return '-';
        const diff = Date.now() - new Date(notifiedAt).getTime();
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        if (hours > 24) {
            return `${Math.floor(hours / 24)}d ${hours % 24}h`;
        }
        return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
    };

    const getStatusBadge = (status) => {
        const config = {
            pending: { bg: 'bg-amber-100', text: 'text-amber-800', label: 'Pending' },
            accepted: { bg: 'bg-emerald-100', text: 'text-emerald-800', label: 'Accepted' },
            declined: { bg: 'bg-red-100', text: 'text-red-800', label: 'Declined' },
        };
        const c = config[status] || config.pending;
        return (
            <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${c.bg} ${c.text}`}>
                {c.label}
            </span>
        );
    };

    const getAcuityBadge = (acuity) => {
        const config = {
            critical: { bg: 'bg-rose-100', text: 'text-rose-800' },
            high: { bg: 'bg-orange-100', text: 'text-orange-800' },
            medium: { bg: 'bg-amber-100', text: 'text-amber-800' },
            low: { bg: 'bg-emerald-100', text: 'text-emerald-800' },
        };
        const c = config[acuity] || config.medium;
        return (
            <span className={`px-2 py-0.5 rounded text-xs font-medium ${c.bg} ${c.text}`}>
                {acuity?.toUpperCase() || 'N/A'}
            </span>
        );
    };

    const pendingColumns = [
        {
            header: 'Patient',
            accessor: 'patient',
            sortable: true,
            render: (row) => (
                <div>
                    <div className="font-medium text-slate-900">{row.patient?.name || 'Unknown'}</div>
                    <div className="text-xs text-slate-500">ID: {row.patient?.id}</div>
                </div>
            ),
        },
        {
            header: 'Service',
            accessor: 'service_type',
            sortable: true,
            render: (row) => (
                <div>
                    <div className="font-medium">{row.service_type?.name || 'Unknown'}</div>
                    <div className="text-xs text-slate-500">{row.service_type?.code}</div>
                </div>
            ),
        },
        {
            header: 'Schedule',
            accessor: 'scheduled_start',
            sortable: true,
            render: (row) => (
                <div>
                    <div>{formatTime(row.scheduled_start)}</div>
                    <div className="text-xs text-slate-500">{row.frequency_rule || 'One-time'}</div>
                </div>
            ),
        },
        {
            header: 'Waiting',
            accessor: 'notified_at',
            sortable: true,
            render: (row) => (
                <div className={`font-medium ${row.notified_at && (Date.now() - new Date(row.notified_at).getTime()) > 24 * 60 * 60 * 1000 ? 'text-rose-600' : 'text-slate-600'}`}>
                    {getTimeSinceNotified(row.notified_at)}
                </div>
            ),
        },
        {
            header: 'Status',
            accessor: 'sspo_status',
            render: (row) => getStatusBadge(row.sspo_status),
        },
        {
            header: 'Actions',
            accessor: 'actions',
            render: (row) => (
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        onClick={(e) => { e.stopPropagation(); handleAccept(row); }}
                        disabled={actionLoading}
                    >
                        Accept
                    </Button>
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={(e) => { e.stopPropagation(); handleDeclineClick(row); }}
                        disabled={actionLoading}
                    >
                        Decline
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Assignment Requests</h1>
                    <p className="text-slate-500 text-sm">
                        Review and respond to assignments from SPO
                    </p>
                </div>
                <Button variant="secondary" onClick={fetchData} disabled={loading}>
                    {loading ? 'Refreshing...' : 'Refresh'}
                </Button>
            </div>

            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <KpiCard
                    label="Pending Requests"
                    value={metrics?.pending || 0}
                    status={metrics?.pending > 5 ? 'warning' : 'neutral'}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <KpiCard
                    label="Accepted"
                    value={metrics?.accepted || 0}
                    status="success"
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <KpiCard
                    label="Declined"
                    value={metrics?.declined || 0}
                    status="neutral"
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <KpiCard
                    label="Acceptance Rate"
                    value={metrics?.acceptance_rate ? `${metrics.acceptance_rate}%` : '-'}
                    status={metrics?.acceptance_rate >= 90 ? 'success' : metrics?.acceptance_rate >= 70 ? 'warning' : 'critical'}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    }
                />
            </div>

            {/* Tabs */}
            <div className="border-b border-slate-200">
                <nav className="-mb-px flex gap-4">
                    {[
                        { id: 'pending', label: 'Pending', count: assignments.filter(a => a.sspo_status === 'pending').length },
                        { id: 'all', label: 'All Requests', count: assignments.length },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`py-3 px-1 border-b-2 font-medium text-sm ${activeTab === tab.id
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                                }`}
                        >
                            {tab.label}
                            <span className={`ml-2 py-0.5 px-2 rounded-full text-xs ${activeTab === tab.id ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600'
                                }`}>
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </nav>
            </div>

            {/* Table */}
            <DataTable
                columns={pendingColumns}
                data={activeTab === 'pending'
                    ? assignments.filter(a => a.sspo_status === 'pending')
                    : assignments
                }
                loading={loading}
                pagination
                pageSize={10}
                searchable
                searchPlaceholder="Search by patient or service..."
                emptyMessage={activeTab === 'pending'
                    ? 'No pending assignment requests. Check back later!'
                    : 'No assignment requests found.'
                }
            />

            {/* Decline Modal */}
            <Modal
                isOpen={showDeclineModal}
                onClose={() => setShowDeclineModal(false)}
                title="Decline Assignment"
            >
                <div className="space-y-4">
                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div className="flex gap-3">
                            <svg className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p className="font-medium text-amber-800">Declining an assignment</p>
                                <p className="text-sm text-amber-700 mt-1">
                                    The SPO will be notified and may reassign to another SSPO or handle internally.
                                </p>
                            </div>
                        </div>
                    </div>

                    {selectedAssignment && (
                        <div className="bg-slate-50 rounded-lg p-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-slate-500">Patient:</span>
                                    <span className="ml-2 font-medium">{selectedAssignment.patient?.name}</span>
                                </div>
                                <div>
                                    <span className="text-slate-500">Service:</span>
                                    <span className="ml-2 font-medium">{selectedAssignment.service_type?.name}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-2">
                            Reason for declining <span className="text-rose-500">*</span>
                        </label>
                        <textarea
                            value={declineReason}
                            onChange={(e) => setDeclineReason(e.target.value)}
                            placeholder="Please provide a detailed reason (min 10 characters)..."
                            rows={4}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            {declineReason.length}/500 characters (min 10)
                        </p>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Button
                            variant="secondary"
                            onClick={() => setShowDeclineModal(false)}
                            disabled={actionLoading}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleDeclineSubmit}
                            disabled={actionLoading || declineReason.length < 10}
                        >
                            {actionLoading ? 'Declining...' : 'Decline Assignment'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default SspoAssignmentsPage;
