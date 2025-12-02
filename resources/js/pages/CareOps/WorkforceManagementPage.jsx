import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

/**
 * SPO Workforce Management Page
 *
 * Displays comprehensive workforce analytics including:
 * - FTE Compliance (headcount-based per RFP Q&A)
 * - HHR Complement breakdown by role and employment type
 * - Staff Satisfaction metrics
 * - Utilization and capacity tracking
 *
 * Per RFP Q&A: FTE ratio = [Full-time direct staff / Total direct staff] x 100%
 * Target: 80% full-time, SSPO staff excluded from ratio
 */
const WorkforceManagementPage = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [summary, setSummary] = useState(null);
    const [fteTrend, setFteTrend] = useState([]);
    const [hhrComplement, setHhrComplement] = useState(null);
    const [satisfaction, setSatisfaction] = useState(null);
    const [staff, setStaff] = useState([]);
    const [staffRoles, setStaffRoles] = useState([]);
    const [employmentTypes, setEmploymentTypes] = useState([]);
    const [filter, setFilter] = useState({ staff_role_code: '', employment_type_code: '' });
    const [activeTab, setActiveTab] = useState('overview');

    // Fetch all workforce data
    const fetchWorkforceData = useCallback(async () => {
        setLoading(true);
        try {
            const [summaryRes, trendRes, hhrRes, satRes, rolesRes, empTypesRes] = await Promise.all([
                api.get('/v2/workforce/summary'),
                api.get('/v2/workforce/fte-trend?weeks=8'),
                api.get('/v2/workforce/hhr-complement'),
                api.get('/v2/workforce/satisfaction'),
                api.get('/v2/workforce/metadata/roles'),
                api.get('/v2/workforce/metadata/employment-types'),
            ]);

            setSummary(summaryRes.data.data);
            setFteTrend(trendRes.data.data || []);
            setHhrComplement(hhrRes.data.data);
            setSatisfaction(satRes.data.data);
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
                ...(filter.employment_type_code && { employment_type_code: filter.employment_type_code }),
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

    // Get FTE band styling
    const getFteBandColor = (band) => {
        switch (band) {
            case 'GREEN': return 'bg-emerald-100 text-emerald-800 border-emerald-300';
            case 'YELLOW': return 'bg-amber-100 text-amber-800 border-amber-300';
            case 'RED': return 'bg-red-100 text-red-800 border-red-300';
            default: return 'bg-slate-100 text-slate-600 border-slate-300';
        }
    };

    // Get employment type badge color
    const getEmpTypeBadgeColor = (color) => {
        const colors = {
            green: 'bg-emerald-100 text-emerald-800',
            blue: 'bg-blue-100 text-blue-800',
            orange: 'bg-amber-100 text-amber-800',
            purple: 'bg-purple-100 text-purple-800',
        };
        return colors[color] || 'bg-slate-100 text-slate-600';
    };

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

    const fteCompliance = summary?.fte_compliance || {};
    const headcount = summary?.headcount || {};
    const capacity = summary?.capacity || {};

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Workforce Management</h1>
                    <p className="text-slate-500 text-sm">
                        HHR complement, FTE compliance, and staff satisfaction metrics
                    </p>
                </div>
                <div className="flex items-center gap-4">
                    <Button variant="primary" onClick={() => navigate('/spo/scheduling')}>
                        Open Scheduler
                    </Button>
                    <div className="text-xs text-slate-400">
                        Last updated: {new Date(summary?.calculated_at).toLocaleString()}
                    </div>
                </div>
            </div>

            {/* Tab Navigation */}
            <div className="flex gap-1 bg-slate-100 p-1 rounded-lg w-fit">
                {['overview', 'staff', 'hhr', 'satisfaction'].map((tab) => (
                    <button
                        key={tab}
                        onClick={() => setActiveTab(tab)}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            activeTab === tab
                                ? 'bg-white shadow text-slate-900'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        {tab === 'hhr' ? 'HHR Complement' : tab.charAt(0).toUpperCase() + tab.slice(1)}
                    </button>
                ))}
            </div>

            {/* Overview Tab */}
            {activeTab === 'overview' && (
                <div className="space-y-6">
                    {/* Key Metrics Row */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* FTE Ratio Card */}
                        <div className={`p-5 rounded-xl border-2 shadow-sm ${getFteBandColor(fteCompliance.band)}`}>
                            <div className="text-xs font-bold uppercase opacity-70">FTE Ratio</div>
                            <div className="text-4xl font-bold mt-1">{fteCompliance.ratio || 0}%</div>
                            <div className="text-sm mt-2 opacity-80">
                                {fteCompliance.full_time_count || 0} FT / {fteCompliance.direct_staff_count || 0} Direct
                            </div>
                            <div className="text-xs mt-1 opacity-60">Target: {fteCompliance.target || 80}%</div>
                        </div>

                        {/* Headcount Card */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Total Headcount</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">{headcount.total || 0}</div>
                            <div className="grid grid-cols-4 gap-1 mt-3 text-xs">
                                <div className="text-center">
                                    <div className="font-bold text-emerald-600">{headcount.full_time || 0}</div>
                                    <div className="text-slate-400">FT</div>
                                </div>
                                <div className="text-center">
                                    <div className="font-bold text-blue-600">{headcount.part_time || 0}</div>
                                    <div className="text-slate-400">PT</div>
                                </div>
                                <div className="text-center">
                                    <div className="font-bold text-amber-600">{headcount.casual || 0}</div>
                                    <div className="text-slate-400">Cas</div>
                                </div>
                                <div className="text-center">
                                    <div className="font-bold text-purple-600">{headcount.sspo || 0}</div>
                                    <div className="text-slate-400">SSPO</div>
                                </div>
                            </div>
                        </div>

                        {/* Capacity Card */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Weekly Capacity</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">{capacity.total_capacity_hours || 0}h</div>
                            <div className="flex items-center gap-2 mt-3">
                                <div className="flex-1 h-2 bg-slate-200 rounded-full">
                                    <div
                                        className="h-2 bg-emerald-500 rounded-full"
                                        style={{ width: `${Math.min(100, capacity.utilization_rate || 0)}%` }}
                                    />
                                </div>
                                <span className="text-sm font-medium">{capacity.utilization_rate || 0}%</span>
                            </div>
                            <div className="text-xs text-slate-400 mt-2">
                                {capacity.internal_hours || 0}h internal | {capacity.sspo_hours || 0}h SSPO
                            </div>
                        </div>

                        {/* Satisfaction Card */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Staff Satisfaction</div>
                            <div className={`text-4xl font-bold mt-1 ${
                                (summary?.satisfaction?.rate || 0) >= 95 ? 'text-emerald-600' : 'text-amber-600'
                            }`}>
                                {summary?.satisfaction?.rate || 'N/A'}%
                            </div>
                            <div className="text-sm text-slate-500 mt-2">
                                Target: {summary?.satisfaction?.target || 95}%
                            </div>
                            <div className="text-xs text-slate-400 mt-1">
                                {summary?.satisfaction?.responses || 0} responses
                            </div>
                        </div>
                    </div>

                    {/* FTE Trend Chart */}
                    {fteTrend.length > 0 && (
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <h3 className="text-sm font-bold text-slate-600 mb-4">FTE Compliance Trend (8 Weeks)</h3>
                            <div className="flex items-end gap-3 h-40">
                                {fteTrend.map((week, idx) => (
                                    <div key={idx} className="flex-1 flex flex-col items-center">
                                        <div
                                            className={`w-full rounded-t transition-all ${getFteBandColor(week.band).split(' ')[0]}`}
                                            style={{ height: `${Math.max(10, (week.fte_ratio || 0))}%` }}
                                            title={`${week.fte_ratio}%`}
                                        />
                                        <div className="text-xs text-slate-400 mt-2">{week.week_label}</div>
                                        <div className="text-xs font-bold">{week.fte_ratio || 0}%</div>
                                    </div>
                                ))}
                            </div>
                            {/* Target line indicator */}
                            <div className="relative h-0 -mt-40 mb-40 pointer-events-none">
                                <div className="absolute w-full border-t-2 border-dashed border-emerald-500" style={{ top: '20%' }} />
                                <div className="absolute right-0 -mt-3 text-xs text-emerald-600 font-medium">80% Target</div>
                            </div>
                            <div className="flex items-center gap-6 mt-4 text-xs text-slate-500">
                                <span className="flex items-center gap-1">
                                    <span className="w-3 h-3 rounded bg-emerald-100 border border-emerald-300" /> Compliant (80%+)
                                </span>
                                <span className="flex items-center gap-1">
                                    <span className="w-3 h-3 rounded bg-amber-100 border border-amber-300" /> At Risk (75-79%)
                                </span>
                                <span className="flex items-center gap-1">
                                    <span className="w-3 h-3 rounded bg-red-100 border border-red-300" /> Non-Compliant (&lt;75%)
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* HHR Complement Tab */}
            {activeTab === 'hhr' && hhrComplement && (
                <div className="space-y-6">
                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                        <h3 className="text-lg font-bold text-slate-800 mb-4">
                            HHR Complement by Role & Employment Type
                        </h3>
                        <p className="text-sm text-slate-500 mb-6">
                            Breakdown of Human Health Resources by worker type and employment category.
                            SSPO staff are tracked separately and excluded from FTE ratio calculation.
                        </p>

                        {/* Employment Type Legend */}
                        <div className="flex gap-4 mb-6 flex-wrap">
                            {employmentTypes.map((empType) => (
                                <span
                                    key={empType.code}
                                    className={`px-3 py-1 rounded-full text-xs font-bold ${getEmpTypeBadgeColor(empType.badge_color)}`}
                                >
                                    {empType.code}: {empType.name}
                                    {!empType.is_direct_staff && ' (Not in FTE)'}
                                </span>
                            ))}
                        </div>

                        {/* HHR Matrix Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="text-left py-3 px-4 font-bold text-slate-600">Role</th>
                                        <th className="text-left py-3 px-4 font-bold text-slate-600">Category</th>
                                        {employmentTypes.map((empType) => (
                                            <th key={empType.code} className="text-center py-3 px-4 font-bold text-slate-600">
                                                {empType.code}
                                            </th>
                                        ))}
                                        <th className="text-center py-3 px-4 font-bold text-slate-600">Total</th>
                                        <th className="text-center py-3 px-4 font-bold text-slate-600">FTE Eligible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(hhrComplement.complement || []).filter(r => r.total > 0).map((role) => (
                                        <tr key={role.role_code} className="border-b border-slate-100 hover:bg-slate-50">
                                            <td className="py-3 px-4">
                                                <span className={`px-2 py-1 rounded text-xs font-bold ${getRoleBadgeColor(role.badge_color)}`}>
                                                    {role.role_code}
                                                </span>
                                                <span className="ml-2 text-slate-700">{role.role_name}</span>
                                            </td>
                                            <td className="py-3 px-4 text-slate-500">{role.category}</td>
                                            {employmentTypes.map((empType) => {
                                                const empData = role.by_employment_type[empType.code] || {};
                                                return (
                                                    <td key={empType.code} className="text-center py-3 px-4">
                                                        {empData.count > 0 ? (
                                                            <span className="font-bold">{empData.count}</span>
                                                        ) : (
                                                            <span className="text-slate-300">-</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                            <td className="text-center py-3 px-4 font-bold">{role.total}</td>
                                            <td className="text-center py-3 px-4">
                                                <span className={role.fte_eligible > 0 ? 'text-emerald-600 font-bold' : 'text-slate-300'}>
                                                    {role.fte_eligible}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="bg-slate-50 font-bold">
                                        <td className="py-3 px-4" colSpan={2}>Total</td>
                                        {employmentTypes.map((empType) => (
                                            <td key={empType.code} className="text-center py-3 px-4">
                                                {hhrComplement.totals?.by_employment_type?.[empType.code] || 0}
                                            </td>
                                        ))}
                                        <td className="text-center py-3 px-4">{hhrComplement.totals?.grand_total || 0}</td>
                                        <td className="text-center py-3 px-4 text-emerald-600">
                                            {hhrComplement.totals?.direct_staff_total || 0}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {/* FTE Summary */}
                        <div className="mt-6 p-4 bg-slate-50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="text-sm font-bold text-slate-600">FTE Ratio Calculation</div>
                                    <div className="text-xs text-slate-500 mt-1">
                                        Full-Time Direct Staff ({hhrComplement.totals?.full_time_total || 0}) /
                                        Total Direct Staff ({hhrComplement.totals?.direct_staff_total || 0}) x 100%
                                    </div>
                                </div>
                                <div className={`px-4 py-2 rounded-lg ${getFteBandColor(hhrComplement.band)}`}>
                                    <div className="text-2xl font-bold">{hhrComplement.fte_ratio || 0}%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Satisfaction Tab */}
            {activeTab === 'satisfaction' && satisfaction && (
                <div className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Overall Satisfaction */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Satisfaction Rate</div>
                            <div className={`text-4xl font-bold mt-1 ${
                                satisfaction.meets_target ? 'text-emerald-600' : 'text-amber-600'
                            }`}>
                                {satisfaction.satisfaction_rate !== null ? `${satisfaction.satisfaction_rate}%` : 'N/A'}
                            </div>
                            <div className="text-sm text-slate-500 mt-2">Target: {satisfaction.target_rate}%</div>
                            {satisfaction.gap_to_target > 0 && (
                                <div className="text-xs text-amber-600 mt-1">
                                    Gap: {satisfaction.gap_to_target.toFixed(1)}%
                                </div>
                            )}
                        </div>

                        {/* Average Score */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Average Satisfaction</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">
                                {satisfaction.average_satisfaction !== null ? satisfaction.average_satisfaction.toFixed(1) : 'N/A'}
                            </div>
                            <div className="text-sm text-slate-500 mt-2">Out of 100</div>
                        </div>

                        {/* Response Count */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div className="text-xs font-bold text-slate-400 uppercase">Survey Responses</div>
                            <div className="text-4xl font-bold text-slate-700 mt-1">{satisfaction.total_responses}</div>
                            {satisfaction.latest_survey_date && (
                                <div className="text-sm text-slate-500 mt-2">
                                    Last: {satisfaction.latest_survey_date}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Distribution Chart */}
                    {satisfaction.distribution && (
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <h3 className="text-lg font-bold text-slate-800 mb-4">Satisfaction Distribution</h3>
                            <div className="space-y-3">
                                {[
                                    { key: 'very_satisfied', label: 'Very Satisfied (90-100)', color: 'bg-emerald-500' },
                                    { key: 'satisfied', label: 'Satisfied (70-89)', color: 'bg-emerald-300' },
                                    { key: 'neutral', label: 'Neutral (50-69)', color: 'bg-amber-400' },
                                    { key: 'dissatisfied', label: 'Dissatisfied (30-49)', color: 'bg-orange-400' },
                                    { key: 'very_dissatisfied', label: 'Very Dissatisfied (<30)', color: 'bg-red-500' },
                                ].map(({ key, label, color }) => {
                                    const count = satisfaction.distribution[key] || 0;
                                    const pct = satisfaction.total_responses > 0
                                        ? (count / satisfaction.total_responses) * 100
                                        : 0;
                                    return (
                                        <div key={key} className="flex items-center gap-4">
                                            <div className="w-40 text-sm text-slate-600">{label}</div>
                                            <div className="flex-1 h-6 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    className={`h-full ${color} transition-all`}
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                            <div className="w-16 text-right text-sm font-medium">
                                                {count} ({pct.toFixed(0)}%)
                                            </div>
                                        </div>
                                    );
                                })}
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
                        <Select
                            value={filter.employment_type_code}
                            onChange={(e) => setFilter(prev => ({ ...prev, employment_type_code: e.target.value }))}
                            options={[
                                { value: '', label: 'All Employment Types' },
                                ...employmentTypes.map(t => ({ value: t.code, label: t.name })),
                            ]}
                            placeholder="Filter by type"
                        />
                        <Button
                            variant="secondary"
                            onClick={() => setFilter({ staff_role_code: '', employment_type_code: '' })}
                        >
                            Clear
                        </Button>
                    </div>

                    {/* Staff Table */}
                    <Section title="Staff Directory">
                        <DataTable
                            compact
                            columns={[
                                {
                                header: 'Name',
                                accessor: 'name',
                                render: (row) => (
                                    <button
                                        onClick={() => navigate(`/staff/${row.id}`)}
                                        className="text-teal-600 hover:text-teal-800 font-medium hover:underline text-left"
                                    >
                                        {row.name}
                                    </button>
                                ),
                            },
                                {
                                    header: 'Role',
                                    accessor: 'staff_role_code',
                                    render: (row) => (
                                        <span className={`px-2 py-1 rounded text-xs font-bold ${getRoleBadgeColor(row.staff_role_badge_color || 'blue')}`}>
                                            {row.staff_role_code || row.organization_role || '-'}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'Employment',
                                    accessor: 'employment_type_code',
                                    render: (row) => (
                                        <span className={`px-2 py-1 rounded-full text-xs font-bold ${getEmpTypeBadgeColor(row.employment_type_badge_color || 'green')}`}>
                                            {row.employment_type_name || row.employment_type_code || '-'}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'FTE Eligible',
                                    accessor: 'is_direct_staff',
                                    render: (row) => (
                                        <span className={row.is_direct_staff ? 'text-emerald-600 font-bold' : 'text-slate-400'}>
                                            {row.is_direct_staff ? 'Yes' : 'No'}
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
                                                    className={`h-2 rounded-full ${row.utilization_rate > 90 ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                                    style={{ width: `${Math.min(100, row.utilization_rate || 0)}%` }}
                                                />
                                            </div>
                                            <span className="text-sm">{row.utilization_rate || 0}%</span>
                                        </div>
                                    ),
                                },
                                {
                                header: 'Schedule',
                                accessor: 'id',
                                render: (row) => (
                                    <button
                                        onClick={() => navigate(`/staff/${row.id}`)}
                                        className="px-3 py-1 text-xs font-medium bg-teal-600 text-white rounded hover:bg-teal-700 transition-colors"
                                    >
                                        Schedule
                                    </button>
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

export default WorkforceManagementPage;
