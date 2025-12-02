import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import DataTable from '../../components/UI/DataTable';
import Spinner from '../../components/UI/Spinner';
import api from '../../services/api';

/**
 * QinManagerPage - Manages Quality Improvement Notices from OHaH
 *
 * Displays:
 * - Active QINs (officially issued by Ontario Health)
 * - Summary statistics (active, pending review, closed YTD)
 * - QIN history with actions (draft QIP, view plan)
 *
 * Per Ontario Health at Home RFP:
 * - QINs are issued when SPOs breach performance band thresholds
 * - SPOs must respond with a QIP within 7 days
 * - Target is 0 Active QINs (compliance)
 */
const QinManagerPage = () => {
    const navigate = useNavigate();
    const [qins, setQins] = useState([]);
    const [summary, setSummary] = useState({
        active_count: 0,
        open_count: 0,
        pending_review_count: 0,
        closed_ytd: 0,
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchQinData();
    }, []);

    const fetchQinData = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await api.get('/v2/qin/all');
            setQins(response.data.data || []);
            setSummary(response.data.summary || {
                active_count: 0,
                open_count: 0,
                pending_review_count: 0,
                closed_ytd: 0,
            });
        } catch (err) {
            console.error('Failed to fetch QIN data:', err);
            setError('Failed to load QIN data. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleCheckForNewNotices = async () => {
        // In production, this would trigger a check with OHaH
        // For now, just refresh the data
        alert('Checking OHaH for new notices... (Integration pending)');
        await fetchQinData();
    };

    const handleSubmitQip = async (qinId) => {
        try {
            await api.post(`/v2/qin/${qinId}/submit-qip`);
            alert('QIP submitted successfully!');
            await fetchQinData();
        } catch (err) {
            console.error('Failed to submit QIP:', err);
            alert('Failed to submit QIP. Please try again.');
        }
    };

    const columns = [
        { header: 'QIN ID', accessor: 'qin_number' },
        { header: 'Date Issued', accessor: 'issued_date' },
        { 
            header: 'Indicator / Breach', 
            accessor: (row) => (
                <div>
                    <div className="font-medium text-slate-900">{row.indicator}</div>
                    <div className="text-xs text-rose-600 font-bold">{row.band_breach}</div>
                </div>
            )
        },
        { 
            header: 'Status', 
            accessor: (row) => {
                const colors = {
                    'open': 'bg-rose-100 text-rose-800',
                    'submitted': 'bg-amber-100 text-amber-800',
                    'under_review': 'bg-blue-100 text-blue-800',
                    'closed': 'bg-slate-100 text-slate-600'
                };
                return (
                    <div className="flex flex-col gap-1">
                        <span className={`px-2 py-1 rounded-full text-xs font-bold ${colors[row.status] || 'bg-gray-100 text-gray-800'}`}>
                            {row.status_label}
                        </span>
                        {row.is_overdue && (
                            <span className="px-2 py-0.5 rounded text-xs font-bold bg-red-600 text-white">
                                OVERDUE
                            </span>
                        )}
                    </div>
                );
            }
        },
        { 
            header: 'QIP Due Date', 
            accessor: (row) => (
                <div>
                    <div>{row.qip_due_date || '—'}</div>
                    {row.days_until_due !== null && row.status === 'open' && (
                        <div className={`text-xs ${row.days_until_due < 0 ? 'text-red-600 font-bold' : row.days_until_due <= 2 ? 'text-amber-600' : 'text-slate-500'}`}>
                            {row.days_until_due < 0 
                                ? `${Math.abs(row.days_until_due)} days overdue`
                                : row.days_until_due === 0 
                                    ? 'Due today'
                                    : `${row.days_until_due} days remaining`}
                        </div>
                    )}
                </div>
            )
        },
        {
            header: 'Action',
            accessor: (row) => (
                row.status === 'open' ? (
                    <div className="flex gap-2">
                        <Button size="sm" onClick={() => navigate(`/qip/create/${row.id}`)}>
                            Draft QIP
                        </Button>
                    </div>
                ) : (
                    <Link to={`/qip/view/${row.id}`} className="text-teal-600 hover:text-teal-800 text-sm font-medium">
                        View Plan
                    </Link>
                )
            )
        }
    ];

    if (loading) {
        return (
            <div className="p-12 flex justify-center">
                <Spinner />
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6">
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    {error}
                    <button 
                        onClick={fetchQinData}
                        className="ml-4 text-red-800 underline hover:no-underline"
                    >
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Quality Improvement Notices (QIN)</h1>
                    <p className="text-slate-500 text-sm">Manage compliance breaches and remediation plans (Schedule 4).</p>
                </div>
                <Button variant="secondary" onClick={handleCheckForNewNotices}>
                    Check for New Notices
                </Button>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className={`bg-white p-4 rounded-xl border shadow-sm ${summary.open_count > 0 ? 'border-rose-200' : 'border-slate-200'}`}>
                    <div className="text-xs font-bold text-slate-400 uppercase">Active Notices</div>
                    <div className={`text-3xl font-bold ${summary.active_count > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                        {summary.active_count}
                    </div>
                    <div className={`text-xs mt-1 ${summary.active_count > 0 ? 'text-rose-500' : 'text-emerald-500'}`}>
                        {summary.active_count > 0 ? 'Action Required' : 'Compliant'}
                    </div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Open (Require QIP)</div>
                    <div className={`text-3xl font-bold ${summary.open_count > 0 ? 'text-rose-600' : 'text-slate-600'}`}>
                        {summary.open_count}
                    </div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Pending OHaH Review</div>
                    <div className="text-3xl font-bold text-amber-500">{summary.pending_review_count}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Closed (YTD)</div>
                    <div className="text-3xl font-bold text-slate-600">{summary.closed_ytd}</div>
                </div>
            </div>

            {/* QIN History Table */}
            <Section title="Notice History">
                {qins.length === 0 ? (
                    <div className="text-center py-8 text-slate-500">
                        <div className="text-4xl mb-2">✓</div>
                        <div className="font-medium">No QINs on record</div>
                        <div className="text-sm">Your organization is currently in compliance with all Schedule 4 indicators.</div>
                    </div>
                ) : (
                    <DataTable columns={columns} data={qins} keyField="id" />
                )}
            </Section>
        </div>
    );
};

export default QinManagerPage;
