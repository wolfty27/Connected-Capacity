import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Card from '../../components/UI/Card';

const QipFormPage = () => {
    const { qinId } = useParams();
    const navigate = useNavigate();
    
    const [formData, setFormData] = useState({
        rootCause: '',
        actionPlan: '',
        targetDate: '',
        responsible: ''
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        alert('QIP Submitted to OHaH for Approval (Mock)');
        navigate('/qin');
    };

    return (
        <div className="max-w-3xl mx-auto space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Quality Improvement Plan (QIP)</h1>
                    <p className="text-slate-500 text-sm">Remediation for Notice: <span className="font-mono font-bold text-slate-700">{qinId}</span></p>
                </div>
            </div>

            <div className="bg-rose-50 border border-rose-200 p-4 rounded-lg flex items-start gap-3">
                <svg className="w-6 h-6 text-rose-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div>
                    <h3 className="font-bold text-rose-800">Breach Details</h3>
                    <p className="text-sm text-rose-700">Referral Acceptance Rate dropped to 94% (Band C) for the period of Oct 1 - Oct 15.</p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card title="1. Root Cause Analysis">
                    <p className="text-sm text-slate-500 mb-2">Describe the underlying factors contributing to the performance failure.</p>
                    <textarea 
                        required
                        rows={4}
                        className="w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        placeholder="e.g., Staff shortage on weekends caused intake delays..."
                        value={formData.rootCause}
                        onChange={(e) => setFormData({...formData, rootCause: e.target.value})}
                    />
                </Card>

                <Card title="2. Corrective Action Plan">
                    <p className="text-sm text-slate-500 mb-2">List specific steps to remediate the issue and prevent recurrence.</p>
                    <textarea 
                        required
                        rows={4}
                        className="w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        placeholder="e.g., 1. Hire 2 additional weekend coordinators. 2. Implement auto-accept logic for standard bundles..."
                        value={formData.actionPlan}
                        onChange={(e) => setFormData({...formData, actionPlan: e.target.value})}
                    />
                </Card>

                <Card title="3. Governance & Timeline">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Target Resolution Date</label>
                            <input 
                                type="date" 
                                required
                                className="w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                value={formData.targetDate}
                                onChange={(e) => setFormData({...formData, targetDate: e.target.value})}
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Person Responsible</label>
                            <input 
                                type="text" 
                                required
                                className="w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="Name / Role"
                                value={formData.responsible}
                                onChange={(e) => setFormData({...formData, responsible: e.target.value})}
                            />
                        </div>
                    </div>
                </Card>

                <div className="flex justify-end gap-4">
                    <Button variant="secondary" type="button" onClick={() => navigate('/qin')}>Cancel</Button>
                    <Button type="submit">Submit QIP for Approval</Button>
                </div>
            </form>
        </div>
    );
};

export default QipFormPage;