import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Card from '../../components/UI/Card';
import Spinner from '../../components/UI/Spinner';

/**
 * SSPOCard - Displays an individual SSPO partner in a card format
 */
const SSPOCard = ({ partner, onViewProfile, onAssign }) => {
    const capacityColors = {
        'high': 'bg-emerald-100 text-emerald-800',
        'moderate': 'bg-amber-100 text-amber-800',
        'low': 'bg-rose-100 text-rose-800'
    };

    const statusColors = {
        'active': 'bg-green-100 text-green-800',
        'draft': 'bg-slate-100 text-slate-600',
        'inactive': 'bg-red-100 text-red-800'
    };

    // Determine capacity level from metadata or default
    const getCapacityLevel = () => {
        if (partner.capacity_metadata?.current_capacity) {
            const cap = partner.capacity_metadata.current_capacity;
            if (cap >= 70) return 'high';
            if (cap >= 40) return 'moderate';
            return 'low';
        }
        return 'moderate';
    };

    const capacityLevel = getCapacityLevel();

    return (
        <div className="bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden">
            {/* Header with logo/initials */}
            <div className="p-6 border-b border-slate-100">
                <div className="flex items-start gap-4">
                    {partner.logo_url ? (
                        <img
                            src={partner.logo_url}
                            alt={partner.name}
                            className="w-14 h-14 rounded-lg object-cover"
                        />
                    ) : (
                        <div className="w-14 h-14 rounded-lg bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center text-white font-bold text-lg">
                            {partner.initials}
                        </div>
                    )}
                    <div className="flex-1 min-w-0">
                        <h3 className="text-lg font-bold text-slate-900 truncate">{partner.name}</h3>
                        {partner.tagline && (
                            <p className="text-sm text-slate-500 mt-0.5 line-clamp-1">{partner.tagline}</p>
                        )}
                        <div className="flex items-center gap-2 mt-2">
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[partner.status] || statusColors.active}`}>
                                {partner.status || 'Active'}
                            </span>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${capacityColors[capacityLevel]}`}>
                                {capacityLevel.charAt(0).toUpperCase() + capacityLevel.slice(1)} Capacity
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Services */}
            <div className="p-6 border-b border-slate-100">
                <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Services Offered</div>
                <div className="flex flex-wrap gap-1.5">
                    {(partner.service_types || []).slice(0, 5).map((service, index) => (
                        <span
                            key={service.id || index}
                            className="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100"
                        >
                            {service.name}
                        </span>
                    ))}
                    {partner.service_types && partner.service_types.length > 5 && (
                        <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                            +{partner.service_types.length - 5} more
                        </span>
                    )}
                </div>
            </div>

            {/* Region & Contact */}
            <div className="p-6 border-b border-slate-100">
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Region</div>
                        <div className="text-slate-700">{partner.region_code?.replace(/_/g, ' ') || 'All Regions'}</div>
                    </div>
                    <div>
                        <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Contact</div>
                        <div className="text-slate-700 truncate">{partner.contact_email || 'N/A'}</div>
                    </div>
                </div>
            </div>

            {/* Actions */}
            <div className="p-4 bg-slate-50 flex gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    className="flex-1"
                    onClick={() => onViewProfile(partner)}
                >
                    View Profile
                </Button>
                <Button
                    size="sm"
                    className="flex-1"
                    onClick={() => onAssign(partner)}
                >
                    Assign Service
                </Button>
            </div>
        </div>
    );
};

/**
 * SspoMarketplacePage - Browse and manage SSPO partnerships
 */
