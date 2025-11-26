import React from 'react';

const PartnerPerformanceTable = ({ partners, compact = false }) => {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div className={`border-b border-slate-100 bg-slate-50/50 flex justify-between items-center ${compact ? 'px-3 py-2' : 'px-6 py-4'}`}>
                <h3 className={`font-bold text-slate-700 ${compact ? 'text-xs' : 'text-sm'}`}>Partner (SSPO) Performance</h3>
                <button className="text-xs text-teal-700 font-medium hover:underline">View Marketplace</button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm text-left">
                    <thead className={`text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-100 ${compact ? 'text-[10px]' : ''}`}>
                        <tr>
                            <th className={`${compact ? 'px-2 py-2' : 'px-6 py-3'}`}>Organization</th>
                            <th className={`${compact ? 'px-2 py-2' : 'px-6 py-3'}`}>Specialty</th>
                            {!compact && <th className="px-6 py-3">Active Assignments</th>}
                            {!compact && <th className="px-6 py-3">Acceptance Rate</th>}
                            <th className={`${compact ? 'px-2 py-2 text-right' : 'px-6 py-3'}`}>Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {partners.map((partner) => (
                            <tr key={partner.id} className={`hover:bg-slate-50 ${partner.status === 'warning' ? 'hover:bg-amber-50/30' : ''}`}>
                                <td className={`${compact ? 'px-2 py-2 text-xs' : 'px-6 py-4'} font-medium text-slate-800`}>
                                    <div className={compact ? 'truncate max-w-[100px]' : ''}>{partner.name}</div>
                                </td>
                                <td className={`${compact ? 'px-2 py-2 text-xs' : 'px-6 py-4'} text-slate-500`}>
                                    <div className={compact ? 'truncate max-w-[80px]' : ''}>{partner.specialty}</div>
                                </td>
                                {!compact && <td className="px-6 py-4">{partner.active_assignments}</td>}
                                {!compact && (
                                    <td className={`px-6 py-4 font-bold ${partner.acceptance_rate >= 95 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                        {partner.acceptance_rate}%
                                    </td>
                                )}
                                <td className={`${compact ? 'px-2 py-2 text-right' : 'px-6 py-4'}`}>
                                    <span className={`inline-block w-2 h-2 rounded-full ${partner.status === 'good' ? 'bg-emerald-500' :
                                        partner.status === 'warning' ? 'bg-amber-500 animate-pulse' : 'bg-slate-300'
                                        }`} title={partner.status}></span>
                                </td>
                            </tr>
                        ))}
                        {partners.length === 0 && (
                            <tr>
                                <td colSpan={compact ? "3" : "5"} className={`${compact ? 'px-3 py-4' : 'px-6 py-8'} text-center text-slate-400`}>
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
