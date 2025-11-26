import React, { useState, useEffect } from 'react';
import api from '../../services/api';

/**
 * STAFF-020: SSPO Capability Management Page
 *
 * Allows SSPO coordinators to manage their service capabilities,
 * including capacity, pricing, and quality metrics for marketplace matching.
 */
const SspoCapabilityPage = () => {
    const [loading, setLoading] = useState(true);
    const [capabilities, setCapabilities] = useState([]);
    const [serviceTypes, setServiceTypes] = useState([]);
    const [coverage, setCoverage] = useState([]);
    const [selectedCapability, setSelectedCapability] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isAddMode, setIsAddMode] = useState(false);
    const [activeTab, setActiveTab] = useState('capabilities'); // 'capabilities' | 'coverage' | 'matching'
    const [matchingResults, setMatchingResults] = useState(null);
    const [matchingForm, setMatchingForm] = useState({
        service_type_id: '',
        patient_id: '',
        estimated_hours: '',
    });

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        setLoading(true);
        try {
            const [capRes, coverageRes, serviceTypesRes] = await Promise.all([
                api.get('/api/v2/sspo-capabilities'),
                api.get('/api/v2/sspo-capabilities/coverage'),
                api.get('/api/v2/service-types'),
            ]);
            setCapabilities(capRes.data.data || []);
            setCoverage(coverageRes.data.data || []);
            setServiceTypes(serviceTypesRes.data.data || serviceTypesRes.data || []);
        } catch (error) {
            console.error('Failed to fetch capabilities', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSaveCapability = async (formData) => {
        try {
            if (isAddMode) {
                await api.post('/api/v2/sspo-capabilities', formData);
            } else {
                await api.put(`/api/v2/sspo-capabilities/${selectedCapability.id}`, formData);
            }
            setIsModalOpen(false);
            setSelectedCapability(null);
            fetchData();
        } catch (error) {
            console.error('Failed to save capability', error);
            alert('Failed to save capability: ' + (error.response?.data?.error || error.message));
        }
    };

    const handleDeleteCapability = async (id) => {
        if (!window.confirm('Are you sure you want to delete this capability?')) return;
        try {
            await api.delete(`/api/v2/sspo-capabilities/${id}`);
            fetchData();
        } catch (error) {
            console.error('Failed to delete capability', error);
        }
    };

    const handleFindMatches = async () => {
        if (!matchingForm.service_type_id || !matchingForm.patient_id) {
            alert('Please select a service type and patient');
            return;
        }
        try {
            const res = await api.post('/api/v2/sspo-capabilities/find-matches', matchingForm);
            setMatchingResults(res.data);
        } catch (error) {
            console.error('Failed to find matches', error);
        }
    };

    const openAddModal = () => {
        setSelectedCapability(null);
        setIsAddMode(true);
        setIsModalOpen(true);
    };

    const openEditModal = (capability) => {
        setSelectedCapability(capability);
        setIsAddMode(false);
        setIsModalOpen(true);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-screen">
                <div className="w-8 h-8 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
            </div>
        );
    }

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SSPO Capability Management</h1>
                    <p className="text-slate-500 text-sm">Manage service capabilities, capacity, and marketplace visibility</p>
                </div>
                <button
                    onClick={openAddModal}
                    className="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg shadow-sm flex items-center gap-2 text-sm font-medium"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Capability
                </button>
            </div>

            {/* Tabs */}
            <div className="border-b border-slate-200">
                <nav className="-mb-px flex space-x-8">
                    {[
                        { id: 'capabilities', label: 'My Capabilities' },
                        { id: 'coverage', label: 'Service Coverage' },
                        { id: 'matching', label: 'Find Matches' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                                activeTab === tab.id
                                    ? 'border-teal-500 text-teal-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Tab Content */}
            {activeTab === 'capabilities' && (
                <CapabilitiesTab
                    capabilities={capabilities}
                    onEdit={openEditModal}
                    onDelete={handleDeleteCapability}
                />
            )}

            {activeTab === 'coverage' && (
                <CoverageTab coverage={coverage} />
            )}

            {activeTab === 'matching' && (
                <MatchingTab
                    serviceTypes={serviceTypes}
                    matchingForm={matchingForm}
                    setMatchingForm={setMatchingForm}
                    onFindMatches={handleFindMatches}
                    results={matchingResults}
                />
            )}

            {/* Add/Edit Modal */}
            {isModalOpen && (
                <CapabilityModal
                    capability={selectedCapability}
                    serviceTypes={serviceTypes}
                    isAddMode={isAddMode}
                    onSave={handleSaveCapability}
                    onClose={() => setIsModalOpen(false)}
                />
            )}
        </div>
    );
};

/**
 * Capabilities List Tab
 */
const CapabilitiesTab = ({ capabilities, onEdit, onDelete }) => {
    if (!capabilities.length) {
        return (
            <div className="bg-white rounded-xl border border-slate-200 p-8 text-center">
                <svg className="w-12 h-12 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <h3 className="text-lg font-medium text-slate-900 mb-2">No Capabilities Configured</h3>
                <p className="text-slate-500 mb-4">Add your first service capability to start receiving marketplace requests.</p>
            </div>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {capabilities.map((cap) => (
                <div key={cap.id} className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-4 border-b border-slate-100">
                        <div className="flex justify-between items-start">
                            <div>
                                <h3 className="font-bold text-slate-900">{cap.service_type_name}</h3>
                                <p className="text-xs text-slate-500">{cap.service_type_code}</p>
                            </div>
                            <span className={`px-2 py-1 text-xs font-bold rounded-full ${
                                cap.is_active && cap.is_valid
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-slate-100 text-slate-600'
                            }`}>
                                {cap.is_active && cap.is_valid ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>

                    <div className="p-4 space-y-3">
                        {/* Capacity */}
                        <div>
                            <div className="flex justify-between text-xs text-slate-500 mb-1">
                                <span>Weekly Capacity</span>
                                <span>{cap.current_utilization_hours || 0}/{cap.max_weekly_hours || 0} hrs</span>
                            </div>
                            <div className="w-full bg-slate-100 rounded-full h-2">
                                <div
                                    className={`h-2 rounded-full transition-all ${
                                        cap.utilization_rate > 80 ? 'bg-rose-500' :
                                        cap.utilization_rate > 60 ? 'bg-amber-500' : 'bg-emerald-500'
                                    }`}
                                    style={{ width: `${Math.min(100, cap.utilization_rate || 0)}%` }}
                                />
                            </div>
                        </div>

                        {/* Metrics */}
                        <div className="grid grid-cols-3 gap-2 text-center">
                            <div className="bg-slate-50 rounded-lg p-2">
                                <div className="text-lg font-bold text-slate-900">{cap.quality_score || '-'}</div>
                                <div className="text-xs text-slate-500">Quality</div>
                            </div>
                            <div className="bg-slate-50 rounded-lg p-2">
                                <div className="text-lg font-bold text-slate-900">{cap.acceptance_rate || '-'}%</div>
                                <div className="text-xs text-slate-500">Accept</div>
                            </div>
                            <div className="bg-slate-50 rounded-lg p-2">
                                <div className="text-lg font-bold text-slate-900">{cap.completion_rate || '-'}%</div>
                                <div className="text-xs text-slate-500">Complete</div>
                            </div>
                        </div>

                        {/* Pricing */}
                        <div className="flex justify-between items-center text-sm">
                            <span className="text-slate-500">Pricing</span>
                            <span className="font-medium text-slate-900">
                                {cap.hourly_rate ? `$${cap.hourly_rate}/hr` : ''}
                                {cap.hourly_rate && cap.visit_rate ? ' | ' : ''}
                                {cap.visit_rate ? `$${cap.visit_rate}/visit` : ''}
                                {!cap.hourly_rate && !cap.visit_rate ? 'Not set' : ''}
                            </span>
                        </div>

                        {/* Special Capabilities */}
                        <div className="flex flex-wrap gap-1">
                            {cap.can_handle_dementia && (
                                <span className="px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded">Dementia</span>
                            )}
                            {cap.can_handle_palliative && (
                                <span className="px-2 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded">Palliative</span>
                            )}
                            {cap.can_handle_complex_care && (
                                <span className="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">Complex Care</span>
                            )}
                            {cap.bilingual_french && (
                                <span className="px-2 py-0.5 text-xs bg-teal-100 text-teal-700 rounded">French</span>
                            )}
                        </div>
                    </div>

                    <div className="px-4 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                        <button
                            onClick={() => onEdit(cap)}
                            className="text-sm text-teal-600 hover:text-teal-800 font-medium"
                        >
                            Edit
                        </button>
                        <button
                            onClick={() => onDelete(cap.id)}
                            className="text-sm text-rose-600 hover:text-rose-800 font-medium"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
};

/**
 * Service Coverage Tab
 */
const CoverageTab = ({ coverage }) => {
    return (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100">
                <h3 className="font-bold text-slate-800">Service Type Coverage Summary</h3>
                <p className="text-xs text-slate-500">Overview of SSPO coverage across all service types</p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Service Type</th>
                            <th className="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Category</th>
                            <th className="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase">SSPOs</th>
                            <th className="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase">Weekly Capacity</th>
                            <th className="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase">Avg Quality</th>
                            <th className="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase">Avg Rate</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {coverage.map((st) => (
                            <tr key={st.id} className="hover:bg-slate-50">
                                <td className="px-6 py-4">
                                    <div className="font-medium text-slate-900">{st.name}</div>
                                    <div className="text-xs text-slate-500">{st.code}</div>
                                </td>
                                <td className="px-6 py-4 text-sm text-slate-600">{st.category}</td>
                                <td className="px-6 py-4 text-center">
                                    <span className={`px-2 py-1 text-xs font-bold rounded-full ${
                                        st.sspo_count > 3 ? 'bg-emerald-100 text-emerald-700' :
                                        st.sspo_count > 0 ? 'bg-amber-100 text-amber-700' :
                                        'bg-slate-100 text-slate-500'
                                    }`}>
                                        {st.sspo_count}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-center text-sm text-slate-600">
                                    {st.total_weekly_capacity || 0} hrs
                                </td>
                                <td className="px-6 py-4 text-center">
                                    {st.avg_quality_score > 0 ? (
                                        <span className={`text-sm font-medium ${
                                            st.avg_quality_score >= 80 ? 'text-emerald-600' :
                                            st.avg_quality_score >= 60 ? 'text-amber-600' : 'text-rose-600'
                                        }`}>
                                            {st.avg_quality_score}
                                        </span>
                                    ) : (
                                        <span className="text-slate-400">-</span>
                                    )}
                                </td>
                                <td className="px-6 py-4 text-center text-sm text-slate-600">
                                    {st.avg_hourly_rate > 0 ? `$${st.avg_hourly_rate}` : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

/**
 * Marketplace Matching Tab
 */
const MatchingTab = ({ serviceTypes, matchingForm, setMatchingForm, onFindMatches, results }) => {
    return (
        <div className="space-y-6">
            {/* Search Form */}
            <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h3 className="font-bold text-slate-800 mb-4">Find Matching SSPOs</h3>
                <div className="grid md:grid-cols-4 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Service Type</label>
                        <select
                            value={matchingForm.service_type_id}
                            onChange={(e) => setMatchingForm({ ...matchingForm, service_type_id: e.target.value })}
                            className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"
                        >
                            <option value="">Select service type</option>
                            {serviceTypes.map((st) => (
                                <option key={st.id} value={st.id}>{st.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Patient ID</label>
                        <input
                            type="text"
                            value={matchingForm.patient_id}
                            onChange={(e) => setMatchingForm({ ...matchingForm, patient_id: e.target.value })}
                            placeholder="Enter patient ID"
                            className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Estimated Hours</label>
                        <input
                            type="number"
                            step="0.5"
                            value={matchingForm.estimated_hours}
                            onChange={(e) => setMatchingForm({ ...matchingForm, estimated_hours: e.target.value })}
                            placeholder="e.g., 2.5"
                            className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="flex items-end">
                        <button
                            onClick={onFindMatches}
                            className="w-full bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
                        >
                            Find Matches
                        </button>
                    </div>
                </div>
            </div>

            {/* Results */}
            {results && (
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <h3 className="font-bold text-slate-800">Matching SSPOs</h3>
                            <p className="text-xs text-slate-500">
                                {results.meta?.total_matches || 0} matches for {results.meta?.service_type}
                            </p>
                        </div>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {results.data?.length > 0 ? (
                            results.data.map((match, idx) => (
                                <div key={idx} className="p-4 hover:bg-slate-50">
                                    <div className="flex justify-between items-start mb-3">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h4 className="font-bold text-slate-900">{match.sspo_name}</h4>
                                                {match.is_preferred && (
                                                    <span className="px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded">Preferred</span>
                                                )}
                                                {idx === 0 && (
                                                    <span className="px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded">Best Match</span>
                                                )}
                                            </div>
                                            <p className="text-sm text-slate-500">{match.service_type}</p>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-2xl font-bold text-teal-600">{match.overall_score}</div>
                                            <div className="text-xs text-slate-500">Match Score</div>
                                        </div>
                                    </div>

                                    {/* Score breakdown */}
                                    <div className="grid grid-cols-5 gap-2 mb-3">
                                        {Object.entries(match.scores || {}).filter(([k]) => k !== 'overall').map(([key, value]) => (
                                            <div key={key} className="bg-slate-50 rounded p-2 text-center">
                                                <div className="text-sm font-bold text-slate-700">{value}</div>
                                                <div className="text-xs text-slate-500 capitalize">{key.replace('_', ' ')}</div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Details */}
                                    <div className="flex flex-wrap gap-4 text-sm text-slate-600">
                                        <span>Available: {match.available_hours} hrs</span>
                                        <span>Quality: {match.quality_score}</span>
                                        <span>Rate: ${match.hourly_rate || match.visit_rate}/hr</span>
                                        {match.estimated_cost && <span>Est. Cost: ${match.estimated_cost}</span>}
                                    </div>

                                    {/* Warnings */}
                                    {match.warnings?.length > 0 && (
                                        <div className="mt-2 space-y-1">
                                            {match.warnings.map((w, i) => (
                                                <div key={i} className="text-xs text-amber-600 flex items-center gap-1">
                                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                    {w.message}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))
                        ) : (
                            <div className="p-8 text-center text-slate-500">
                                No matching SSPOs found for the specified criteria.
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

/**
 * Add/Edit Capability Modal
 */
const CapabilityModal = ({ capability, serviceTypes, isAddMode, onSave, onClose }) => {
    const [form, setForm] = useState({
        sspo_id: capability?.sspo_id || '',
        service_type_id: capability?.service_type_id || '',
        is_active: capability?.is_active ?? true,
        max_weekly_hours: capability?.max_weekly_hours || '',
        min_notice_hours: capability?.min_notice_hours || 24,
        hourly_rate: capability?.hourly_rate || '',
        visit_rate: capability?.visit_rate || '',
        available_days: capability?.available_days || [1, 2, 3, 4, 5],
        earliest_start_time: capability?.earliest_start_time || '08:00',
        latest_end_time: capability?.latest_end_time || '20:00',
        can_handle_dementia: capability?.can_handle_dementia || false,
        can_handle_palliative: capability?.can_handle_palliative || false,
        can_handle_complex_care: capability?.can_handle_complex_care || false,
        bilingual_french: capability?.bilingual_french || false,
        available_staff_count: capability?.available_staff_count || '',
    });

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    const toggleDay = (day) => {
        const days = form.available_days || [];
        if (days.includes(day)) {
            setForm({ ...form, available_days: days.filter(d => d !== day) });
        } else {
            setForm({ ...form, available_days: [...days, day].sort() });
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave(form);
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white">
                    <h2 className="text-lg font-bold text-slate-900">
                        {isAddMode ? 'Add Capability' : 'Edit Capability'}
                    </h2>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Service Type */}
                    {isAddMode && (
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Service Type</label>
                            <select
                                value={form.service_type_id}
                                onChange={(e) => setForm({ ...form, service_type_id: e.target.value })}
                                required
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            >
                                <option value="">Select service type</option>
                                {serviceTypes.map((st) => (
                                    <option key={st.id} value={st.id}>{st.name} ({st.code})</option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Active Status */}
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="is_active"
                            checked={form.is_active}
                            onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                            className="rounded border-slate-300"
                        />
                        <label htmlFor="is_active" className="text-sm font-medium text-slate-700">
                            Active in Marketplace
                        </label>
                    </div>

                    {/* Capacity */}
                    <div className="grid md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Max Weekly Hours</label>
                            <input
                                type="number"
                                value={form.max_weekly_hours}
                                onChange={(e) => setForm({ ...form, max_weekly_hours: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Min Notice (hours)</label>
                            <input
                                type="number"
                                value={form.min_notice_hours}
                                onChange={(e) => setForm({ ...form, min_notice_hours: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Available Staff</label>
                            <input
                                type="number"
                                value={form.available_staff_count}
                                onChange={(e) => setForm({ ...form, available_staff_count: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                    </div>

                    {/* Pricing */}
                    <div className="grid md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Hourly Rate ($)</label>
                            <input
                                type="number"
                                step="0.01"
                                value={form.hourly_rate}
                                onChange={(e) => setForm({ ...form, hourly_rate: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Visit Rate ($)</label>
                            <input
                                type="number"
                                step="0.01"
                                value={form.visit_rate}
                                onChange={(e) => setForm({ ...form, visit_rate: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                    </div>

                    {/* Schedule */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-2">Available Days</label>
                        <div className="flex gap-2">
                            {dayNames.map((name, idx) => (
                                <button
                                    key={idx}
                                    type="button"
                                    onClick={() => toggleDay(idx)}
                                    className={`w-10 h-10 rounded-lg text-sm font-medium transition-colors ${
                                        form.available_days?.includes(idx)
                                            ? 'bg-teal-600 text-white'
                                            : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                                    }`}
                                >
                                    {name}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Earliest Start</label>
                            <input
                                type="time"
                                value={form.earliest_start_time}
                                onChange={(e) => setForm({ ...form, earliest_start_time: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Latest End</label>
                            <input
                                type="time"
                                value={form.latest_end_time}
                                onChange={(e) => setForm({ ...form, latest_end_time: e.target.value })}
                                className="w-full border border-slate-200 rounded-lg px-3 py-2"
                            />
                        </div>
                    </div>

                    {/* Special Capabilities */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-2">Special Capabilities</label>
                        <div className="grid md:grid-cols-2 gap-3">
                            {[
                                { key: 'can_handle_dementia', label: 'Dementia Care' },
                                { key: 'can_handle_palliative', label: 'Palliative Care' },
                                { key: 'can_handle_complex_care', label: 'Complex Care' },
                                { key: 'bilingual_french', label: 'Bilingual (French)' },
                            ].map(({ key, label }) => (
                                <div key={key} className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id={key}
                                        checked={form[key]}
                                        onChange={(e) => setForm({ ...form, [key]: e.target.checked })}
                                        className="rounded border-slate-300"
                                    />
                                    <label htmlFor={key} className="text-sm text-slate-700">{label}</label>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-lg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg"
                        >
                            {isAddMode ? 'Add Capability' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default SspoCapabilityPage;
