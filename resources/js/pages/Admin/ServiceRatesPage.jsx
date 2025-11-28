import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';
import Modal from '../../components/UI/Modal';
import Input from '../../components/UI/Input';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

/**
 * ServiceRatesPage - Admin page for managing service billing rates (rate card)
 *
 * Allows SPO and SSPO admins to:
 * - View system default rates
 * - Create organization-specific rate overrides
 * - Edit and manage their organization's rates
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
const ServiceRatesPage = () => {
    const [rates, setRates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [canEdit, setCanEdit] = useState(false);
    const [organizationId, setOrganizationId] = useState(null);
    const [hasCustomRates, setHasCustomRates] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [selectedRate, setSelectedRate] = useState(null);
    const [filter, setFilter] = useState({ category: '', search: '' });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    // Fetch rate card data
    const fetchRates = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await api.get('/v2/admin/service-rates');
            setRates(response.data.data?.rates || []);
            setCanEdit(response.data.can_edit || false);
            setOrganizationId(response.data.user_organization_id);
            setHasCustomRates(response.data.data?.has_custom_rates || false);
        } catch (err) {
            console.error('Failed to fetch rates:', err);
            setError('Failed to load service rates. Please try again.');
            setRates([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchRates();
    }, [fetchRates]);

    // Handle edit rate
    const handleEditRate = (rate) => {
        setSelectedRate(rate);
        setShowEditModal(true);
    };

    // Handle save rate
    const handleSaveRate = async (rateData) => {
        setSaving(true);
        setError(null);
        try {
            await api.post('/v2/admin/service-rates', rateData);
            await fetchRates();
            setShowEditModal(false);
            setSelectedRate(null);
        } catch (err) {
            console.error('Failed to save rate:', err);
            setError(err.response?.data?.message || 'Failed to save rate');
        } finally {
            setSaving(false);
        }
    };

    // Handle reset to default
    const handleResetToDefault = async (rate) => {
        if (!rate.organization_rate?.id) return;

        if (!window.confirm('Are you sure you want to remove this custom rate and use the system default?')) {
            return;
        }

        try {
            await api.delete(`/v2/admin/service-rates/${rate.organization_rate.id}`);
            await fetchRates();
        } catch (err) {
            console.error('Failed to delete rate:', err);
            setError(err.response?.data?.message || 'Failed to reset rate');
        }
    };

    // Filter rates
    const filteredRates = rates.filter(rate => {
        if (filter.category && rate.service_type_category !== filter.category) {
            return false;
        }
        if (filter.search) {
            const searchLower = filter.search.toLowerCase();
            return (
                rate.service_type_name?.toLowerCase().includes(searchLower) ||
                rate.service_type_code?.toLowerCase().includes(searchLower)
            );
        }
        return true;
    });

    // Get unique categories
    const categories = [...new Set(rates.map(r => r.service_type_category).filter(Boolean))];

    // Format currency
    const formatCurrency = (cents) => {
        if (cents === null || cents === undefined) return '-';
        return `$${(cents / 100).toFixed(2)}`;
    };

    // Get unit label
    const getUnitLabel = (unitType) => {
        const labels = {
            hour: '/hour',
            visit: '/visit',
            month: '/month',
            trip: '/trip',
            call: '/call',
            service: '/service',
            night: '/night',
            block: '/block',
        };
        return labels[unitType] || `/${unitType}`;
    };

    // Get rate status badge
    const getRateStatusBadge = (rate) => {
        if (rate.has_org_override) {
            return (
                <span className="px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                    Custom
                </span>
            );
        }
        return (
            <span className="px-2 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                Default
            </span>
        );
    };

    const columns = [
        {
            header: 'Service',
            accessor: 'service_type_name',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.service_type_name}</div>
                    <div className="text-xs text-slate-500">{row.service_type_code}</div>
                </div>
            )
        },
        {
            header: 'Category',
            accessor: 'service_type_category',
            render: (row) => (
                <span className="text-sm text-slate-600">
                    {row.service_type_category || '-'}
                </span>
            )
        },
        {
            header: 'System Default',
            accessor: 'system_default_rate',
            render: (row) => (
                <div className="text-sm">
                    {row.system_default_rate ? (
                        <>
                            <span className="font-medium">
                                {formatCurrency(row.system_default_rate.rate_cents)}
                            </span>
                            <span className="text-slate-500">
                                {getUnitLabel(row.system_default_rate.unit_type)}
                            </span>
                        </>
                    ) : (
                        <span className="text-slate-400">Not set</span>
                    )}
                </div>
            )
        },
        {
            header: 'Your Rate',
            accessor: 'effective_rate',
            render: (row) => (
                <div className="text-sm">
                    {row.effective_rate ? (
                        <div className="flex items-center gap-2">
                            <span className={`font-bold ${row.has_org_override ? 'text-blue-700' : 'text-slate-700'}`}>
                                {formatCurrency(row.effective_rate.rate_cents)}
                            </span>
                            <span className="text-slate-500">
                                {getUnitLabel(row.effective_rate.unit_type)}
                            </span>
                            {getRateStatusBadge(row)}
                        </div>
                    ) : (
                        <span className="text-slate-400">Not set</span>
                    )}
                </div>
            )
        },
        {
            header: 'Duration',
            accessor: 'default_duration_minutes',
            render: (row) => (
                <span className="text-sm text-slate-600">
                    {row.default_duration_minutes ? `${row.default_duration_minutes} min` : '-'}
                </span>
            )
        },
        {
            header: 'Actions',
            accessor: 'service_type_id',
            render: (row) => (
                <div className="flex gap-2">
                    {canEdit && (
                        <>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => handleEditRate(row)}
                            >
                                {row.has_org_override ? 'Edit' : 'Override'}
                            </Button>
                            {row.has_org_override && (
                                <Button
                                    size="sm"
                                    variant="danger"
                                    onClick={() => handleResetToDefault(row)}
                                >
                                    Reset
                                </Button>
                            )}
                        </>
                    )}
                </div>
            )
        }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Service Rate Card</h1>
                    <p className="text-slate-500 text-sm">
                        Manage billing rates for services. System defaults apply unless overridden.
                    </p>
                </div>
                {canEdit && (
                    <div className="text-sm text-slate-600 bg-slate-100 px-3 py-2 rounded-lg">
                        {hasCustomRates ? (
                            <span className="text-blue-700 font-medium">Using custom rates</span>
                        ) : (
                            <span>Using system defaults</span>
                        )}
                    </div>
                )}
            </div>

            {/* Error Message */}
            {error && (
                <div className="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
                    {error}
                    <button
                        className="ml-4 underline"
                        onClick={() => setError(null)}
                    >
                        Dismiss
                    </button>
                </div>
            )}

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Total Services</div>
                    <div className="text-3xl font-bold text-slate-700">{rates.length}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">With Rates</div>
                    <div className="text-3xl font-bold text-emerald-600">
                        {rates.filter(r => r.effective_rate).length}
                    </div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Custom Overrides</div>
                    <div className="text-3xl font-bold text-blue-600">
                        {rates.filter(r => r.has_org_override).length}
                    </div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Categories</div>
                    <div className="text-3xl font-bold text-slate-700">{categories.length}</div>
                </div>
            </div>

            {/* Filters */}
            <div className="flex gap-4 items-center bg-white p-4 rounded-xl border border-slate-200">
                <Input
                    placeholder="Search services..."
                    value={filter.search}
                    onChange={(e) => setFilter(prev => ({ ...prev, search: e.target.value }))}
                    className="w-64"
                />
                <Select
                    value={filter.category}
                    onChange={(e) => setFilter(prev => ({ ...prev, category: e.target.value }))}
                    options={[
                        { value: '', label: 'All Categories' },
                        ...categories.map(cat => ({ value: cat, label: cat }))
                    ]}
                    placeholder="Filter by category"
                />
                <Button
                    variant="secondary"
                    onClick={() => setFilter({ category: '', search: '' })}
                >
                    Clear
                </Button>
            </div>

            {/* Rates Table */}
            <Section title="Service Rates">
                {loading ? (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : filteredRates.length === 0 ? (
                    <div className="text-center py-12 text-slate-500">
                        No service rates found.
                    </div>
                ) : (
                    <DataTable columns={columns} data={filteredRates} keyField="service_type_id" />
                )}
            </Section>

            {/* Edit Rate Modal */}
            <EditRateModal
                isOpen={showEditModal}
                onClose={() => {
                    setShowEditModal(false);
                    setSelectedRate(null);
                }}
                rate={selectedRate}
                onSave={handleSaveRate}
                saving={saving}
            />
        </div>
    );
};

