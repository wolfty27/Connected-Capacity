import React, { useState } from 'react';
import axios from 'axios';
import Button from '../UI/Button';

const AssignmentConfigurator = ({ serviceLine, onConfirm, onCancel }) => {
    const [mode, setMode] = useState('internal'); // 'internal' | 'sspo'
    const [selectedStaff, setSelectedStaff] = useState(null);
    const [selectedSspo, setSelectedSspo] = useState(null);
    const [fteProjection, setFteProjection] = useState(null);
    const [sspoEstimate, setSspoEstimate] = useState(null);

    // Mock Internal Staff
    const staffList = [
        { id: 1, name: 'Nurse Joy', type: 'full_time', current_hours: 32 },
        { id: 2, name: 'Nurse Jackie', type: 'part_time', current_hours: 15 },
        { id: 3, name: 'Greg House', type: 'casual', current_hours: 5 },
    ];

    // Mock SSPOs (In real app, filter by capability matching serviceLine)
    const sspoList = [
        { id: 6, name: 'Alexis Lodge', type: 'SSPO', capabilities: ['Dementia'] },
        { id: 7, name: 'Wellhaus', type: 'SSPO', capabilities: ['Digital'] },
        { id: 9, name: 'Grace Hospital', type: 'SSPO', capabilities: ['RPM', 'Rehab'] },
    ];

    const handleStaffSelect = async (staff) => {
        setSelectedStaff(staff);
        // Call FTE Projection API
        try {
            const res = await axios.post('/api/v2/staffing/fte-project', { type: staff.type });
            setFteProjection(res.data);
        } catch (e) {
            console.error(e);
        }
    };

    const handleSspoSelect = async (sspo) => {
        setSelectedSspo(sspo);
        // Call Estimation API
        try {
            const res = await axios.post('/api/v2/assignments/sspo-estimate', {
                sspo_id: sspo.id,
                frequency_rule: 'Daily', // Mock, pass real props
                duration_minutes: 60
            });
            setSspoEstimate(res.data);
        } catch (e) {
            console.error(e);
        }
    };

    const handleConfirm = () => {
        if (mode === 'internal' && selectedStaff) {
            onConfirm({ type: 'internal', staff: selectedStaff });
        } else if (mode === 'sspo' && selectedSspo) {
            onConfirm({ type: 'sspo', partner: selectedSspo, estimate: sspoEstimate });
        }
    };

    return (
        <div className="bg-white p-6 rounded-lg border border-slate-200 shadow-lg mt-2">
            <h3 className="font-bold text-slate-800 mb-4">Configure Assignment: {serviceLine}</h3>
            
            <div className="flex gap-4 mb-4 border-b border-slate-100 pb-2">
                <button 
                    onClick={() => setMode('internal')}
                    className={`pb-2 text-sm font-bold ${mode === 'internal' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-slate-500'}`}
                >
                    Internal Staff (SPO)
                </button>
                <button 
                    onClick={() => setMode('sspo')}
                    className={`pb-2 text-sm font-bold ${mode === 'sspo' ? 'text-teal-600 border-b-2 border-teal-600' : 'text-slate-500'}`}
                >
                    Partner Network (SSPO)
                </button>
            </div>

            {mode === 'internal' && (
                <div className="space-y-4">
                    <p className="text-sm text-slate-500">Select a staff member. Priority: Full-Time.</p>
                    <div className="grid grid-cols-1 gap-2">
                        {staffList.map(staff => (
                            <div 
                                key={staff.id} 
                                onClick={() => handleStaffSelect(staff)}
                                className={`p-3 border rounded cursor-pointer flex justify-between items-center ${selectedStaff?.id === staff.id ? 'border-teal-500 bg-teal-50' : 'border-slate-200 hover:bg-slate-50'}`}
                            >
                                <div>
                                    <div className="font-bold text-slate-800">{staff.name}</div>
                                    <div className="text-xs text-slate-500 capitalize">{staff.type.replace('_', ' ')}</div>
                                </div>
                                <div className="text-xs bg-slate-100 px-2 py-1 rounded">{staff.current_hours}h/wk</div>
                            </div>
                        ))}
                    </div>

                    {fteProjection && (
                        <div className={`p-3 rounded border ${fteProjection.projected_band === 'GREEN' ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'}`}>
                            <div className="text-xs font-bold uppercase mb-1">FTE Compliance Impact</div>
                            <div className="flex justify-between items-center">
                                <span className="text-sm">Current: {fteProjection.current_ratio}%</span>
                                <span className="font-bold">Projected: {fteProjection.projected_ratio}%</span>
                            </div>
                            {fteProjection.projected_band !== 'GREEN' && (
                                <div className="text-xs text-amber-700 mt-1">
                                    Warning: This assignment lowers FTE ratio below 80%.
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {mode === 'sspo' && (
                <div className="space-y-4">
                    <p className="text-sm text-slate-500">Select a partner organization.</p>
                    <div className="grid grid-cols-1 gap-2">
                        {sspoList.map(sspo => (
                            <div 
                                key={sspo.id} 
                                onClick={() => handleSspoSelect(sspo)}
                                className={`p-3 border rounded cursor-pointer flex justify-between items-center ${selectedSspo?.id === sspo.id ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}`}
                            >
                                <div>
                                    <div className="font-bold text-slate-800">{sspo.name}</div>
                                    <div className="flex gap-1 mt-1">
                                        {sspo.capabilities.map(c => <span key={c} className="text-[10px] bg-blue-100 text-blue-800 px-1 rounded">{c}</span>)}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {sspoEstimate && (
                        <div className="bg-slate-50 p-3 rounded border border-slate-200">
                            <h4 className="text-xs font-bold text-slate-500 uppercase mb-2">Workload Estimate</h4>
                            <div className="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span className="text-slate-500">Hours/Week:</span>
                                    <div className="font-bold">{sspoEstimate.hours_per_week}h</div>
                                </div>
                                <div>
                                    <span className="text-slate-500">Total Hours:</span>
                                    <div className="font-bold">{sspoEstimate.total_hours}h</div>
                                </div>
                                <div>
                                    <span className="text-slate-500">Travel:</span>
                                    <div className="font-bold">~{sspoEstimate.estimated_travel_km} km</div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="flex justify-end gap-2 mt-6 pt-4 border-t border-slate-100">
                <Button variant="secondary" onClick={onCancel} size="sm">Cancel</Button>
                <Button onClick={handleConfirm} size="sm" disabled={!selectedStaff && !selectedSspo}>Confirm Assignment</Button>
            </div>
        </div>
    );
};

export default AssignmentConfigurator;