import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Button from '../../components/UI/Button';

const TnpReviewDetailPage = () => {
    const { patientId } = useParams();
    const navigate = useNavigate();
    const [tnp, setTnp] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [analyzing, setAnalyzing] = useState(false);

    useEffect(() => {
        const fetchTnp = async () => {
            try {
                // First try to fetch existing TNP
                const response = await axios.get(`/api/patients/${patientId}/tnp`);
                setTnp(response.data);
            } catch (error) {
                console.error('Failed to fetch TNP:', error);
                if (error.response && error.response.status === 404) {
                    setTnp(null);
                } else {
                    setError(error.message || 'An error occurred');
                }
            } finally {
                setLoading(false);
            }
        };

        fetchTnp();
    }, [patientId]);

    const handleAnalyze = async () => {
        if (!tnp) return;
        setAnalyzing(true);
        try {
            await axios.post(`/api/tnp/${tnp.id}/analyze`);
            // Poll or just reload for now
            alert('Analysis queued!'); 
            window.location.reload();
        } catch (error) {
            console.error('Analysis failed:', error);
            alert('Failed to start analysis.');
        } finally {
            setAnalyzing(false);
        }
    };

    if (loading) return <div className="p-12 text-center">Loading...</div>;
    if (error) return <div className="p-12 text-center text-red-600">Error: {error}</div>;

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-slate-900">Transition Needs Profile</h1>
                <Button 
                    className="bg-teal-600 hover:bg-teal-700 text-white"
                    onClick={() => navigate(`/care-bundles/create/${patientId}`)}
                >
                    Proceed to Care Delivery Plan →
                </Button>
            </div>

            {!tnp ? (
                <Section>
                    <p>No TNP profile found for this patient.</p>
                    <Button variant="secondary" className="mt-4" onClick={() => alert('Create TNP Flow (Mock)')}>+ Create TNP</Button>
                </Section>
            ) : (
                <>
                    <Section title="Clinical Context">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Card title="Narrative">
                                <p className="whitespace-pre-wrap">{tnp.narrative_summary || 'No narrative provided.'}</p>
                            </Card>
                            <Card title="Clinical Flags">
                                {tnp.clinical_flags && tnp.clinical_flags.length > 0 ? (
                                    <ul className="list-disc list-inside">
                                        {tnp.clinical_flags.map((flag, index) => (
                                            <li key={index}>{flag}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p>No flags identified.</p>
                                )}
                            </Card>
                        </div>
                    </Section>

                    <Section title="AI Analysis">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-lg font-medium">Gemini Insights</h3>
                            <Button onClick={handleAnalyze} disabled={analyzing}>
                                {analyzing ? 'Analyzing...' : 'Run AI Analysis'}
                            </Button>
                        </div>
                        
                        <Card>
                            <div className="mb-4">
                                <span className="font-semibold">Status: </span>
                                <span className={`capitalize ${tnp.ai_summary_status === 'completed' ? 'text-green-600' : 'text-yellow-600'}`}>
                                    {tnp.ai_summary_status}
                                </span>
                            </div>
                            {tnp.ai_summary_text && (
                                <div className="prose max-w-none">
                                    <h4 className="text-md font-semibold mb-2">SBAR Summary</h4>
                                    <p>{tnp.ai_summary_text}</p>
                                </div>
                            )}
                        </Card>
                    </Section>
                </>
            )}
        </div>
    );
};

export default TnpReviewDetailPage;
