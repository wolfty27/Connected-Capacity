import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Card from '../../components/UI/Card';
import Spinner from '../../components/UI/Spinner';

/**
 * SspoProfilePage - Detailed view of a single SSPO partner
 *
 * Displays:
 * - Header with logo, name, status, region
 * - About section with description
 * - Services Offered
 * - Assigned Patients & Upcoming Appointments
 * - Recent Service History
 * - Capacity & Utilization metrics
 * - Contact Information
 * - Location
 * - Actions
 */
const SspoProfilePage = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [partner, setPartner] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchPartner = async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(`/api/v2/sspo-marketplace/${id}`);

                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error('Partner not found');
                    }
                    throw new Error('Failed to fetch partner details');
                }

                const data = await response.json();
                setPartner(data.data);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        if (id) {
            fetchPartner();
        }
    }, [id]);

    const handleBack = () => {
        navigate('/sspo-marketplace');
    };

    const handleAssign = () => {
        alert(`Assignment flow for ${partner?.name} will be implemented in Phase 2.`);
    };

    // Status badge colors
    const getStatusColor = (status) => {
        const colors = {
            'active': 'bg-green-100 text-green-800',
            'draft': 'bg-slate-100 text-slate-600',
            'inactive': 'bg-red-100 text-red-800'
        };
        return colors[status] || colors.active;
    };

    // Service category colors
    const getCategoryColor = (category) => {
        const colors = {
            'nursing': 'bg-blue-100 text-blue-600',
            'therapy': 'bg-purple-100 text-purple-600',
            'psw': 'bg-green-100 text-green-600',
            'monitoring': 'bg-amber-100 text-amber-600',
            'support': 'bg-rose-100 text-rose-600',
        };
        return colors[category?.toLowerCase()] || 'bg-slate-100 text-slate-600';
    };

    // Format date/time for display
    const formatDateTime = (isoString) => {
        if (!isoString) return 'N/A';
        const date = new Date(isoString);
        return date.toLocaleDateString('en-CA', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    const formatTime = (isoString) => {
        if (!isoString) return '';
        const date = new Date(isoString);
        return date.toLocaleTimeString('en-CA', {
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    // Verification status badge
    const getVerificationBadge = (status) => {
        const badges = {
            'VERIFIED': { color: 'bg-emerald-100 text-emerald-700', label: 'Verified', icon: '✓' },
            'PENDING': { color: 'bg-amber-100 text-amber-700', label: 'Pending', icon: '○' },
            'MISSED': { color: 'bg-rose-100 text-rose-700', label: 'Missed', icon: '✕' },
        };
        return badges[status] || badges['PENDING'];
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <Spinner size="lg" />
                <span className="ml-3 text-slate-500">Loading partner details...</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className="text-center py-12">
                <div className="text-red-500 text-xl mb-2">Error</div>
                <p className="text-slate-500 mb-4">{error}</p>
                <Button variant="secondary" onClick={handleBack}>
                    Back to Marketplace
                </Button>
            </div>
        );
    }

    if (!partner) {
        return (
            <div className="text-center py-12">
                <div className="text-slate-400 text-xl mb-2">Partner Not Found</div>
                <p className="text-slate-500 mb-4">The requested partner could not be found.</p>
                <Button variant="secondary" onClick={handleBack}>
                    Back to Marketplace
                </Button>
            </div>
        );
    }

    // Get capacity summary from API response
    const capacitySummary = partner.capacity_summary || {};
    const upcomingAssignments = partner.upcoming_assignments || [];
    const recentAssignments = partner.recent_assignments || [];

    return (
        <div className="space-y-6">
            {/* Back Navigation */}
            <Button variant="ghost" onClick={handleBack} className="mb-2">
                &larr; Back to Marketplace
            </Button>

            {/* Header Section */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                {/* Cover Photo */}
                {partner.cover_photo_url && (
                    <div className="h-48 bg-gradient-to-r from-teal-600 to-teal-500 overflow-hidden">
                        <img
                            src={partner.cover_photo_url}
                            alt=""
                            className="w-full h-full object-cover opacity-60"
                        />
                    </div>
                )}
                {!partner.cover_photo_url && (
                    <div className="h-32 bg-gradient-to-r from-teal-600 to-teal-500" />
                )}

                {/* Profile Info */}
                <div className="p-6 relative">
                    <div className="flex flex-col md:flex-row md:items-start gap-6">
                        {/* Logo */}
                        <div className={`-mt-16 md:-mt-12 ${partner.cover_photo_url ? '' : '-mt-12'}`}>
                            {partner.logo_url ? (
                                <img
                                    src={partner.logo_url}
                                    alt={partner.name}
                                    className="w-24 h-24 rounded-xl border-4 border-white shadow-lg object-cover bg-white"
                                />
                            ) : (
                                <div className="w-24 h-24 rounded-xl border-4 border-white shadow-lg bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center text-white font-bold text-2xl">
                                    {partner.initials}
                                </div>
                            )}
                        </div>

                        {/* Name and Tagline */}
                        <div className="flex-1">
                            <div className="flex items-start justify-between">
                                <div>
                                    <h1 className="text-2xl font-bold text-slate-900">{partner.name}</h1>
                                    {partner.tagline && (
                                        <p className="text-slate-500 mt-1">{partner.tagline}</p>
                                    )}
                                    <div className="flex items-center gap-2 mt-3">
                                        <span className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(partner.status)}`}>
                                            {partner.status?.charAt(0).toUpperCase() + partner.status?.slice(1) || 'Active'}
                                        </span>
                                        <span className="text-slate-400">|</span>
                                        <span className="text-slate-600 text-sm">
                                            {partner.region_code?.replace(/_/g, ' ') || 'All Regions'}
                                        </span>
                                    </div>
                                </div>
                                <Button onClick={handleAssign}>
                                    Assign Service
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column - Main Content */}
                <div className="lg:col-span-2 space-y-6">
                    {/* About */}
                    {partner.description && (
                        <Card title="About">
                            <p className="text-slate-600 leading-relaxed whitespace-pre-line">
                                {partner.description}
                            </p>
                        </Card>
                    )}

                    {/* Services Offered */}
                    <Card title="Services Offered">
                        {partner.services && partner.services.length > 0 ? (
                            <div className="space-y-4">
                                {partner.services.map((service) => (
                                    <div
                                        key={service.id}
                                        className="flex items-start gap-3 p-3 bg-slate-50 rounded-lg"
                                    >
                                        <div className={`w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm flex-shrink-0 ${getCategoryColor(service.category)}`}>
                                            {service.code || service.name?.substring(0, 2).toUpperCase()}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="font-medium text-slate-800">{service.name}</div>
                                            {service.description && (
                                                <p className="text-sm text-slate-500 mt-0.5 line-clamp-2">
                                                    {service.description}
                                                </p>
                                            )}
                                            <div className="flex items-center gap-3 mt-2 text-xs text-slate-500">
                                                {service.duration_minutes && (
                                                    <span>{service.duration_minutes} min</span>
                                                )}
                                                {service.delivery_mode && (
                                                    <span className="capitalize">
                                                        {service.delivery_mode.replace('_', ' ')}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        {service.is_primary && (
                                            <span className="px-2 py-0.5 bg-teal-100 text-teal-700 text-xs font-medium rounded-full">
                                                Primary
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-slate-500">No services listed yet.</p>
                        )}
                    </Card>

                    {/* Assigned Patients & Upcoming Appointments */}
                    <Card title="Assigned Patients & Appointments">
                        {upcomingAssignments.length > 0 ? (
                            <div className="space-y-4">
                                {upcomingAssignments.map((patientGroup, idx) => (
                                    <div key={idx} className="border border-slate-200 rounded-lg p-4">
                                        {/* Patient Header */}
                                        <div className="flex items-center gap-3 mb-3">
                                            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-slate-400 to-slate-500 flex items-center justify-center text-white font-bold text-sm">
                                                {patientGroup.patient?.initials || '??'}
                                            </div>
                                            <div>
                                                <div className="font-medium text-slate-800">
                                                    {patientGroup.patient?.name || 'Unknown Patient'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {patientGroup.appointments?.length || 0} upcoming appointment(s)
                                                </div>
                                            </div>
                                        </div>

                                        {/* Appointments List */}
                                        <div className="space-y-2 ml-13">
                                            {patientGroup.appointments?.map((appt) => (
                                                <div
                                                    key={appt.id}
                                                    className="flex items-center justify-between p-2 bg-slate-50 rounded"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className={`px-2 py-0.5 text-xs font-medium rounded ${getCategoryColor(appt.service_type?.category)}`}>
                                                            {appt.service_type?.code || 'SVC'}
                                                        </span>
                                                        <span className="text-sm text-slate-700">
                                                            {appt.service_type?.name}
                                                        </span>
                                                    </div>
                                                    <div className="text-right text-sm">
                                                        <div className="text-slate-800 font-medium">
                                                            {formatDateTime(appt.scheduled_start)}
                                                        </div>
                                                        <div className="text-slate-500 text-xs">
                                                            {appt.staff?.name || 'Unassigned'}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-6 text-slate-500">
                                <svg className="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p>No upcoming appointments</p>
                            </div>
                        )}
                    </Card>

                    {/* Recent Service History */}
                    <Card title="Recent Service History">
                        {recentAssignments.length > 0 ? (
                            <div className="space-y-2">
                                {recentAssignments.map((assignment) => {
                                    const badge = getVerificationBadge(assignment.verification_status);
                                    return (
                                        <div
                                            key={assignment.id}
                                            className="flex items-center justify-between p-3 border border-slate-100 rounded-lg hover:bg-slate-50 transition-colors"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs ${badge.color}`}>
                                                    {badge.icon}
                                                </span>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span className={`px-2 py-0.5 text-xs font-medium rounded ${getCategoryColor(assignment.service_type?.category)}`}>
                                                            {assignment.service_type?.code}
                                                        </span>
                                                        <span className="text-sm font-medium text-slate-800">
                                                            {assignment.patient?.name}
                                                        </span>
                                                    </div>
                                                    <div className="text-xs text-slate-500 mt-0.5">
                                                        {assignment.service_type?.name}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-sm text-slate-700">
                                                    {formatDateTime(assignment.scheduled_start)}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {assignment.staff?.name}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-6 text-slate-500">
                                <svg className="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p>No recent service history</p>
                            </div>
                        )}
                    </Card>

                    {/* Notes */}
                    {partner.notes && (
                        <Card title="Additional Notes">
                            <p className="text-slate-600 leading-relaxed whitespace-pre-line">
                                {partner.notes}
                            </p>
                        </Card>
                    )}
                </div>

                {/* Right Column - Sidebar */}
                <div className="space-y-6">
                    {/* Capacity & Utilization - Enhanced */}
                    <Card title="Capacity & Utilization">
                        <div className="space-y-4">
                            {/* Availability Status */}
                            <div className="flex items-center justify-between">
                                <span className="text-slate-600">Availability</span>
                                <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                                    capacitySummary.availability_status === 'High'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : capacitySummary.availability_status === 'Moderate'
                                        ? 'bg-amber-100 text-amber-700'
                                        : 'bg-rose-100 text-rose-700'
                                }`}>
                                    {capacitySummary.availability_status || partner.capacity_status || 'Unknown'}
                                </span>
                            </div>

                            {/* Utilization Progress Bar */}
                            <div>
                                <div className="flex justify-between text-sm mb-1">
                                    <span className="text-slate-500">Utilization</span>
                                    <span className="font-medium text-slate-700">
                                        {capacitySummary.utilization_pct || 0}%
                                    </span>
                                </div>
                                <div className="w-full bg-slate-200 rounded-full h-3">
                                    <div
                                        className={`h-3 rounded-full transition-all ${
                                            (capacitySummary.utilization_pct || 0) >= 90
                                                ? 'bg-rose-500'
                                                : (capacitySummary.utilization_pct || 0) >= 70
                                                ? 'bg-amber-500'
                                                : 'bg-teal-500'
                                        }`}
                                        style={{ width: `${capacitySummary.utilization_pct || 0}%` }}
                                    />
                                </div>
                            </div>

                            {/* Weekly Metrics */}
                            <div className="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                                <div className="text-center p-3 bg-slate-50 rounded-lg">
                                    <div className="text-2xl font-bold text-teal-600">
                                        {capacitySummary.scheduled_hours || 0}
                                    </div>
                                    <div className="text-xs text-slate-500 mt-1">Scheduled Hours</div>
                                </div>
                                <div className="text-center p-3 bg-slate-50 rounded-lg">
                                    <div className="text-2xl font-bold text-slate-700">
                                        {capacitySummary.available_hours || 0}
                                    </div>
                                    <div className="text-xs text-slate-500 mt-1">Available Hours</div>
                                </div>
                            </div>

                            {/* Visit Stats */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="text-center p-3 bg-slate-50 rounded-lg">
                                    <div className="text-2xl font-bold text-slate-700">
                                        {capacitySummary.patient_count || 0}
                                    </div>
                                    <div className="text-xs text-slate-500 mt-1">Patients This Week</div>
                                </div>
                                <div className="text-center p-3 bg-slate-50 rounded-lg">
                                    <div className="text-2xl font-bold text-slate-700">
                                        {capacitySummary.visit_count || 0}
                                    </div>
                                    <div className="text-xs text-slate-500 mt-1">Visits This Week</div>
                                </div>
                            </div>

                            {/* Week Range */}
                            {capacitySummary.week_start && (
                                <div className="text-xs text-center text-slate-400 pt-2 border-t border-slate-100">
                                    Week of {capacitySummary.week_start} to {capacitySummary.week_end}
                                </div>
                            )}
                        </div>
                    </Card>

                    {/* Contact Information */}
                    <Card title="Contact Information">
                        <div className="space-y-4">
                            {partner.contact_name && (
                                <div>
                                    <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">
                                        Contact Person
                                    </div>
                                    <div className="text-slate-800">{partner.contact_name}</div>
                                </div>
                            )}

                            {partner.contact_email && (
                                <div>
                                    <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">
                                        Email
                                    </div>
                                    <a
                                        href={`mailto:${partner.contact_email}`}
                                        className="text-teal-600 hover:text-teal-700"
                                    >
                                        {partner.contact_email}
                                    </a>
                                </div>
                            )}

                            {partner.contact_phone && (
                                <div>
                                    <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">
                                        Phone
                                    </div>
                                    <a
                                        href={`tel:${partner.contact_phone}`}
                                        className="text-teal-600 hover:text-teal-700"
                                    >
                                        {partner.contact_phone}
                                    </a>
                                </div>
                            )}

                            {partner.website_url && (
                                <div>
                                    <div className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">
                                        Website
                                    </div>
                                    <a
                                        href={partner.website_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-teal-600 hover:text-teal-700"
                                    >
                                        {partner.website_url.replace(/^https?:\/\//, '')}
                                    </a>
                                </div>
                            )}
                        </div>
                    </Card>

                    {/* Address */}
                    {(partner.address || partner.city) && (
                        <Card title="Location">
                            <div className="text-slate-700">
                                {partner.address && <div>{partner.address}</div>}
                                <div>
                                    {partner.city}
                                    {partner.province && `, ${partner.province}`}
                                    {partner.postal_code && ` ${partner.postal_code}`}
                                </div>
                            </div>
                        </Card>
                    )}

                    {/* Capabilities */}
                    {partner.capabilities && partner.capabilities.length > 0 && (
                        <Card title="Special Capabilities">
                            <div className="flex flex-wrap gap-2">
                                {partner.capabilities.map((capability, index) => (
                                    <span
                                        key={index}
                                        className="px-2 py-1 bg-purple-50 text-purple-700 text-sm rounded-lg border border-purple-100"
                                    >
                                        {capability}
                                    </span>
                                ))}
                            </div>
                        </Card>
                    )}

                    {/* Quick Actions */}
                    <Card title="Actions">
                        <div className="space-y-2">
                            <Button className="w-full" onClick={handleAssign}>
                                Assign Service
                            </Button>
                            <Button
                                variant="secondary"
                                className="w-full"
                                onClick={() => alert('Contact form coming soon')}
                            >
                                Send Message
                            </Button>
                            <Button
                                variant="ghost"
                                className="w-full"
                                onClick={() => alert('Reporting coming soon')}
                            >
                                View Reports
                            </Button>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    );
};

export default SspoProfilePage;
