import React from 'react';
import Card from '../../components/UI/Card';
import DataTable from '../../components/UI/DataTable';

const ReviewList = () => {
    // Mock Data
    const reviews = [
        { id: 1, patient: 'Sarah Connor', hospital: 'City Gen', acuity: 85, status: 'Pending' },
        { id: 2, patient: 'Kyle Reese', hospital: 'Westside', acuity: 45, status: 'In Review' },
        { id: 3, patient: 'John Doe', hospital: 'North Health', acuity: 92, status: 'Pending' },
        { id: 4, patient: 'Jane Smith', hospital: 'City Gen', acuity: 30, status: 'Approved' },
    ];

    const columns = [
        {
            header: 'Patient',
            accessor: 'patient',
            render: (row) => (
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600">
                        {row.patient.charAt(0)}
                    </div>
                    <span className="font-medium text-slate-900">{row.patient}</span>
                </div>
            )
        },
        { header: 'Hospital', accessor: 'hospital' },
        {
            header: 'Acuity Score',
            accessor: 'acuity',
            render: (row) => (
                <div className="w-full max-w-[120px]">
                    <div className="flex justify-between text-xs mb-1">
                        <span className="font-bold text-slate-700">{row.acuity}</span>
                        <span className="text-slate-400">/100</span>
                    </div>
                    <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div
                            className={`h-full rounded-full ${row.acuity > 80 ? 'bg-rose-500' :
                                    row.acuity > 50 ? 'bg-amber-500' : 'bg-emerald-500'
                                }`}
                            style={{ width: `${row.acuity}%` }}
                        ></div>
                    </div>
                </div>
            )
        },
        {
            header: 'Status',
            accessor: 'status',
            render: (row) => (
                <span className={`px-2 py-1 rounded-full text-xs font-bold ${row.status === 'Approved' ? 'bg-emerald-100 text-emerald-700' :
                        row.status === 'Pending' ? 'bg-slate-100 text-slate-600' :
                            'bg-blue-100 text-blue-700'
                    }`}>
                    {row.status}
                </span>
            )
        },
        {
            header: 'Actions',
            accessor: 'id',
            render: () => (
                <button className="text-slate-400 hover:text-teal-600 font-bold text-xl leading-none">
                    ...
                </button>
            )
        }
    ];

    return (
        <div className="space-y-6 animate-fade-in">
            <div className="flex justify-between items-end">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Transition Reviews</h1>
                    <p className="text-slate-500">Manage and prioritize patient transition needs.</p>
                </div>
                <button className="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition-colors border border-indigo-200 shadow-sm">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span className="font-bold text-sm">Prioritize with Gemini</span>
                </button>
            </div>

            <Card className="overflow-hidden">
                <DataTable columns={columns} data={reviews} />
            </Card>
        </div>
    );
};

export default ReviewList;
