import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import DataTable from '../../components/UI/DataTable';
import Button from '../../components/UI/Button';
import Modal from '../../components/UI/Modal';
import Input from '../../components/UI/Input';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

const SpoStaffPage = () => {
    const [staff, setStaff] = useState([]);
    const [loading, setLoading] = useState(true);
    const [fteCompliance, setFteCompliance] = useState(null);
    const [fteTrend, setFteTrend] = useState([]);
    const [skills, setSkills] = useState([]);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showSkillsModal, setShowSkillsModal] = useState(false);
    const [showAvailabilityModal, setShowAvailabilityModal] = useState(false);
    const [selectedStaff, setSelectedStaff] = useState(null);
    const [staffSkills, setStaffSkills] = useState([]);
    const [staffAvailability, setStaffAvailability] = useState([]);
    const [filter, setFilter] = useState({ status: '', employment_type: '', search: '' });
    const [pagination, setPagination] = useState({ page: 1, perPage: 20, total: 0 });

    // Fetch staff list
    const fetchStaff = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: pagination.page,
                per_page: pagination.perPage,
                ...(filter.status && { status: filter.status }),
                ...(filter.employment_type && { employment_type: filter.employment_type }),
                ...(filter.search && { search: filter.search }),
            });
            const response = await api.get(`/v2/staff?${params}`);
            setStaff(response.data.data || []);
            setPagination(prev => ({ ...prev, total: response.data.total || 0 }));
        } catch (error) {
            console.error('Failed to fetch staff:', error);
            // Fallback to empty state
            setStaff([]);
        } finally {
            setLoading(false);
        }
    }, [pagination.page, pagination.perPage, filter]);

    // Fetch FTE compliance
    const fetchFteCompliance = async () => {
        try {
            const [complianceRes, trendRes] = await Promise.all([
                api.get('/v2/staff/analytics/fte-compliance'),
                api.get('/v2/staff/analytics/fte-trend?weeks=8'),
            ]);
            setFteCompliance(complianceRes.data.data);
            setFteTrend(trendRes.data.data || []);
        } catch (error) {
            console.error('Failed to fetch FTE compliance:', error);
        }
    };

    // Fetch skills catalog
    const fetchSkills = async () => {
        try {
            const response = await api.get('/v2/staff/skills/catalog');
            setSkills(response.data.data || []);
        } catch (error) {
            console.error('Failed to fetch skills:', error);
        }
    };

    useEffect(() => {
        fetchStaff();
        fetchFteCompliance();
        fetchSkills();
    }, [fetchStaff]);

    // View staff skills
    const handleViewSkills = async (staffMember) => {
        setSelectedStaff(staffMember);
        try {
            const response = await api.get(`/v2/staff/${staffMember.id}/skills`);
            setStaffSkills(response.data.data || []);
            setShowSkillsModal(true);
        } catch (error) {
            console.error('Failed to fetch staff skills:', error);
        }
    };

    // View staff availability
    const handleViewAvailability = async (staffMember) => {
        setSelectedStaff(staffMember);
        try {
            const response = await api.get(`/v2/staff/${staffMember.id}/availability`);
            setStaffAvailability(response.data.data || []);
            setShowAvailabilityModal(true);
        } catch (error) {
            console.error('Failed to fetch availability:', error);
        }
    };

    // Get FTE band color
    const getFteBandColor = (band) => {
        switch (band) {
            case 'GREEN': return 'bg-emerald-100 text-emerald-800 border-emerald-300';
            case 'YELLOW': return 'bg-amber-100 text-amber-800 border-amber-300';
            case 'RED': return 'bg-red-100 text-red-800 border-red-300';
            default: return 'bg-slate-100 text-slate-600 border-slate-300';
        }
    };

    // Get employment type badge
    const getEmploymentTypeBadge = (type) => {
        const colors = {
            full_time: 'bg-emerald-100 text-emerald-800',
            part_time: 'bg-amber-100 text-amber-800',
            casual: 'bg-slate-100 text-slate-600',
        };
        const labels = {
            full_time: 'Full-Time',
            part_time: 'Part-Time',
            casual: 'Casual',
        };
        return (
            <span className={`px-2 py-1 rounded-full text-xs font-bold ${colors[type] || colors.casual}`}>
                {labels[type] || type}
            </span>
        );
    };

    // Get status badge
    const getStatusBadge = (status) => {
        const colors = {
            active: 'bg-emerald-100 text-emerald-800',
            inactive: 'bg-slate-100 text-slate-600',
            on_leave: 'bg-amber-100 text-amber-800',
            terminated: 'bg-red-100 text-red-800',
        };
        return (
            <span className={`px-2 py-1 rounded-full text-xs font-bold ${colors[status] || colors.active}`}>
                {status?.replace('_', ' ').toUpperCase() || 'ACTIVE'}
            </span>
        );
    };

    const columns = [
        { header: 'Name', accessor: 'name' },
        { header: 'Role', accessor: 'organization_role' },
        { header: 'Employment', accessor: (row) => getEmploymentTypeBadge(row.employment_type) },
        { header: 'Status', accessor: (row) => getStatusBadge(row.staff_status) },
        {
            header: 'Hours (This Week)',
            accessor: (row) => (
                <div className="text-sm">
                    <span className="font-medium">{row.current_weekly_hours || 0}</span>
                    <span className="text-slate-400"> / {row.max_weekly_hours || 40}h</span>
                    <div className="w-24 h-2 bg-slate-200 rounded-full mt-1">
                        <div
                            className={`h-2 rounded-full ${row.utilization_rate > 90 ? 'bg-amber-500' : 'bg-emerald-500'}`}
                            style={{ width: `${Math.min(100, row.utilization_rate || 0)}%` }}
                        />
                    </div>
                </div>
            )
        },
        {
            header: 'Skills',
            accessor: (row) => (
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{row.skills_count || 0}</span>
                    {row.expiring_skills_count > 0 && (
                        <span className="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                            {row.expiring_skills_count} expiring
                        </span>
                    )}
                </div>
            )
        },
        {
            header: 'Actions',
            accessor: (row) => (
                <div className="flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => handleViewSkills(row)}>
                        Skills
                    </Button>
                    <Button size="sm" variant="secondary" onClick={() => handleViewAvailability(row)}>
                        Schedule
                    </Button>
                </div>
            )
        }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SPO Workforce Management</h1>
                    <p className="text-slate-500 text-sm">Manage direct care staff, credentials, and FTE compliance.</p>
                </div>
                <Button onClick={() => setShowAddModal(true)}>+ Add Staff</Button>
            </div>

            {/* FTE Compliance Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Total Staff</div>
                    <div className="text-3xl font-bold text-slate-700">{fteCompliance?.total_staff || staff.length}</div>
                    <div className="text-xs text-slate-400 mt-1">
                        {fteCompliance?.full_time_staff || 0} FT / {fteCompliance?.part_time_staff || 0} PT / {fteCompliance?.casual_staff || 0} Casual
                    </div>
                </div>
                <div className={`p-4 rounded-xl border shadow-sm ${getFteBandColor(fteCompliance?.band)}`}>
                    <div className="text-xs font-bold uppercase opacity-70">FTE Ratio</div>
                    <div className="text-3xl font-bold">{fteCompliance?.fte_ratio || 0}%</div>
                    <div className="text-xs opacity-70">Target: 80%</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Total Hours (Week)</div>
                    <div className="text-3xl font-bold text-slate-700">{fteCompliance?.total_hours || 0}</div>
                    <div className="text-xs text-slate-400 mt-1">
                        Internal: {fteCompliance?.internal_hours || 0}h | SSPO: {fteCompliance?.sspo_hours || 0}h
                    </div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Utilization</div>
                    <div className="text-3xl font-bold text-slate-700">{fteCompliance?.utilization_rate || 0}%</div>
                    <div className="text-xs text-slate-400 mt-1">
                        Capacity: {fteCompliance?.total_capacity_hours || 0}h
                    </div>
                </div>
            </div>

            {/* FTE Trend Chart */}
            {fteTrend.length > 0 && (
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <h3 className="text-sm font-bold text-slate-600 mb-3">FTE Compliance Trend (8 Weeks)</h3>
                    <div className="flex items-end gap-2 h-32">
                        {fteTrend.map((week, idx) => (
                            <div key={idx} className="flex-1 flex flex-col items-center">
                                <div
                                    className={`w-full rounded-t ${getFteBandColor(week.band).split(' ')[0]}`}
                                    style={{ height: `${Math.max(10, (week.fte_ratio || 0))}%` }}
                                />
                                <div className="text-xs text-slate-400 mt-1">{week.week_label}</div>
                                <div className="text-xs font-medium">{week.fte_ratio || 0}%</div>
                            </div>
                        ))}
                    </div>
                    <div className="flex items-center gap-4 mt-2 text-xs text-slate-500">
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

            {/* Filters */}
            <div className="flex gap-4 items-center">
                <Input
                    placeholder="Search staff..."
                    value={filter.search}
                    onChange={(e) => setFilter(prev => ({ ...prev, search: e.target.value }))}
                    className="w-64"
                />
                <Select
                    value={filter.status}
                    onChange={(e) => setFilter(prev => ({ ...prev, status: e.target.value }))}
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="on_leave">On Leave</option>
                </Select>
                <Select
                    value={filter.employment_type}
                    onChange={(e) => setFilter(prev => ({ ...prev, employment_type: e.target.value }))}
                >
                    <option value="">All Types</option>
                    <option value="full_time">Full-Time</option>
                    <option value="part_time">Part-Time</option>
                    <option value="casual">Casual</option>
                </Select>
                <Button variant="secondary" onClick={() => setFilter({ status: '', employment_type: '', search: '' })}>
                    Clear
                </Button>
            </div>

            {/* Staff Directory */}
            <Section title="Staff Directory">
                {loading ? (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : (
                    <DataTable columns={columns} data={staff} keyField="id" />
                )}
            </Section>

            {/* Skills Modal */}
            <Modal
                isOpen={showSkillsModal}
                onClose={() => setShowSkillsModal(false)}
                title={`Skills - ${selectedStaff?.name}`}
                size="lg"
            >
                <div className="space-y-4">
                    {staffSkills.length === 0 ? (
                        <p className="text-slate-500 text-center py-4">No skills assigned</p>
                    ) : (
                        <div className="divide-y divide-slate-200">
                            {staffSkills.map((skill) => (
                                <div key={skill.id} className="py-3 flex justify-between items-center">
                                    <div>
                                        <div className="font-medium">{skill.name}</div>
                                        <div className="text-sm text-slate-500">
                                            {skill.category_label} | {skill.proficiency_level}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        {skill.expires_at && (
                                            <div className={`text-sm ${skill.is_expired ? 'text-red-600 font-bold' : skill.is_expiring_soon ? 'text-amber-600' : 'text-slate-500'}`}>
                                                {skill.is_expired ? 'EXPIRED' : `Expires: ${skill.expires_at}`}
                                            </div>
                                        )}
                                        {skill.certification_number && (
                                            <div className="text-xs text-slate-400">#{skill.certification_number}</div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    <div className="pt-4 border-t">
                        <Button variant="secondary" className="w-full">+ Assign New Skill</Button>
                    </div>
                </div>
            </Modal>

            {/* Availability Modal */}
            <Modal
                isOpen={showAvailabilityModal}
                onClose={() => setShowAvailabilityModal(false)}
                title={`Availability - ${selectedStaff?.name}`}
                size="lg"
            >
                <div className="space-y-4">
                    {staffAvailability.length === 0 ? (
                        <p className="text-slate-500 text-center py-4">No availability set</p>
                    ) : (
                        <div className="grid grid-cols-7 gap-2">
                            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day, idx) => {
                                const dayAvail = staffAvailability.filter(a => a.day_of_week === idx);
                                return (
                                    <div key={day} className="text-center">
                                        <div className="font-medium text-sm text-slate-600 mb-2">{day}</div>
                                        {dayAvail.length > 0 ? (
                                            dayAvail.map((a, i) => (
                                                <div key={i} className="bg-emerald-100 text-emerald-800 text-xs py-1 px-2 rounded mb-1">
                                                    {a.time_range}
                                                </div>
                                            ))
                                        ) : (
                                            <div className="bg-slate-100 text-slate-400 text-xs py-1 px-2 rounded">
                                                Off
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    <div className="pt-4 border-t flex gap-2">
                        <Button variant="secondary" className="flex-1">Edit Schedule</Button>
                        <Button variant="secondary" className="flex-1">Request Time Off</Button>
                    </div>
                </div>
            </Modal>

            {/* Add Staff Modal */}
            <AddStaffModal
                isOpen={showAddModal}
                onClose={() => setShowAddModal(false)}
                onSuccess={() => {
                    setShowAddModal(false);
                    fetchStaff();
                    fetchFteCompliance();
                }}
            />
        </div>
    );
};

// Add Staff Modal Component
const AddStaffModal = ({ isOpen, onClose, onSuccess }) => {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        role: 'FIELD_STAFF',
        organization_role: '',
        employment_type: 'full_time',
        max_weekly_hours: 40,
        hire_date: '',
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            await api.post('/v2/staff', formData);
            onSuccess();
        } catch (err) {
            setError(err.response?.data?.errors || { general: ['Failed to create staff member'] });
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Add New Staff Member" size="lg">
            <form onSubmit={handleSubmit} className="space-y-4">
                {error && (
                    <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">
                        {Object.values(error).flat().join(', ')}
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Full Name"
                        value={formData.name}
                        onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                        required
                    />
                    <Input
                        label="Email"
                        type="email"
                        value={formData.email}
                        onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
                        required
                    />
                </div>

                <Input
                    label="Password"
                    type="password"
                    value={formData.password}
                    onChange={(e) => setFormData(prev => ({ ...prev, password: e.target.value }))}
                    required
                />

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">System Role</label>
                        <Select
                            value={formData.role}
                            onChange={(e) => setFormData(prev => ({ ...prev, role: e.target.value }))}
                        >
                            <option value="FIELD_STAFF">Field Staff</option>
                            <option value="SPO_COORDINATOR">SPO Coordinator</option>
                            <option value="SSPO_COORDINATOR">SSPO Coordinator</option>
                        </Select>
                    </div>
                    <Input
                        label="Organization Role"
                        placeholder="e.g., RN, PSW, OT"
                        value={formData.organization_role}
                        onChange={(e) => setFormData(prev => ({ ...prev, organization_role: e.target.value }))}
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Employment Type</label>
                        <Select
                            value={formData.employment_type}
                            onChange={(e) => setFormData(prev => ({ ...prev, employment_type: e.target.value }))}
                        >
                            <option value="full_time">Full-Time</option>
                            <option value="part_time">Part-Time</option>
                            <option value="casual">Casual</option>
                        </Select>
                    </div>
                    <Input
                        label="Max Weekly Hours"
                        type="number"
                        value={formData.max_weekly_hours}
                        onChange={(e) => setFormData(prev => ({ ...prev, max_weekly_hours: parseInt(e.target.value) }))}
                    />
                </div>

                <Input
                    label="Hire Date"
                    type="date"
                    value={formData.hire_date}
                    onChange={(e) => setFormData(prev => ({ ...prev, hire_date: e.target.value }))}
                />

                <div className="flex gap-3 pt-4">
                    <Button type="button" variant="secondary" onClick={onClose} className="flex-1">
                        Cancel
                    </Button>
                    <Button type="submit" disabled={loading} className="flex-1">
                        {loading ? 'Creating...' : 'Create Staff Member'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
};

export default SpoStaffPage;
