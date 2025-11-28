import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

/**
 * SSPO Workforce Management Page
 *
 * Displays workforce analytics for SSPO (Subcontracted Service Provider Organizations).
 * Similar to SPO Workforce Management but with SSPO-specific context:
 * - SSPO staff tracked separately (not included in SPO FTE ratio)
 * - Focus on contracted capacity and assignment volume
 * - Staff by role and availability
 *
 * Note: SSPO staff do NOT affect the primary SPO's FTE ratio calculation.
 */
const SspoWorkforcePage = () => {
    const [loading, setLoading] = useState(true);
    const [summary, setSummary] = useState(null);
    const [hhrComplement, setHhrComplement] = useState(null);
    const [satisfaction, setSatisfaction] = useState(null);
    const [staff, setStaff] = useState([]);
    const [staffRoles, setStaffRoles] = useState([]);
    const [employmentTypes, setEmploymentTypes] = useState([]);
    const [assignmentSummary, setAssignmentSummary] = useState(null);
    const [filter, setFilter] = useState({ staff_role_code: '' });
    const [activeTab, setActiveTab] = useState('overview');

    // Fetch all workforce data
    const fetchWorkforceData = useCallback(async () => {
        setLoading(true);
        try {
            const [summaryRes, hhrRes, satRes, assignRes, rolesRes, empTypesRes] = await Promise.all([
                api.get('/v2/workforce/summary'),
                api.get('/v2/workforce/hhr-complement'),
                api.get('/v2/workforce/satisfaction'),
                api.get('/v2/workforce/assignment-summary'),
                api.get('/v2/workforce/metadata/roles'),
                api.get('/v2/workforce/metadata/employment-types'),
            ]);

            setSummary(summaryRes.data.data);
            setHhrComplement(hhrRes.data.data);
            setSatisfaction(satRes.data.data);
            setAssignmentSummary(assignRes.data.data);
            setStaffRoles(rolesRes.data.data || []);
            setEmploymentTypes(empTypesRes.data.data || []);
        } catch (error) {
            console.error('Failed to fetch workforce data:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    // Fetch filtered staff list
    const fetchStaff = useCallback(async () => {
        try {
            const params = new URLSearchParams({
                ...(filter.staff_role_code && { staff_role_code: filter.staff_role_code }),
            });
            const response = await api.get(`/v2/workforce/staff?${params}`);
            setStaff(response.data.data || []);
        } catch (error) {
            console.error('Failed to fetch staff:', error);
        }
    }, [filter]);

    useEffect(() => {
        fetchWorkforceData();
    }, [fetchWorkforceData]);

    useEffect(() => {
        if (activeTab === 'staff') {
            fetchStaff();
        }
    }, [activeTab, filter, fetchStaff]);

    // Get role badge color
    const getRoleBadgeColor = (color) => {
        const colors = {
            blue: 'bg-blue-100 text-blue-800',
            indigo: 'bg-indigo-100 text-indigo-800',
            green: 'bg-emerald-100 text-emerald-800',
            purple: 'bg-purple-100 text-purple-800',
            teal: 'bg-teal-100 text-teal-800',
            gray: 'bg-slate-100 text-slate-600',
        };
        return colors[color] || 'bg-slate-100 text-slate-600';
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <Spinner size="lg" />
            </div>
        );
    }

    const headcount = summary?.headcount || {};
    const capacity = summary?.capacity || {};

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SSPO Workforce</h1>
                    <p className="text-slate-500 text-sm">
                        Contracted staff capacity and assignment tracking
                    </p>
                </div>
                <div className="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full">
                    SSPO Partner Organization
                </div>
            </div>

            {/* Info Banner */}
            <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div className="flex items-start gap-3">
                    <div className="text-purple-500 text-xl">*</div>
                    <div>
                        <div className="font-medium text-purple-800">SSPO Staff Tracking</div>
                        <div className="text-sm text-purple-700 mt-1">
                            As a subcontracted service provider, your staff are tracked separately from the primary SPO's
                            FTE ratio calculation. Your workforce metrics focus on contracted capacity and assignment fulfillment.
                        </div>
                    </div>
                </div>
            </div>

            {/* Tab Navigation */}
            <div className="flex gap-1 bg-slate-100 p-1 rounded-lg w-fit">
                {['overview', 'staff'].map((tab) => (
                    <button
                        key={tab}
                        onClick={() => setActiveTab(tab)}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            activeTab === tab
                                ? 'bg-white shadow text-slate-900'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        {tab.charAt(0).toUpperCase() + tab.slice(1)}
                    </button>
                ))}
            </div>

            {/* Overview Tab */}
            {activeTab === 'overview' && (
                <div className="space-y-6">
                    {/* Key Metrics Row */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Total Staff */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Total Staff</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">{headcount.total || 0}</div>
                            <div className="text-sm text-slate-500 mt-2">Contracted workers</div>
                        </div>

                        {/* Weekly Capacity */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Weekly Capacity</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">{capacity.total_capacity_hours || 0}h</div>
                            <div className="flex items-center gap-2 mt-3">
                                <div className="flex-1 h-2 bg-slate-200 rounded-full">
                                    <div
                                        className="h-2 bg-purple-500 rounded-full"
                                        style={{ width: `${Math.min(100, capacity.utilization_rate || 0)}%` }}
                                    />
                                </div>
                                <span className="text-sm font-medium">{capacity.utilization_rate || 0}%</span>
                            </div>
                        </div>

                        {/* Assignments This Week */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Assignments (Week)</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">
                                {assignmentSummary?.total?.count || 0}
                            </div>
                            <div className="text-sm text-slate-500 mt-2">
                                {assignmentSummary?.total?.hours || 0}h scheduled
                            </div>
                        </div>

                        {/* Staff Satisfaction */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Staff Satisfaction</div>
                            <div className={`text-4xl font-bold mt-1 ${
                                (satisfaction?.satisfaction_rate || 0) >= 95 ? 'text-emerald-600' : 'text-amber-600'
                            }`}>
                                {satisfaction?.satisfaction_rate !== null ? `${satisfaction.satisfaction_rate}%` : 'N/A'}
                            </div>
                            <div className="text-sm text-slate-500 mt-2">
                                {satisfaction?.total_responses || 0} responses
                            </div>
                        </div>
                    </div>

                    {/* HHR Complement Summary */}
                    {hhrComplement && (
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <h3 className="text-lg font-bold text-slate-800 mb-4">Staff by Role</h3>
                            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                                {(hhrComplement.complement || []).filter(r => r.total > 0).map((role) => (
                                    <div key={role.role_code} className="text-center p-3 bg-slate-50 rounded-lg">
                                        <div className={`inline-block px-2 py-1 rounded text-xs font-bold mb-2 ${getRoleBadgeColor(role.badge_color)}`}>
                                            {role.role_code}
                                        </div>
                                        <div className="text-2xl font-bold text-slate-700">{role.total}</div>
                                        <div className="text-xs text-slate-500">{role.role_name}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Assignment Breakdown */}
                    {assignmentSummary && (
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <h3 className="text-lg font-bold text-slate-800 mb-4">
                                Week of {assignmentSummary.week_start} - {assignmentSummary.week_end}
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="p-4 bg-slate-50 rounded-lg">
                                    <div className="text-xs font-bold text-slate-400 uppercase">Total Assignments</div>
                                    <div className="text-2xl font-bold text-slate-700 mt-1">
                                        {assignmentSummary.total?.count || 0}
                                    </div>
                                    <div className="text-sm text-slate-500">
                                        {assignmentSummary.total?.hours || 0} hours
                                    </div>
                                </div>
                                <div className="p-4 bg-emerald-50 rounded-lg">
                                    <div className="text-xs font-bold text-emerald-600 uppercase">Internal Staff</div>
                                    <div className="text-2xl font-bold text-emerald-700 mt-1">
                                        {assignmentSummary.internal?.count || 0}
                                    </div>
                                    <div className="text-sm text-emerald-600">
                                        {assignmentSummary.internal?.hours || 0} hours
                                    </div>
                                </div>
                                <div className="p-4 bg-purple-50 rounded-lg">
                                    <div className="text-xs font-bold text-purple-600 uppercase">From SPO</div>
                                    <div className="text-2xl font-bold text-purple-700 mt-1">
                                        {assignmentSummary.sspo?.count || 0}
                                    </div>
                                    <div className="text-sm text-purple-600">
                                        {assignmentSummary.sspo?.hours || 0} hours
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Staff Tab */}
            {activeTab === 'staff' && (
                <div className="space-y-4">
                    {/* Filters */}
                    <div className="flex gap-4 items-center">
                        <Select
                            value={filter.staff_role_code}
                            onChange={(e) => setFilter(prev => ({ ...prev, staff_role_code: e.target.value }))}
                            options={[
                                { value: '', label: 'All Roles' },
                                ...staffRoles.map(r => ({ value: r.code, label: `${r.code} - ${r.name}` })),
                            ]}
                            placeholder="Filter by role"
                        />
                        <Button
                            variant="secondary"
                            onClick={() => setFilter({ staff_role_code: '' })}
                        >
                            Clear
                        </Button>
                    </div>

                    {/* Staff Table */}
                    <Section title="Staff Directory">
                        <DataTable
                            columns={[
                                { header: 'Name', accessor: 'name' },
                                {
                                    header: 'Role',
                                    accessor: 'staff_role_code',
                                    render: (row) => (
                                        <span className={`px-2 py-1 rounded text-xs font-bold ${getRoleBadgeColor(row.staff_role_badge_color || 'purple')}`}>
                                            {row.staff_role_code || row.organization_role || '-'}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    accessor: 'staff_status',
                                    render: (row) => (
                                        <span className={`px-2 py-1 rounded-full text-xs font-bold ${
                                            row.staff_status === 'active' ? 'bg-emerald-100 text-emerald-800' :
                                            row.staff_status === 'on_leave' ? 'bg-amber-100 text-amber-800' :
                                            'bg-slate-100 text-slate-600'
                                        }`}>
                                            {row.staff_status?.replace('_', ' ').toUpperCase() || 'ACTIVE'}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'Hours/Week',
                                    accessor: 'max_weekly_hours',
                                    render: (row) => `${row.max_weekly_hours || 40}h`,
                                },
                                {
                                    header: 'Utilization',
                                    accessor: 'utilization_rate',
                                    render: (row) => (
                                        <div className="flex items-center gap-2">
                                            <div className="w-16 h-2 bg-slate-200 rounded-full">
                                                <div
                                                    className={`h-2 rounded-full ${row.utilization_rate > 90 ? 'bg-amber-500' : 'bg-purple-500'}`}
                                                    style={{ width: `${Math.min(100, row.utilization_rate || 0)}%` }}
                                                />
                                            </div>
                                            <span className="text-sm">{row.utilization_rate || 0}%</span>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Satisfaction',
                                    accessor: 'job_satisfaction',
                                    render: (row) => (
                                        row.job_satisfaction !== null ? (
                                            <span className={`font-medium ${row.job_satisfaction >= 80 ? 'text-emerald-600' : row.job_satisfaction >= 60 ? 'text-amber-600' : 'text-red-600'}`}>
                                                {row.job_satisfaction}%
                                            </span>
                                        ) : (
                                            <span className="text-slate-400">-</span>
                                        )
                                    ),
                                },
                            ]}
                            data={staff}
                            keyField="id"
                        />
                    </Section>
                </div>
            )}
        </div>
    );
};

export default SspoWorkforcePage;
