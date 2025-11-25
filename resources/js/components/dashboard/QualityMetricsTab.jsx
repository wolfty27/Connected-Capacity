import React from 'react';

const QualityMetricsTab = ({ metrics }) => {
    // Default metrics if not provided
    const data = metrics || {
        ed_visits_avoidable: 0,
        readmissions: 0,
        complaints: 0,
        ltc_transition_requests: 0,
        time_to_first_service_avg: '18h',
        patient_satisfaction: 96,
        staff_satisfaction: 92
    };

    const MetricRow = ({ label, value, target, status = 'success' }) => (
        <div className="flex items-center justify-between py-4 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 px-4 -mx-4 transition-colors">
            <div>
                <p className="text-sm font-medium text-slate-700">{label}</p>
                <p className="text-xs text-slate-400">Target: {target}</p>
            </div>
            <div className="text-right">
                <p className={`text-lg font-bold ${status === 'success' ? 'text-emerald-600' : status === 'warning' ? 'text-amber-600' : 'text-rose-600'}`}>
                    {value}
                </p>
            </div>
        </div>
    );

    return (
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h3 className="font-bold text-slate-700 text-sm">Quality & Compliance (RFP Metrics)</h3>
                <button className="text-xs text-indigo-600 font-medium hover:underline">Download Report</button>
            </div>

            <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">

                {/* Clinical Safety */}
                <div>
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Clinical Safety</h4>
                    <div className="space-y-1">
                        <MetricRow
                            label="Avoidable ED Visits (7d)"
                            value={data.ed_visits_avoidable}
                            target="0%"
                            status={data.ed_visits_avoidable === 0 ? 'success' : 'warning'}
                        />
                        <MetricRow
                            label="Hospital Readmissions (30d)"
                            value={data.readmissions}
                            target="0%"
                            status={data.readmissions === 0 ? 'success' : 'warning'}
                        />
                        <MetricRow
                            label="Adverse Events"
                            value="0"
                            target="0"
                            status="success"
                        />
                    </div>
                </div>

                {/* Service Excellence */}
                <div>
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Service Excellence</h4>
                    <div className="space-y-1">
                        <MetricRow
                            label="Avg. Time to First Service"
                            value={data.time_to_first_service_avg}
                            target="< 24h"
                            status="success"
                        />
                        <MetricRow
                            label="Patient Satisfaction"
                            value={`${data.patient_satisfaction}%`}
                            target="> 95%"
                            status={data.patient_satisfaction > 95 ? 'success' : 'warning'}
                        />
                        <MetricRow
                            label="Staff Job Satisfaction"
                            value={`${data.staff_satisfaction}%`}
                            target="> 95%"
                            status={data.staff_satisfaction > 95 ? 'success' : 'warning'}
                        />
                        <MetricRow
                            label="Complaints (Active)"
                            value={data.complaints}
                            target="0"
                            status={data.complaints === 0 ? 'success' : 'warning'}
                        />
                    </div>
                </div>

            </div>

            <div className="bg-slate-50 px-6 py-3 border-t border-slate-100 text-xs text-slate-500 flex justify-between">
                <span>Last Audit: Today, 09:00 AM</span>
                <span>Compliance Status: <span className="font-bold text-emerald-600">Compliant</span></span>
            </div>
        </div>
    );
};

export default QualityMetricsTab;
