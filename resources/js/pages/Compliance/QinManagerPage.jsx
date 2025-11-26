import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import DataTable from '../../components/UI/DataTable';

const QinManagerPage = () => {
    const navigate = useNavigate();
    
    // Mock Data for QINs
    const [qins, setQins] = useState([
        {
            id: 'QIN-2025-001',
            issued_date: '2025-10-15',
            indicator: 'Referral Acceptance Rate',
            status: 'Open',
            band_breach: 'Band C (<95%)',
            due_date: '2025-10-22',
            ohah_contact: 'Sarah Smith'
        },
        {
            id: 'QIN-2025-002',
            issued_date: '2025-10-10',
            indicator: 'Missed Care Rate',
            status: 'Submitted',
            band_breach: 'Band B (>0.01%)',
            due_date: '2025-10-17',
            ohah_contact: 'Mike Jones'
        },
         {
            id: 'QIN-2024-089',
            issued_date: '2024-12-01',
            indicator: 'Time-to-First-Service',
            status: 'Closed',
            band_breach: 'Band C (>24h)',
            due_date: '2024-12-08',
            ohah_contact: 'Sarah Smith'
        }
    ]);

    const columns = [
        { header: 'QIN ID', accessor: 'id' },
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
                    'Open': 'bg-rose-100 text-rose-800',
                    'Submitted': 'bg-amber-100 text-amber-800',
                    'Closed': 'bg-slate-100 text-slate-600'
                };
                return (
                    <span className={`px-2 py-1 rounded-full text-xs font-bold ${colors[row.status]}`}>
                        {row.status}
                    </span>
                );
            }
        },
        { header: 'QIP Due Date', accessor: 'due_date' },
        {
            header: 'Action',
            accessor: (row) => (
                row.status === 'Open' ? (
                    <Button size="sm" onClick={() => navigate(`/qip/create/${row.id}`)}>
                        Draft QIP
                    </Button>
                ) : (
                    <Link to={`/qip/view/${row.id}`} className="text-teal-600 hover:text-teal-800 text-sm font-medium">
                        View Plan
                    </Link>
                )
            )
        }
    ];

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Quality Improvement Notices (QIN)</h1>
                    <p className="text-slate-500 text-sm">Manage compliance breaches and remediation plans (Schedule 4).</p>
                </div>
                <Button variant="secondary" onClick={() => alert('Simulating OHaH QIN Ingestion...')}>
                    Check for New Notices
                </Button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Active Notices</div>
                    <div className="text-3xl font-bold text-rose-600">1</div>
                    <div className="text-xs text-rose-500 mt-1">Action Required</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Pending OHaH Review</div>
                    <div className="text-3xl font-bold text-amber-500">1</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Closed (YTD)</div>
                    <div className="text-3xl font-bold text-slate-600">12</div>
                </div>
            </div>

            <Section title="Notice History">
                <DataTable columns={columns} data={qins} keyField="id" />
            </Section>
        </div>
    );
};

export default QinManagerPage;