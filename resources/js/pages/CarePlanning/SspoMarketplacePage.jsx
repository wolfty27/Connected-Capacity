import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import DataTable from '../../components/UI/DataTable';

const SspoMarketplacePage = () => {
    const navigate = useNavigate(); // Assume a hook for navigation is available

    // Mock Data for SSPO Partners
    const [partners, setPartners] = useState([
        {
            id: 'SSPO-001',
            name: 'Alexis Lodge',
            services: ['Dementia Care', 'Assisted Living', 'Respite Care'],
            capacity: 'High',
            rating: 4.9,
            contact: 'intake@alexislodge.ca'
        },
        {
            id: 'SSPO-002',
            name: 'Wellhaus',
            services: ['Digital Health', 'Virtual Care', 'Remote Monitoring'],
            capacity: 'High',
            rating: 4.8,
            contact: 'partners@wellhaushealth.com'
        },
        {
            id: 'SSPO-003',
            name: 'Toronto Grace Health Centre',
            services: ['Complex Continuing Care', 'RPM', 'Palliative Care'],
            capacity: 'Moderate',
            rating: 4.7,
            contact: 'admissions@torontograce.org'
        },
        {
            id: 'SSPO-004',
            name: 'Reconnect Community Health Services',
            services: ['Mental Health', 'Addictions', 'Community Support'],
            capacity: 'Moderate',
            rating: 4.6,
            contact: 'referrals@reconnect.on.ca'
        },
    ]);

    const columns = [
        { header: 'Partner Name', accessor: 'name' },
        {
            header: 'Services Offered',
            accessor: (row) => (
                <div className="flex flex-wrap gap-1">
                    {row.services.map((service, index) => (
                        <span key={index} className="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {service}
                        </span>
                    ))}
                </div>
            )
        },
        {
            header: 'Capacity Status',
            accessor: (row) => {
                const colors = {
                    'High': 'bg-emerald-100 text-emerald-800',
                    'Moderate': 'bg-amber-100 text-amber-800',
                    'Low': 'bg-rose-100 text-rose-800'
                };
                return (
                    <span className={`px-2 py-1 rounded-full text-xs font-bold ${colors[row.capacity]}`}>
                        {row.capacity}
                    </span>
                );
            }
        },
        { header: 'Rating', accessor: 'rating' },
        {
            header: 'Action',
            accessor: (row) => (
                <Button size="sm" onClick={() => alert(`Assigning service via ${row.name}... (Mock)`)}>
                    Assign Service
                </Button>
            )
        }
    ];

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">SSPO Partner Marketplace</h1>
                    <p className="text-slate-500 text-sm">Browse and assign care bundle components to Secondary Service Provider Organizations.</p>
                </div>
                <Button variant="secondary" onClick={() => alert('Refreshing partner data... (Mock)')}>
                    Refresh Directory
                </Button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Available Partners</div>
                    <div className="text-3xl font-bold text-teal-600">{partners.length}</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Avg. Capacity</div>
                    <div className="text-3xl font-bold text-amber-500">Moderate</div>
                </div>
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="text-xs font-bold text-slate-400 uppercase">Open Assignments</div>
                    <div className="text-3xl font-bold text-blue-600">7</div>
                </div>
            </div>

            <Section title="Current Partner Directory">
                <DataTable columns={columns} data={partners} keyField="id" />
            </Section>
        </div>
    );
};

export default SspoMarketplacePage;