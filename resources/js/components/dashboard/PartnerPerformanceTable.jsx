import React from 'react';

const PartnerPerformanceTable = ({ partners }) => {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h3 className="font-bold text-slate-700 text-sm">Partner (SSPO) Performance</h3>
                <button className="text-xs text-teal-700 font-medium hover:underline">View Marketplace</button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm text-left">
                    <thead className="text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th className="px-6 py-3">Organization</th>
                            <th className="px-6 py-3">Specialty</th>
                            <th className="px-6 py-3">Active Assignments</th>
                            <th className="px-6 py-3">Acceptance Rate</th>
                            <th className="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {partners.map((partner) => (
                            <tr key={partner.id} className={`hover:bg-slate-50 ${partner.status === 'warning' ? 'hover:bg-amber-50/30' : ''}`}>
                                <td className="px-6 py-4 font-medium text-slate-800">{partner.name}</td>
                                <td className="px-6 py-4 text-slate-500">{partner.specialty}</td>
                                <td className="px-6 py-4">{partner.active_assignments}</td>
                                <td className={`px-6 py-4 font-bold ${partner.acceptance_rate >= 95 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                    {partner.acceptance_rate}%
                                </td>
                                <td className="px-6 py-4">
                                    <span className={`inline-block w-2 h-2 rounded-full ${partner.status === 'good' ? 'bg-emerald-500' :
                                            partner.status === 'warning' ? 'bg-amber-500 animate-pulse' : 'bg-slate-300'
                                        }`}></span>
                                </td>
                            </tr>
                        ))}
                        {partners.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-6 py-8 text-center text-slate-400">
                                    No active partners found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default PartnerPerformanceTable;
