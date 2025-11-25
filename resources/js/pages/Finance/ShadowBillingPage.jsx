import React, { useState } from 'react';
import axios from 'axios';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Card from '../../components/UI/Card';

const ShadowBillingPage = () => {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [summary, setSummary] = useState(null);

    const handleGenerateSummary = async () => {
        if (startDate && endDate) {
            try {
                const response = await axios.post('/api/v2/finance/shadow-billing', {
                    start_date: startDate,
                    end_date: endDate
                });
                setSummary({
                    totalEncounters: response.data.line_items.length, // Or map to real encounters if detailed
                    totalPatients: response.data.total_patients,
                    estimatedValue: response.data.total_amount,
                    lineItems: response.data.line_items
                });
            } catch (error) {
                console.error("Billing gen failed", error);
                alert("Failed to generate billing report.");
            }
        } else {
            alert('Please select a start and end date.');
        }
    };

    const handleExportCSV = () => {
        if (summary) {
            alert('Generating Shadow Billing CSV for selected period... (Mock Download)');
            // In a real scenario, an API call would trigger CSV generation and download.
        } else {
            alert('Please generate a summary first.');
        }
    };

    return (
        <div className="space-y-6 max-w-4xl mx-auto py-8">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Shadow Billing Engine</h1>
                    <p className="text-slate-500 text-sm">Generate zero-rate billing submissions for OHaH reporting.</p>
                </div>
            </div>

            <Card title="Billing Period Selection">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="startDate" className="block text-sm font-medium text-slate-700">Start Date</label>
                        <input
                            type="date"
                            id="startDate"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring focus:ring-teal-500 focus:ring-opacity-50"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                        />
                    </div>
                    <div>
                        <label htmlFor="endDate" className="block text-sm font-medium text-slate-700">End Date</label>
                        <input
                            type="date"
                            id="endDate"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring focus:ring-teal-500 focus:ring-opacity-50"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                        />
                    </div>
                </div>
                <div className="mt-6 flex justify-end">
                    <Button onClick={handleGenerateSummary} disabled={!startDate || !endDate}>
                        Generate Summary
                    </Button>
                </div>
            </Card>

            {summary && (
                <div className="space-y-6">
                    <Card title="Billing Summary (Zero-Rate)">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p className="text-sm text-slate-500">Total Active Plans</p>
                                <p className="text-2xl font-bold text-slate-800">{summary.totalEncounters}</p>
                            </div>
                            <div>
                                <p className="text-sm text-slate-500">Total Unique Patients</p>
                                <p className="text-2xl font-bold text-slate-800">{summary.totalPatients}</p>
                            </div>
                            <div>
                                <p className="text-sm text-slate-500">Estimated Value (Pro-rated)</p>
                                <p className="text-2xl font-bold text-slate-800">${summary.estimatedValue}</p>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end">
                            <Button onClick={handleExportCSV}>
                                Export to CSV
                            </Button>
                        </div>
                    </Card>

                    <Card title="Line Items (Pro-rated)">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Patient</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Bundle</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Period</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Days</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-slate-200">
                                    {summary.lineItems.map((item, idx) => (
                                        <tr key={idx}>
                                            <td className="px-4 py-2 whitespace-nowrap text-sm font-medium text-slate-900">{item.patient_name}</td>
                                            <td className="px-4 py-2 whitespace-nowrap text-sm text-slate-500">{item.bundle}</td>
                                            <td className="px-4 py-2 whitespace-nowrap text-sm text-slate-500">{item.start_date} to {item.end_date}</td>
                                            <td className="px-4 py-2 whitespace-nowrap text-sm text-slate-500">{item.active_days}</td>
                                            <td className="px-4 py-2 whitespace-nowrap text-sm font-bold text-slate-700">${item.total_amount}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>
            )}

            <div className="bg-blue-50 border border-blue-200 p-4 rounded-lg text-sm text-blue-800">
                <p className="font-bold mb-2">Important Note on Shadow Billing:</p>
                <p>Shadow billing is a non-monetary reporting requirement by OHaH to track service utilization. It does not generate revenue for the SPO but is crucial for compliance and data submission.</p>
            </div>
        </div>
    );
};

export default ShadowBillingPage;