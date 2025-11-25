import React, { useState } from 'react';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Button from '../../components/UI/Button';

const WeeklyHuddlePage = () => {
    // Mock Data Aggregation (would come from API)
    const stats = {
        period: 'Oct 15 - Oct 22',
        missed_visits: 2,
        tfs_breaches: 1,
        new_qins: 1,
        complaints: 0
    };

    const incidents = [
        { id: 1, type: 'Missed Care', patient: 'John Doe', date: 'Oct 18', reason: 'Staff Illness', status: 'Investigating' },
        { id: 2, type: 'TFS Breach', patient: 'Jane Smith', date: 'Oct 20', reason: 'No SSPO Capacity', status: 'Resolved' },
    ];

    const qips = [
        { id: 'QIN-2025-001', indicator: 'Referral Acceptance', status: 'Draft', due: 'Oct 25' }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="flex justify-between items-center border-b border-slate-200 pb-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Weekly Huddle Board</h1>
                    <p className="text-slate-500 text-sm">OHaH Joint Review • Period: <span className="font-bold text-slate-700">{stats.period}</span></p>
                </div>
                <div className="flex gap-2">
                    <Button variant="secondary" onClick={() => window.print()}>Print Agenda</Button>
                    <Button>Start Meeting Mode</Button>
                </div>
            </div>

            {/* METRICS ROW */}
            <div className="grid grid-cols-4 gap-4">
                <div className={`p-4 rounded-lg border ${stats.missed_visits > 0 ? 'bg-rose-50 border-rose-200' : 'bg-white border-slate-200'}`}>
                    <div className="text-xs font-bold uppercase text-slate-500">Missed Visits</div>
                    <div className={`text-2xl font-bold ${stats.missed_visits > 0 ? 'text-rose-600' : 'text-slate-700'}`}>{stats.missed_visits}</div>
                </div>
                <div className={`p-4 rounded-lg border ${stats.tfs_breaches > 0 ? 'bg-amber-50 border-amber-200' : 'bg-white border-slate-200'}`}>
                    <div className="text-xs font-bold uppercase text-slate-500">TFS Breaches</div>
                    <div className={`text-2xl font-bold ${stats.tfs_breaches > 0 ? 'text-amber-600' : 'text-slate-700'}`}>{stats.tfs_breaches}</div>
                </div>
                <div className={`p-4 rounded-lg border ${stats.new_qins > 0 ? 'bg-slate-50 border-slate-300' : 'bg-white border-slate-200'}`}>
                    <div className="text-xs font-bold uppercase text-slate-500">New QINs</div>
                    <div className="text-2xl font-bold text-slate-700">{stats.new_qins}</div>
                </div>
                <div className="p-4 rounded-lg border bg-white border-slate-200">
                    <div className="text-xs font-bold uppercase text-slate-500">Complaints</div>
                    <div className="text-2xl font-bold text-slate-700">{stats.complaints}</div>
                </div>
            </div>

            {/* MAIN BOARD */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full">
                
                {/* COL 1: Incident Review */}
                <div className="bg-slate-50 rounded-xl border border-slate-200 p-4">
                    <h3 className="font-bold text-slate-700 mb-4 flex items-center gap-2">
                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Incident Review
                    </h3>
                    <div className="space-y-3">
                        {incidents.map((inc) => (
                            <div key={inc.id} className="bg-white p-3 rounded shadow-sm border border-slate-200">
                                <div className="flex justify-between items-start">
                                    <span className="text-xs font-bold bg-rose-100 text-rose-800 px-2 py-0.5 rounded">{inc.type}</span>
                                    <span className="text-xs text-slate-400">{inc.date}</span>
                                </div>
                                <div className="font-bold text-sm text-slate-800 mt-2">{inc.patient}</div>
                                <div className="text-sm text-slate-600">Reason: {inc.reason}</div>
                                <div className="mt-2 pt-2 border-t border-slate-100 flex justify-between items-center">
                                    <span className="text-xs text-slate-500">Status: {inc.status}</span>
                                    <button className="text-xs text-blue-600 hover:underline">View RCA</button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* COL 2: QIP & Compliance */}
                <div className="bg-slate-50 rounded-xl border border-slate-200 p-4">
                    <h3 className="font-bold text-slate-700 mb-4 flex items-center gap-2">
                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        QIP Status
                    </h3>
                    <div className="space-y-3">
                        {qips.map((q) => (
                            <div key={q.id} className="bg-white p-3 rounded shadow-sm border border-slate-200">
                                <div className="text-xs font-bold text-slate-400">{q.id}</div>
                                <div className="font-bold text-sm text-slate-800">{q.indicator}</div>
                                <div className="text-xs text-amber-600 font-medium mt-1">Status: {q.status}</div>
                                <div className="text-xs text-slate-500 mt-2">Due Date: {q.due}</div>
                                <button className="w-full mt-2 text-xs bg-slate-50 border border-slate-200 py-1 rounded hover:bg-slate-100">Review Plan</button>
                            </div>
                        ))}
                        <div className="text-center py-4 text-slate-400 text-sm italic">
                            No other active QIPs.
                        </div>
                    </div>
                </div>

                {/* COL 3: Action Items */}
                <div className="bg-slate-50 rounded-xl border border-slate-200 p-4">
                    <h3 className="font-bold text-slate-700 mb-4 flex items-center gap-2">
                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Action Items
                    </h3>
                    <ul className="space-y-2">
                        <li className="bg-white p-2 rounded border border-slate-200 flex gap-2 items-start">
                            <input type="checkbox" className="mt-1 rounded text-teal-600" />
                            <span className="text-sm text-slate-700">Submit RCA for Missed Visit #1 by Friday.</span>
                        </li>
                        <li className="bg-white p-2 rounded border border-slate-200 flex gap-2 items-start">
                            <input type="checkbox" className="mt-1 rounded text-teal-600" />
                            <span className="text-sm text-slate-700">Update SSPO capacity for next week.</span>
                        </li>
                        <li className="bg-white p-2 rounded border border-slate-200 flex gap-2 items-start">
                            <input type="checkbox" className="mt-1 rounded text-teal-600" />
                            <span className="text-sm text-slate-700">Confirm holiday staffing schedule.</span>
                        </li>
                    </ul>
                    <button className="w-full mt-3 text-sm text-slate-500 hover:text-teal-600 font-medium border border-dashed border-slate-300 rounded py-2 hover:bg-white hover:border-teal-300 transition-all">
                        + Add Action Item
                    </button>
                </div>

            </div>
        </div>
    );
};

export default WeeklyHuddlePage;