/**
 * Edit Rate Modal Component
 */
const EditRateModal = ({ isOpen, onClose, rate, onSave, saving }) => {
    const [formData, setFormData] = useState({
        service_type_id: '',
        rate_cents: '',
        unit_type: 'visit',
        effective_from: new Date().toISOString().split('T')[0],
        notes: '',
    });

    // Update form when rate changes
    useEffect(() => {
        if (rate) {
            const currentRate = rate.organization_rate || rate.effective_rate || rate.system_default_rate;
            setFormData({
                service_type_id: rate.service_type_id,
                rate_cents: currentRate?.rate_cents ? (currentRate.rate_cents / 100).toFixed(2) : '',
                unit_type: currentRate?.unit_type || 'visit',
                effective_from: new Date().toISOString().split('T')[0],
                notes: '',
            });
        }
    }, [rate]);

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave({
            service_type_id: formData.service_type_id,
            rate_cents: Math.round(parseFloat(formData.rate_cents) * 100),
            unit_type: formData.unit_type,
            effective_from: formData.effective_from,
            notes: formData.notes || null,
        });
    };

    const unitTypeOptions = [
        { value: 'hour', label: 'Per Hour' },
        { value: 'visit', label: 'Per Visit' },
        { value: 'month', label: 'Per Month' },
        { value: 'trip', label: 'Per Trip' },
        { value: 'call', label: 'Per Call' },
        { value: 'service', label: 'Per Service' },
        { value: 'night', label: 'Per Night' },
        { value: 'block', label: 'Per Block' },
    ];

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={rate?.has_org_override ? 'Edit Custom Rate' : 'Create Custom Rate Override'}
            size="md"
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Service Info */}
                <div className="bg-slate-50 p-4 rounded-lg">
                    <div className="font-medium text-slate-900">{rate?.service_type_name}</div>
                    <div className="text-sm text-slate-500">{rate?.service_type_code}</div>
                    {rate?.system_default_rate && (
                        <div className="text-sm text-slate-600 mt-2">
                            System default: ${(rate.system_default_rate.rate_cents / 100).toFixed(2)}
                            {' '}{rate.system_default_rate.unit_type}
                        </div>
                    )}
                </div>

                {/* Rate Input */}
                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Rate (CAD)"
                        type="number"
                        step="0.01"
                        min="0"
                        value={formData.rate_cents}
                        onChange={(e) => setFormData(prev => ({ ...prev, rate_cents: e.target.value }))}
                        placeholder="0.00"
                        required
                    />
                    <Select
                        label="Unit Type"
                        value={formData.unit_type}
                        onChange={(e) => setFormData(prev => ({ ...prev, unit_type: e.target.value }))}
                        options={unitTypeOptions}
                    />
                </div>

                {/* Effective Date */}
                <Input
                    label="Effective From"
                    type="date"
                    value={formData.effective_from}
                    onChange={(e) => setFormData(prev => ({ ...prev, effective_from: e.target.value }))}
                    required
                />

                {/* Notes */}
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                        Notes (Optional)
                    </label>
                    <textarea
                        value={formData.notes}
                        onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        rows={3}
                        placeholder="Reason for rate change..."
                    />
                </div>

                {/* Actions */}
                <div className="flex gap-3 pt-4 border-t">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onClose}
                        className="flex-1"
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        className="flex-1"
                        disabled={saving || !formData.rate_cents}
                    >
                        {saving ? 'Saving...' : 'Save Rate'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
};

export default ServiceRatesPage;
