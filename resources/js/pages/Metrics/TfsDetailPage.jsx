import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Clock, CheckCircle, AlertTriangle, Timer, User, ChevronUp, ChevronDown, Calendar } from 'lucide-react';
import api from '../../services/api';

/**
 * TFS (Time-to-First-Service) Detail Page
 * 
 * Shows detailed breakdown of patients used in TFS calculation:
 * - Summary metrics (average, median, band)
 * - Table of all patients with acceptance and first service times
 * - Status indicators for each patient
 * - Sortable columns
 */
const TfsDetailPage = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [data, setData] = useState(null);
    const [filter, setFilter] = useState('all');
    const [sortConfig, setSortConfig] = useState({ key: 'hours_to_first_service', direction: 'desc' });

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            setLoading(true);
            const response = await api.get('/v2/metrics/tfs/details');
            setData(response.data.data);
            setError(null);
        } catch (err) {
            console.error('Error fetching TFS details:', err);
            setError('Failed to load Time-to-First-Service data');
        } finally {
            setLoading(false);
        }
    };

    const getStatusBadge = (status) => {
        switch (status) {
            case 'within_target':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                        <CheckCircle className="w-3 h-3" />
                        Within Target
                    </span>
                );
            case 'below_standard':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                        <AlertTriangle className="w-3 h-3" />
                        Below Standard
                    </span>
                );
            case 'exceeded_target':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700">
                        <Timer className="w-3 h-3" />
                        Exceeded Target
                    </span>
                );
            case 'awaiting_first_service':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                        <Clock className="w-3 h-3" />
                        Awaiting Service
                    </span>
                );
            default:
                return null;
        }
    };

    const getBandColor = (band) => {
        switch (band) {
            case 'A': return 'text-emerald-600';
            case 'B': return 'text-amber-500';
            case 'C': return 'text-rose-600';
            default: return 'text-slate-600';
        }
    };

    const getBandBadge = (band) => {
        switch (band) {
            case 'A':
                return <span className="px-2 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Meets Target</span>;
            case 'B':
                return <span className="px-2 py-1 rounded text-xs font-bold bg-amber-100 text-amber-700">Below Standard</span>;
            case 'C':
                return <span className="px-2 py-1 rounded text-xs font-bold bg-rose-100 text-rose-700">Exceeded Target</span>;
            default:
                return null;
        }
    };

    // Sorting logic
    const requestSort = (key) => {
        let direction = 'asc';
        if (sortConfig.key === key && sortConfig.direction === 'asc') {
            direction = 'desc';
        }
        setSortConfig({ key, direction });
    };

    const getSortIcon = (columnKey) => {
        if (sortConfig.key !== columnKey) {
            return <ChevronUp className="w-3 h-3 text-slate-300" />;
        }
        return sortConfig.direction === 'asc' 
            ? <ChevronUp className="w-3 h-3 text-sky-600" />
            : <ChevronDown className="w-3 h-3 text-sky-600" />;
    };

    // Filter and sort patients
    const filteredAndSortedPatients = useMemo(() => {
        let patients = data?.patients || [];
        
        // Apply filter
        if (filter !== 'all') {
            if (filter === 'awaiting') {
                patients = patients.filter(p => p.status === 'awaiting_first_service');
            } else {
                patients = patients.filter(p => p.status === filter);
            }
        }

        // Apply sorting
        return [...patients].sort((a, b) => {
            let aVal = a[sortConfig.key];
            let bVal = b[sortConfig.key];

            // Handle null values
            if (aVal === null || aVal === undefined) return sortConfig.direction === 'asc' ? 1 : -1;
            if (bVal === null || bVal === undefined) return sortConfig.direction === 'asc' ? -1 : 1;

            // Handle string comparison
            if (typeof aVal === 'string') {
                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
            }

            if (aVal < bVal) return sortConfig.direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });
    }, [data?.patients, filter, sortConfig]);

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-600"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6">
                <div className="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded">
                    {error}
                </div>
            </div>
        );
    }

    const summary = data?.summary;
    const counts = data?.counts;

    // Column header component
    const SortableHeader = ({ label, sortKey, className = '' }) => (
        <th 
            className={`px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none ${className}`}
            onClick={() => requestSort(sortKey)}
        >
            <div className="flex items-center gap-1">
                {label}
                {getSortIcon(sortKey)}
            </div>
        </th>
    );

    return (
        <div className="p-6 space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between mt-4">
                <div className="flex items-center gap-4">
                    <button 
                        onClick={() => navigate('/care-dashboard')}
                        className="flex items-center gap-2 pl-0 pr-3 py-2 text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                        <span className="text-sm font-medium">Back to Dashboard</span>
                    </button>
                </div>
            </div>
            
            {/* Title */}
            <div>
                <h1 className="text-2xl font-bold text-slate-800">Time-to-First-Service Details</h1>
                <p className="text-slate-500">Patient breakdown for TFS metric calculation</p>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-slate-500 text-xs font-bold uppercase mb-2">Average TFS</div>
                    <div className={`text-3xl font-bold ${getBandColor(summary?.band)}`}>
                        {summary?.formatted_average || '0h'}
                    </div>
                    <div className="mt-2">{getBandBadge(summary?.band)}</div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-slate-500 text-xs font-bold uppercase mb-2">Median TFS</div>
                    <div className="text-3xl font-bold text-slate-700">
                        {summary?.median_hours ? `${summary.median_hours}h` : 'N/A'}
                    </div>
                    <div className="text-xs text-slate-400 mt-2">50th percentile</div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-slate-500 text-xs font-bold uppercase mb-2">First Service Delivered</div>
                    <div className="text-3xl font-bold text-sky-600">
                        {counts?.with_first_service || 0}
                    </div>
                    <div className="text-xs text-slate-400 mt-2">of {counts?.total || 0} patients</div>
                </div>

                <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div className="text-slate-500 text-xs font-bold uppercase mb-2">Awaiting Service</div>
                    <div className="text-3xl font-bold text-slate-600">
                        {counts?.awaiting_first_service || 0}
                    </div>
                    <div className="text-xs text-slate-400 mt-2">pending first visit</div>
                </div>
            </div>

            {/* Status Breakdown */}
            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                <h3 className="font-semibold text-slate-800 mb-3">Status Breakdown</h3>
                <div className="flex flex-wrap gap-3">
                    <button
                        onClick={() => setFilter('all')}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'all' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        All ({counts?.total || 0})
                    </button>
                    <button
                        onClick={() => setFilter('within_target')}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'within_target' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        <CheckCircle className="w-4 h-4 inline mr-1" />
                        Within Target ({counts?.within_target || 0})
                    </button>
                    <button
                        onClick={() => setFilter('below_standard')}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'below_standard' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        <AlertTriangle className="w-4 h-4 inline mr-1" />
                        Below Standard ({counts?.below_standard || 0})
                    </button>
                    <button
                        onClick={() => setFilter('exceeded_target')}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'exceeded_target' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        <Timer className="w-4 h-4 inline mr-1" />
                        Exceeded Target ({counts?.exceeded_target || 0})
                    </button>
                    <button
                        onClick={() => setFilter('awaiting')}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'awaiting' ? 'bg-slate-200 text-slate-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        <Clock className="w-4 h-4 inline mr-1" />
                        Awaiting Service ({counts?.awaiting_first_service || 0})
                    </button>
                </div>
            </div>

            {/* Patient Table */}
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                    <h3 className="font-semibold text-slate-800">Patient Details ({filteredAndSortedPatients.length})</h3>
                    <span className="text-xs text-slate-500">Click column headers to sort</span>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <SortableHeader label="Patient" sortKey="name" />
                                <SortableHeader label="OHIP" sortKey="ohip" />
                                <SortableHeader label="Accepted At" sortKey="accepted_at" />
                                <SortableHeader label="First Service / Scheduled" sortKey="first_service_at" />
                                <SortableHeader label="Service Type" sortKey="first_service_type" />
                                <SortableHeader label="Time to Service" sortKey="hours_to_first_service" />
                                <SortableHeader label="Status" sortKey="status" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {filteredAndSortedPatients.length === 0 ? (
                                <tr>
                                    <td colSpan="7" className="px-4 py-8 text-center text-slate-500">
                                        No patients match the selected filter
                                    </td>
                                </tr>
                            ) : (
                                filteredAndSortedPatients.map((patient, index) => (
                                    <tr key={patient.id || index} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center">
                                                    <User className="w-4 h-4 text-sky-600" />
                                                </div>
                                                <span className="font-medium text-slate-800">{patient.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-600">{patient.ohip || 'N/A'}</td>
                                        <td className="px-4 py-3 text-sm text-slate-600">{patient.accepted_at_formatted}</td>
                                        <td className="px-4 py-3 text-sm">
                                            {patient.has_first_service ? (
                                                <span className="text-slate-600">{patient.first_service_at_formatted}</span>
                                            ) : patient.has_scheduled_service ? (
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="w-4 h-4 text-sky-500" />
                                                    <span className="text-sky-600 font-medium">
                                                        {patient.scheduled_first_service_at_formatted}
                                                    </span>
                                                    <span className="text-xs text-slate-400 ml-1">
                                                        (in {patient.hours_until_scheduled}h)
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-slate-400 italic">Not scheduled</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-600">
                                            {patient.has_first_service ? (
                                                patient.first_service_type
                                            ) : patient.has_scheduled_service ? (
                                                <span className="text-sky-600">{patient.scheduled_first_service_type}</span>
                                            ) : (
                                                <span className="text-slate-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`font-semibold ${
                                                patient.hours_to_first_service === null ? 'text-slate-400' :
                                                patient.hours_to_first_service < 24 ? 'text-emerald-600' :
                                                patient.hours_to_first_service <= 48 ? 'text-amber-500' : 'text-rose-600'
                                            }`}>
                                                {patient.hours_formatted}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">{getStatusBadge(patient.status)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Target Info */}
            <div className="bg-sky-50 border border-sky-200 rounded-lg p-4">
                <h4 className="font-semibold text-sky-800 mb-2">OHaH RFP Target</h4>
                <p className="text-sm text-sky-700">
                    Time-to-First-Service measures the time from referral acceptance to the first completed visit. 
                    Target is <strong>&lt; 24 hours</strong>.
                </p>
                <div className="mt-3 flex flex-wrap gap-2 text-xs">
                    <span className="px-2 py-1 bg-emerald-100 text-emerald-700 rounded">Within Target: &lt;24h</span>
                    <span className="px-2 py-1 bg-amber-100 text-amber-700 rounded">Below Standard: 24-48h</span>
                    <span className="px-2 py-1 bg-rose-100 text-rose-700 rounded">Exceeded Target: &gt;48h</span>
                </div>
            </div>
        </div>
    );
};

export default TfsDetailPage;
