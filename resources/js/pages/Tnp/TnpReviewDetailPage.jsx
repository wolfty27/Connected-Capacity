import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Button from '../../components/UI/Button';

const TnpReviewDetailPage = () => {
    const { patientId } = useParams();
    const [tnp, setTnp] = useState(null);
    const [loading, setLoading] = useState(true);
    const [analyzing, setAnalyzing] = useState(false);

    useEffect(() => {
        const fetchTnp = async () => {
            try {
                // First try to fetch existing TNP
                const response = await axios.get(`/api/patients/${patientId}/tnp`);
                setTnp(response.data);
            } catch (error) {
                if (error.response && error.response.status === 404) {
                    // If not found, we might need to create one or just show empty state
                    // For now, we'll set null
                    setTnp(null);
                } else {
                    console.error('Failed to fetch TNP:', error);
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

    if (loading) return <div>Loading...</div>;

    if (!tnp) {
        return (
            <Section title="Transition Needs Profile">
                <p>No profile found for this patient.</p>
                {/* In a real app, button to create one would go here */}
            </Section>
        );
    }

    return (
        <div className="space-y-6">
            <Section title="Transition Needs Profile">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card title="Clinical Narrative">
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
        </div>
    );
};

export default TnpReviewDetailPage;
