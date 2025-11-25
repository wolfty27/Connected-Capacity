import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Spinner from '../../components/UI/Spinner';
import Button from '../../components/UI/Button';

const CareAssignmentDetailPage = () => {
    const { id } = useParams();
    const [assignment, setAssignment] = useState(null);
    const [loading, setLoading] = useState(true);
    const [updating, setUpdating] = useState(false);

    useEffect(() => {
        fetchAssignment();
    }, [id]);

    const fetchAssignment = async () => {
        try {
            const response = await api.get(`/api/care-assignments/${id}`);
            setAssignment(response.data);
        } catch (error) {
            console.error('Failed to fetch assignment:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleStatusChange = async (newStatus) => {
        setUpdating(true);
        try {
            const response = await api.put(`/api/care-assignments/${id}`, { status: newStatus });
            setAssignment(prev => ({ ...prev, status: response.data.status }));
        } catch (error) {
            console.error('Failed to update status:', error);
            alert('Failed to update status');
        } finally {
            setUpdating(false);
        }
    };

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;
    if (!assignment) return <div className="p-12 text-center">Assignment not found.</div>;

    const patientName = assignment.patient?.user?.name || 'Unknown Patient';
    const assignedTo = assignment.assigned_user?.name || 'Unassigned';

    return (
        <Section 
            title={`Assignment #${assignment.id}`} 
            description={`For ${patientName}`}
        >
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="md:col-span-2 space-y-6">
                    <Card title="Details">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-xs text-gray-500 uppercase">Status</label>
                                <div className="mt-1">
                                    <span className={`px-2 py-1 rounded-full text-sm font-medium ${
                                        assignment.status === 'completed' ? 'bg-green-100 text-green-800' :
                                        assignment.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                        'bg-gray-100 text-gray-800'
                                    }`}>
                                        {assignment.status}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label className="text-xs text-gray-500 uppercase">Start Date</label>
                                <p className="font-medium">{assignment.start_date}</p>
                            </div>
                            <div>
                                <label className="text-xs text-gray-500 uppercase">Assigned Staff</label>
                                <p className="font-medium">{assignedTo}</p>
                            </div>
                        </div>
                    </Card>

                    {/* Action Buttons */}
                    <Card title="Actions">
                        <div className="flex space-x-4">
                            {assignment.status !== 'in_progress' && assignment.status !== 'completed' && (
                                <Button 
                                    onClick={() => handleStatusChange('in_progress')}
                                    disabled={updating}
                                >
                                    Start Visit
                                </Button>
                            )}
                            {assignment.status === 'in_progress' && (
                                <Button 
                                    onClick={() => handleStatusChange('completed')}
                                    disabled={updating}
                                    className="bg-green-600 hover:bg-green-700 text-white"
                                >
                                    Complete Visit
                                </Button>
                            )}
                            {assignment.status !== 'cancelled' && assignment.status !== 'completed' && (
                                <Button 
                                    variant="danger"
                                    onClick={() => handleStatusChange('cancelled')}
                                    disabled={updating}
                                    className="bg-red-50 text-red-600 hover:bg-red-100"
                                >
                                    Cancel
                                </Button>
                            )}
                        </div>
                    </Card>
                </div>

                <div className="md:col-span-1">
                    <Card title="Patient Context">
                        <div className="space-y-2">
                             <p className="text-sm text-gray-600">
                                 To view full clinical history and transition needs, visit the patient profile.
                             </p>
                             {assignment.patient_id && (
                                 <Button 
                                     variant="secondary" 
                                     className="w-full justify-center"
                                     onClick={() => window.location.href = `/patients/${assignment.patient_id}`}
                                 >
                                     View Patient Profile
                                 </Button>
                             )}
                        </div>
                    </Card>
                </div>
            </div>
        </Section>
    );
};

export default CareAssignmentDetailPage;