import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Card from '../../components/UI/Card';
import Button from '../../components/UI/Button';
import DataTable from '../../components/UI/DataTable';
import Section from '../../components/UI/Section';
import Spinner from '../../components/UI/Spinner';
import InterraiStatusBadge, { InterraiStatusDot } from '../../components/InterRAI/InterraiStatusBadge';
import interraiApi from '../../services/interraiApi';

/**
 * InterraiDashboardPage - Admin dashboard for InterRAI compliance
 *
 * IR-006: Shows KPIs, lists, and management actions
 */
const InterraiDashboardPage = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('stale');

    // Data states
    const [stats, setStats] = useState(null);
    const [complianceData, setComplianceData] = useState(null);
    const [staleAssessments, setStaleAssessments] = useState([]);
    const [missingAssessments, setMissingAssessments] = useState([]);
    const [failedUploads, setFailedUploads] = useState([]);
    const [pendingTriggers, setPendingTriggers] = useState([]);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        setError(null);
        try {
            const [statsRes, complianceRes, staleRes, missingRes, failedRes, triggersRes] = await Promise.all([
                interraiApi.getDashboardStats(),
                interraiApi.getComplianceReport(),
                interraiApi.getStaleAssessments(50),
                interraiApi.getMissingAssessments(50),
                interraiApi.getFailedUploads(50),
                interraiApi.getPendingTriggers(50),
            ]);

            setStats(statsRes.data);
            setComplianceData(complianceRes.data);
            setStaleAssessments(staleRes.data);
            setMissingAssessments(missingRes.data);
            setFailedUploads(failedRes.data);
            setPendingTriggers(triggersRes.data);
        } catch (err) {
            setError('Failed to load dashboard data');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleRefresh = async () => {
        setRefreshing(true);
        await loadData();
        setRefreshing(false);
    };

    const handleBulkRetry = async () => {
        if (!confirm(`Retry IAR upload for ${stats?.failed_uploads || 0} failed assessments?`)) return;

        try {
            const result = await interraiApi.bulkRetryIar();
            alert(result.message);
            loadData();
        } catch (err) {
            alert('Failed to queue bulk retry');
        }
    };

    const handleSyncStatuses = async () => {
        try {
            const result = await interraiApi.syncStatuses();
            alert(result.message);
            loadData();
        } catch (err) {
            alert('Failed to sync statuses');
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <Spinner />
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6 text-center">
                <p className="text-rose-600 mb-4">{error}</p>
                <Button onClick={loadData}>Retry</Button>
            </div>
        );
    }

    // Table columns
    const staleColumns = [
        { header: 'Patient', accessor: 'patient_name', sortable: true },
        { header: 'Coordinator', accessor: 'coordinator' },
        {
            header: 'Assessment Date',
            accessor: 'assessment_date',
            render: (row) => row.assessment_date || 'N/A',
        },
        {
            header: 'Days Overdue',
            accessor: 'days_stale',
            render: (row) => (
                <span className={row.days_stale > 120 ? 'text-rose-600 font-medium' : 'text-amber-600'}>
                    {row.days_stale} days
                </span>
            ),
        },
        { header: 'MAPLe', accessor: 'maple_score' },
        {
            header: 'Actions',
            accessor: 'patient_id',
            render: (row) => (
                <Button
                    variant="link"
                    onClick={() => navigate(`/interrai/complete/${row.patient_id}`)}
                >
                    Reassess
                </Button>
            ),
        },
    ];

    const missingColumns = [
        { header: 'Patient', accessor: 'patient_name', sortable: true },
        { header: 'Coordinator', accessor: 'coordinator' },
        { header: 'Queue Status', accessor: 'queue_status' },
        {
            header: 'Days in Queue',
            accessor: 'days_in_queue',
            render: (row) => `${row.days_in_queue} days`,
        },
        {
            header: 'Actions',
            accessor: 'patient_id',
            render: (row) => (
                <Button
                    variant="link"
                    onClick={() => navigate(`/interrai/complete/${row.patient_id}`)}
                >
                    Complete
                </Button>
            ),
        },
    ];

    const failedColumns = [
        { header: 'Patient', accessor: 'patient_name', sortable: true },
        { header: 'Assessment Date', accessor: 'assessment_date' },
        { header: 'MAPLe', accessor: 'maple_score' },
        { header: 'Source', accessor: 'source' },
        {
            header: 'Failed At',
            accessor: 'failed_at',
            render: (row) => new Date(row.failed_at).toLocaleString(),
        },
        {
            header: 'Actions',
            accessor: 'assessment_id',
            render: (row) => (
                <Button
                    variant="link"
                    onClick={async () => {
                        await interraiApi.retryIarUpload(row.assessment_id);
                        loadData();
                    }}
                >
                    Retry
                </Button>
            ),
        },
    ];

    const triggerColumns = [
        { header: 'Patient', accessor: 'patient_name', sortable: true },
        {
            header: 'Reason',
            accessor: 'reason_label',
            render: (row) => (
                <span className="text-sm">{row.reason_label}</span>
            ),
        },
        {
            header: 'Priority',
            accessor: 'priority',
            render: (row) => (
                <span
                    className={`px-2 py-0.5 rounded text-xs font-medium ${
                        row.priority === 'urgent'
                            ? 'bg-rose-100 text-rose-700'
                            : row.priority === 'high'
                            ? 'bg-orange-100 text-orange-700'
                            : 'bg-slate-100 text-slate-700'
                    }`}
                >
                    {row.priority_label}
                </span>
            ),
        },
        {
            header: 'Requested',
            accessor: 'triggered_at',
            render: (row) => new Date(row.triggered_at).toLocaleDateString(),
        },
        {
            header: 'Actions',
            accessor: 'patient_id',
            render: (row) => (
                <Button
                    variant="link"
                    onClick={() => navigate(`/interrai/complete/${row.patient_id}`)}
                >
                    Assess
                </Button>
            ),
        },
    ];

    const tabs = [
        { id: 'stale', label: 'Stale Assessments', count: staleAssessments.length },
        { id: 'missing', label: 'Missing Assessments', count: missingAssessments.length },
        { id: 'failed', label: 'Failed Uploads', count: failedUploads.length },
        { id: 'triggers', label: 'Reassessment Requests', count: pendingTriggers.length },
    ];

    return (
        <Section
            title="InterRAI Compliance Dashboard"
            description="Monitor assessment status and IAR integration"
            action={
                <div className="flex gap-2">
                    <Button variant="secondary" onClick={handleSyncStatuses}>
                        Sync Statuses
                    </Button>
                    <Button variant="secondary" onClick={handleRefresh} disabled={refreshing}>
                        {refreshing ? <Spinner className="w-4 h-4" /> : 'Refresh'}
                    </Button>
                </div>
            }
        >
            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {/* Compliance Rate */}
                <Card variant="kpi">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-slate-500">Assessment Coverage</p>
                            <p className="text-3xl font-bold text-emerald-600">
                                {complianceData?.assessment_coverage?.completion_rate || 0}%
                            </p>
                        </div>
                        <div className="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg className="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p className="text-xs text-slate-400 mt-2">
                        {complianceData?.assessment_coverage?.with_current_assessment || 0} of{' '}
                        {complianceData?.assessment_coverage?.total_patients_in_queue || 0} patients
                    </p>
                </Card>

                {/* Stale */}
                <Card variant="kpi">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-slate-500">Stale Assessments</p>
                            <p className="text-3xl font-bold text-amber-600">
                                {stats?.stale_assessments || 0}
                            </p>
                        </div>
                        <InterraiStatusBadge status="stale" showLabel={false} size="lg" />
                    </div>
                    <p className="text-xs text-slate-400 mt-2">&gt;90 days old, need reassessment</p>
                </Card>

                {/* Missing */}
                <Card variant="kpi">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-slate-500">Missing Assessments</p>
                            <p className="text-3xl font-bold text-rose-600">
                                {stats?.missing_assessments || 0}
                            </p>
                        </div>
                        <InterraiStatusBadge status="missing" showLabel={false} size="lg" />
                    </div>
                    <p className="text-xs text-slate-400 mt-2">No assessment on file</p>
                </Card>

                {/* IAR Success Rate */}
                <Card variant="kpi">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-slate-500">IAR Upload Success</p>
                            <p className="text-3xl font-bold text-blue-600">
                                {complianceData?.iar_integration?.success_rate || 0}%
                            </p>
                        </div>
                        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg className="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 mt-2">
                        <span className="text-xs text-slate-400">
                            {stats?.failed_uploads || 0} failed
                        </span>
                        {(stats?.failed_uploads || 0) > 0 && (
                            <Button variant="link" onClick={handleBulkRetry} className="text-xs">
                                Retry All
                            </Button>
                        )}
                    </div>
                </Card>
            </div>

            {/* Urgent Triggers Alert */}
            {(stats?.urgent_triggers || 0) > 0 && (
                <div className="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-lg flex items-center gap-3">
                    <svg className="w-6 h-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p className="font-medium text-rose-700">
                            {stats?.urgent_triggers} Urgent Reassessment{stats?.urgent_triggers > 1 ? 's' : ''} Pending
                        </p>
                        <p className="text-sm text-rose-600">
                            These patients have high-priority reassessment requests that need attention.
                        </p>
                    </div>
                </div>
            )}

            {/* Tabs */}
            <div className="border-b border-slate-200 mb-4">
                <nav className="flex gap-4">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`
                                pb-3 px-1 text-sm font-medium border-b-2 transition-colors
                                ${activeTab === tab.id
                                    ? 'border-teal-600 text-teal-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                                }
                            `}
                        >
                            {tab.label}
                            <span className={`ml-2 px-2 py-0.5 rounded-full text-xs ${
                                activeTab === tab.id ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600'
                            }`}>
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </nav>
            </div>

            {/* Tab Content */}
            <Card>
                {activeTab === 'stale' && (
                    <DataTable
                        columns={staleColumns}
                        data={staleAssessments}
                        pagination={true}
                        pageSize={10}
                        searchable={true}
                        onRowClick={(row) => navigate(`/patients/${row.patient_id}`)}
                    />
                )}
                {activeTab === 'missing' && (
                    <DataTable
                        columns={missingColumns}
                        data={missingAssessments}
                        pagination={true}
                        pageSize={10}
                        searchable={true}
                        onRowClick={(row) => navigate(`/patients/${row.patient_id}`)}
                    />
                )}
                {activeTab === 'failed' && (
                    <DataTable
                        columns={failedColumns}
                        data={failedUploads}
                        pagination={true}
                        pageSize={10}
                        searchable={true}
                    />
                )}
                {activeTab === 'triggers' && (
                    <DataTable
                        columns={triggerColumns}
                        data={pendingTriggers}
                        pagination={true}
                        pageSize={10}
                        searchable={true}
                        onRowClick={(row) => navigate(`/patients/${row.patient_id}`)}
                    />
                )}
            </Card>
        </Section>
    );
};

export default InterraiDashboardPage;