const SspoMarketplacePage = () => {
    const navigate = useNavigate();

    // State
    const [partners, setPartners] = useState([]);
    const [filters, setFilters] = useState({ regions: [], serviceTypes: [] });
    const [stats, setStats] = useState({ total: 0, active: 0, totalServices: 0 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filter state
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedRegion, setSelectedRegion] = useState('');
    const [selectedServiceType, setSelectedServiceType] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('');

    // Fetch filters and stats on mount
    useEffect(() => {
        const fetchFiltersAndStats = async () => {
            try {
                const [filtersRes, statsRes] = await Promise.all([
                    fetch('/api/v2/sspo-marketplace/filters'),
                    fetch('/api/v2/sspo-marketplace/stats')
                ]);

                if (filtersRes.ok) {
                    const filtersData = await filtersRes.json();
                    setFilters(filtersData.data || { regions: [], serviceTypes: [] });
                }

                if (statsRes.ok) {
                    const statsData = await statsRes.json();
                    setStats(statsData.data || { total: 0, active: 0, totalServices: 0 });
                }
            } catch (err) {
                console.error('Error fetching filters/stats:', err);
            }
        };

        fetchFiltersAndStats();
    }, []);

    // Fetch partners
    const fetchPartners = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (searchTerm) params.append('search', searchTerm);
            if (selectedRegion) params.append('region', selectedRegion);
            if (selectedServiceType) params.append('service_type', selectedServiceType);
            if (selectedStatus) params.append('status', selectedStatus);

            const response = await fetch(`/api/v2/sspo-marketplace?${params.toString()}`);

            if (!response.ok) {
                throw new Error('Failed to fetch partners');
            }

            const data = await response.json();
            setPartners(data.data || []);
        } catch (err) {
            setError(err.message);
            setPartners([]);
        } finally {
            setLoading(false);
        }
    }, [searchTerm, selectedRegion, selectedServiceType, selectedStatus]);

    // Fetch partners on filter change (debounced for search)
    useEffect(() => {
        const timer = setTimeout(() => {
            fetchPartners();
        }, searchTerm ? 300 : 0);

        return () => clearTimeout(timer);
    }, [fetchPartners]);

    // Handlers
    const handleViewProfile = (partner) => {
        navigate(`/sspo-marketplace/${partner.id}`);
    };

    const handleAssign = (partner) => {
        // TODO: Implement assignment modal/flow
        alert(`Assignment flow for ${partner.name} will be implemented in Phase 2.`);
    };

    const handleRefresh = () => {
        fetchPartners();
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setSelectedRegion('');
        setSelectedServiceType('');
        setSelectedStatus('');
    };

    const hasActiveFilters = searchTerm || selectedRegion || selectedServiceType || selectedStatus;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SSPO Partner Marketplace</h1>
                    <p className="text-slate-500 text-sm">
                        Browse and assign care bundle components to Secondary Service Provider Organizations.
                    </p>
                </div>
                <Button variant="secondary" onClick={handleRefresh}>
                    Refresh Directory
                </Button>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Available Partners</div>
                    <div className="text-3xl font-bold text-teal-600">{stats.total}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Active Partners</div>
                    <div className="text-3xl font-bold text-emerald-500">{stats.active}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Services Offered</div>
                    <div className="text-3xl font-bold text-blue-600">{stats.totalServices}</div>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div className="flex flex-wrap gap-4 items-end">
                    {/* Search */}
                    <div className="flex-1 min-w-[200px]">
                        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">
                            Search
                        </label>
                        <input
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Search by name or description..."
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                        />
                    </div>

                    {/* Region Filter */}
                    <div className="min-w-[180px]">
                        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">
                            Region
                        </label>
                        <select
                            value={selectedRegion}
                            onChange={(e) => setSelectedRegion(e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 bg-white"
                        >
                            <option value="">All Regions</option>
                            {filters.regions.map((region) => (
                                <option key={region.code} value={region.code}>
                                    {region.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Service Type Filter */}
                    <div className="min-w-[180px]">
                        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">
                            Service Type
                        </label>
                        <select
                            value={selectedServiceType}
                            onChange={(e) => setSelectedServiceType(e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 bg-white"
                        >
                            <option value="">All Services</option>
                            {filters.serviceTypes.map((service) => (
                                <option key={service.id} value={service.id}>
                                    {service.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Status Filter */}
                    <div className="min-w-[140px]">
                        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">
                            Status
                        </label>
                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 bg-white"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    {/* Clear Filters */}
                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={handleClearFilters}>
                            Clear Filters
                        </Button>
                    )}
                </div>
            </div>

            {/* Partner Grid */}
            <Section title="Partner Directory">
                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <Spinner size="lg" />
                        <span className="ml-3 text-slate-500">Loading partners...</span>
                    </div>
                ) : error ? (
                    <div className="text-center py-12">
                        <div className="text-red-500 mb-2">Error loading partners</div>
                        <p className="text-slate-500 text-sm mb-4">{error}</p>
                        <Button variant="secondary" onClick={handleRefresh}>
                            Try Again
                        </Button>
                    </div>
                ) : partners.length === 0 ? (
                    <div className="text-center py-12">
                        <div className="text-slate-400 text-lg mb-2">No partners found</div>
                        <p className="text-slate-500 text-sm">
                            {hasActiveFilters
                                ? 'Try adjusting your filters to see more results.'
                                : 'No SSPO partners have been added yet.'}
                        </p>
                        {hasActiveFilters && (
                            <Button variant="secondary" size="sm" className="mt-4" onClick={handleClearFilters}>
                                Clear Filters
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {partners.map((partner) => (
                            <SSPOCard
                                key={partner.id}
                                partner={partner}
                                onViewProfile={handleViewProfile}
                                onAssign={handleAssign}
                            />
                        ))}
                    </div>
                )}
            </Section>
        </div>
    );
};

export default SspoMarketplacePage;
