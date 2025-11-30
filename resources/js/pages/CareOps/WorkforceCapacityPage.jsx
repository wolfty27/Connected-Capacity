import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

/**
 * Workforce Capacity Dashboard
 *
 * Displays available capacity vs required care hours with breakdowns by:
 * - Role (RN, RPN, PSW, OT, PT, etc.)
 * - Service type (Nursing, Personal Support, etc.)
 * - Provider type (SPO internal vs SSPO contracted)
 *
 * Net Capacity = Available Hours - Required Hours - Travel Overhead
 */
const WorkforceCapacityPage = () => {
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();

    const [loading, setLoading] = useState(true);
    const [capacityData, setCapacityData] = useState(null);
    const [meta, setMeta] = useState(null);

    // Filters
    const [periodType, setPeriodType] = useState(searchParams.get('period_type') || 'week');
    const [providerType, setProviderType] = useState(searchParams.get('provider_type') || '');
    const [forecastWeeks, setForecastWeeks] = useState(parseInt(searchParams.get('forecast_weeks') || '4'));

    // Fetch capacity data
    const fetchCapacityData = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                period_type: periodType,
                forecast_weeks: forecastWeeks.toString(),
            });
            if (providerType) {
                params.append('provider_type', providerType);
            }

            const response = await api.get(`/v2/workforce/capacity?${params}`);
            setCapacityData(response.data.data);
            setMeta(response.data.meta);
        } catch (error) {
            console.error('Failed to fetch capacity data:', error);
        } finally {
            setLoading(false);
        }
    }, [periodType, providerType, forecastWeeks]);

    useEffect(() => {
        fetchCapacityData();
    }, [fetchCapacityData]);

    // Update URL when filters change
    useEffect(() => {
        const params = new URLSearchParams();
        if (periodType !== 'week') params.set('period_type', periodType);
        if (providerType) params.set('provider_type', providerType);
        if (forecastWeeks !== 4) params.set('forecast_weeks', forecastWeeks.toString());
        setSearchParams(params, { replace: true });
    }, [periodType, providerType, forecastWeeks, setSearchParams]);

    // Get capacity status styling
    const getCapacityStatusColor = (status) => {
        switch (status) {
            case 'surplus':
                return 'bg-emerald-100 text-emerald-800 border-emerald-300';
            case 'balanced':
                return 'bg-blue-100 text-blue-800 border-blue-300';
            case 'shortage':
                return 'bg-amber-100 text-amber-800 border-amber-300';
            case 'critical':
                return 'bg-red-100 text-red-800 border-red-300';
            default:
                return 'bg-slate-100 text-slate-600 border-slate-300';
        }
    };

    // Get capacity bar color
    const getCapacityBarColor = (netCapacity) => {
        if (netCapacity >= 20) return 'bg-emerald-500';
        if (netCapacity >= 0) return 'bg-blue-500';
        if (netCapacity >= -20) return 'bg-amber-500';
        return 'bg-red-500';
    };

    // Get role badge color
    const getRoleBadgeColor = (roleCode) => {
        const colors = {
            'RN': 'bg-blue-100 text-blue-800',
            'RPN': 'bg-indigo-100 text-indigo-800',
            'PSW': 'bg-emerald-100 text-emerald-800',
            'OT': 'bg-purple-100 text-purple-800',
            'PT': 'bg-teal-100 text-teal-800',
            'SW': 'bg-pink-100 text-pink-800',
            'DIET': 'bg-orange-100 text-orange-800',
        };
        return colors[roleCode] || 'bg-slate-100 text-slate-600';
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <Spinner size="lg" />
            </div>
        );
    }

    const snapshot = capacityData?.snapshot || {};
    const summary = snapshot.summary || {};
    const availableByRole = snapshot.available_by_role || [];
    const requiredByService = snapshot.required_by_service || [];
    const scheduledByService = snapshot.scheduled_by_service || [];
    const forecast = capacityData?.forecast || [];
    const providerComparison = capacityData?.provider_comparison || null;

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Workforce Capacity</h1>
                    <p className="text-slate-500 text-sm">
                        Available capacity vs required care hours by role, service type, and provider
                    </p>
                </div>
                <div className="flex items-center gap-4">
                    <Button variant="secondary" onClick={() => navigate('/workforce')}>
                        Workforce Management
                    </Button>
                    <Button variant="primary" onClick={() => navigate('/spo/scheduling')}>
                        Open Scheduler
                    </Button>
                </div>
            </div>

            {/* Filters */}
            <div className="flex gap-4 items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <Select
                    value={periodType}
                    onChange={(e) => setPeriodType(e.target.value)}
                    options={[
                        { value: 'week', label: 'Weekly View' },
                        { value: 'month', label: 'Monthly View' },
                    ]}
                    className="w-40"
                />
                <Select
                    value={providerType}
                    onChange={(e) => setProviderType(e.target.value)}
                    options={[
                        { value: '', label: 'All Providers' },
                        { value: 'spo', label: 'SPO Internal Only' },
                        { value: 'sspo', label: 'SSPO Only' },
                    ]}
                    className="w-48"
                />
                <Select
                    value={forecastWeeks.toString()}
                    onChange={(e) => setForecastWeeks(parseInt(e.target.value))}
                    options={[
                        { value: '0', label: 'No Forecast' },
                        { value: '2', label: '2 Week Forecast' },
                        { value: '4', label: '4 Week Forecast' },
                        { value: '8', label: '8 Week Forecast' },
                    ]}
                    className="w-44"
                />
                <div className="flex-1" />
                <div className="text-xs text-slate-400">
                    Period: {meta?.start_date} to {meta?.end_date}
                </div>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                {/* Available Hours */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Available Hours</div>
                    <div className="text-3xl font-bold text-emerald-600 mt-1">
                        {summary.available_hours?.toFixed(1) || 0}h
                    </div>
                    <div className="text-xs text-slate-400 mt-2">
                        {snapshot.staff_count || 0} staff members
                    </div>
                </div>

                {/* Required Hours */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Required Hours</div>
                    <div className="text-3xl font-bold text-blue-600 mt-1">
                        {summary.required_hours?.toFixed(1) || 0}h
                    </div>
                    <div className="text-xs text-slate-400 mt-2">
                        {snapshot.patient_count || 0} patients
                    </div>
                </div>

                {/* Scheduled Hours */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Scheduled Hours</div>
                    <div className="text-3xl font-bold text-purple-600 mt-1">
                        {summary.scheduled_hours?.toFixed(1) || 0}h
                    </div>
                    <div className="text-xs text-slate-400 mt-2">
                        {summary.required_hours > 0
                            ? `${((summary.scheduled_hours / summary.required_hours) * 100).toFixed(0)}% of required`
                            : 'No requirements'}
                    </div>
                </div>

                {/* Travel Overhead */}
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Travel Overhead</div>
                    <div className="text-3xl font-bold text-amber-600 mt-1">
                        {summary.travel_overhead?.toFixed(1) || 0}h
                    </div>
                    <div className="text-xs text-slate-400 mt-2">
                        {meta?.default_travel_minutes || 30} min/visit
                    </div>
                </div>

                {/* Net Capacity */}
                <div className={`p-5 rounded-xl border-2 shadow-sm ${getCapacityStatusColor(summary.status)}`}>
                    <div className="text-xs font-bold uppercase opacity-70">Net Capacity</div>
                    <div className="text-3xl font-bold mt-1">
                        {summary.net_capacity?.toFixed(1) || 0}h
                    </div>
                    <div className="text-xs mt-2 opacity-80 capitalize">
                        Status: {summary.status || 'unknown'}
                    </div>
                </div>
            </div>

            {/* SPO vs SSPO Comparison */}
            {providerComparison && (
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h3 className="text-sm font-bold text-slate-600 mb-4">Provider Type Comparison</h3>
                    <div className="grid grid-cols-2 gap-6">
                        {/* SPO */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-bold text-slate-700">SPO Internal Staff</span>
                                <span className={`px-2 py-1 rounded text-xs font-bold ${getCapacityStatusColor(providerComparison.spo?.summary?.status)}`}>
                                    {providerComparison.spo?.summary?.status || 'N/A'}
                                </span>
                            </div>
                            <div className="grid grid-cols-4 gap-2 text-center">
                                <div>
                                    <div className="text-lg font-bold text-emerald-600">
                                        {providerComparison.spo?.summary?.available_hours?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Available</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-blue-600">
                                        {providerComparison.spo?.summary?.required_hours?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Required</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-purple-600">
                                        {providerComparison.spo?.summary?.scheduled_hours?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Scheduled</div>
                                </div>
                                <div>
                                    <div className={`text-lg font-bold ${providerComparison.spo?.summary?.net_capacity >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                        {providerComparison.spo?.summary?.net_capacity?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Net</div>
                                </div>
                            </div>
                        </div>

                        {/* SSPO */}
                        <div className="space-y-3 border-l pl-6">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-bold text-slate-700">SSPO Contracted Staff</span>
                                <span className={`px-2 py-1 rounded text-xs font-bold ${getCapacityStatusColor(providerComparison.sspo?.summary?.status)}`}>
                                    {providerComparison.sspo?.summary?.status || 'N/A'}
                                </span>
                            </div>
                            <div className="grid grid-cols-4 gap-2 text-center">
                                <div>
                                    <div className="text-lg font-bold text-emerald-600">
                                        {providerComparison.sspo?.summary?.available_hours?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Available</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-blue-600">
                                        {providerComparison.sspo?.summary?.required_hours?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Required</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-purple-600">
                                        {providerComparison.sspo?.summary?.scheduled_hours?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Scheduled</div>
                                </div>
                                <div>
                                    <div className={`text-lg font-bold ${providerComparison.sspo?.summary?.net_capacity >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                        {providerComparison.sspo?.summary?.net_capacity?.toFixed(0) || 0}h
                                    </div>
                                    <div className="text-xs text-slate-400">Net</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Capacity Forecast */}
            {forecast.length > 0 && (
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h3 className="text-sm font-bold text-slate-600 mb-4">Capacity Forecast ({forecast.length} Weeks)</h3>
                    <div className="flex items-end gap-3 h-40">
                        {forecast.map((week, idx) => {
                            const netCapacity = week.summary?.net_capacity || 0;
                            const maxCapacity = Math.max(...forecast.map(w => Math.abs(w.summary?.net_capacity || 0)), 50);
                            const barHeight = Math.min(100, (Math.abs(netCapacity) / maxCapacity) * 100);

                            return (
                                <div key={idx} className="flex-1 flex flex-col items-center">
                                    <div className="relative w-full h-32 flex items-end justify-center">
                                        <div
                                            className={`w-full rounded-t transition-all ${getCapacityBarColor(netCapacity)}`}
                                            style={{ height: `${Math.max(10, barHeight)}%` }}
                                            title={`${netCapacity.toFixed(1)}h`}
                                        />
                                    </div>
                                    <div className="text-xs text-slate-400 mt-2">{week.week_label}</div>
                                    <div className={`text-xs font-bold ${netCapacity >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                        {netCapacity >= 0 ? '+' : ''}{netCapacity.toFixed(0)}h
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <div className="flex items-center gap-6 mt-4 text-xs text-slate-500">
                        <span className="flex items-center gap-1">
                            <span className="w-3 h-3 rounded bg-emerald-500" /> Surplus (20h+)
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-3 h-3 rounded bg-blue-500" /> Balanced (0-20h)
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-3 h-3 rounded bg-amber-500" /> Shortage (0 to -20h)
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-3 h-3 rounded bg-red-500" /> Critical (-20h+)
                        </span>
                    </div>
                </div>
            )}

            {/* Breakdowns Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Available Capacity by Role */}
                <Section title="Available Capacity by Role">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200">
                                    <th className="text-left py-3 px-4 font-bold text-slate-600">Role</th>
                                    <th className="text-right py-3 px-4 font-bold text-slate-600">Staff</th>
                                    <th className="text-right py-3 px-4 font-bold text-slate-600">Available Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                {availableByRole.length > 0 ? (
                                    availableByRole.map((role) => (
                                        <tr key={role.role_code} className="border-b border-slate-100 hover:bg-slate-50">
                                            <td className="py-3 px-4">
                                                <span className={`px-2 py-1 rounded text-xs font-bold ${getRoleBadgeColor(role.role_code)}`}>
                                                    {role.role_code}
                                                </span>
                                                <span className="ml-2 text-slate-700">{role.role_name}</span>
                                            </td>
                                            <td className="text-right py-3 px-4 text-slate-600">
                                                {role.staff_count}
                                            </td>
                                            <td className="text-right py-3 px-4 font-bold text-emerald-600">
                                                {role.total_hours?.toFixed(1)}h
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={3} className="py-6 text-center text-slate-400">
                                            No staff availability data
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            {availableByRole.length > 0 && (
                                <tfoot>
                                    <tr className="bg-slate-50 font-bold">
                                        <td className="py-3 px-4">Total</td>
                                        <td className="text-right py-3 px-4">
                                            {availableByRole.reduce((sum, r) => sum + (r.staff_count || 0), 0)}
                                        </td>
                                        <td className="text-right py-3 px-4 text-emerald-600">
                                            {availableByRole.reduce((sum, r) => sum + (r.total_hours || 0), 0).toFixed(1)}h
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </Section>

                {/* Required Care by Service */}
                <Section title="Required Care by Service Type">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200">
                                    <th className="text-left py-3 px-4 font-bold text-slate-600">Service Type</th>
                                    <th className="text-right py-3 px-4 font-bold text-slate-600">Required</th>
                                    <th className="text-right py-3 px-4 font-bold text-slate-600">Scheduled</th>
                                    <th className="text-right py-3 px-4 font-bold text-slate-600">Gap</th>
                                </tr>
                            </thead>
                            <tbody>
                                {requiredByService.length > 0 ? (
                                    requiredByService.map((service) => {
                                        const scheduledData = scheduledByService.find(s => s.service_type_id === service.service_type_id);
                                        const scheduledHours = scheduledData?.total_hours || 0;
                                        const gap = service.total_hours - scheduledHours;

                                        return (
                                            <tr key={service.service_type_id} className="border-b border-slate-100 hover:bg-slate-50">
                                                <td className="py-3 px-4 text-slate-700">
                                                    {service.service_type_name}
                                                </td>
                                                <td className="text-right py-3 px-4 text-blue-600">
                                                    {service.total_hours?.toFixed(1)}h
                                                </td>
                                                <td className="text-right py-3 px-4 text-purple-600">
                                                    {scheduledHours.toFixed(1)}h
                                                </td>
                                                <td className={`text-right py-3 px-4 font-bold ${gap > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
                                                    {gap > 0 ? `-${gap.toFixed(1)}h` : 'OK'}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="py-6 text-center text-slate-400">
                                            No care requirements
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            {requiredByService.length > 0 && (
                                <tfoot>
                                    <tr className="bg-slate-50 font-bold">
                                        <td className="py-3 px-4">Total</td>
                                        <td className="text-right py-3 px-4 text-blue-600">
                                            {requiredByService.reduce((sum, s) => sum + (s.total_hours || 0), 0).toFixed(1)}h
                                        </td>
                                        <td className="text-right py-3 px-4 text-purple-600">
                                            {scheduledByService.reduce((sum, s) => sum + (s.total_hours || 0), 0).toFixed(1)}h
                                        </td>
                                        <td className="text-right py-3 px-4">-</td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </Section>
            </div>

            {/* Capacity Formula Info */}
            <div className="bg-slate-50 p-4 rounded-lg text-sm text-slate-600">
                <div className="font-bold mb-2">Capacity Calculation</div>
                <div className="text-xs space-y-1">
                    <div><strong>Net Capacity</strong> = Available Hours - Required Hours - Travel Overhead</div>
                    <div><strong>Travel Overhead</strong> = Number of Scheduled Visits x {meta?.default_travel_minutes || 30} minutes</div>
                    <div><strong>Status</strong>: Surplus (20h+), Balanced (0-20h), Shortage (0 to -20h), Critical (-20h deficit)</div>
                </div>
            </div>
        </div>
    );
};

export default WorkforceCapacityPage;
