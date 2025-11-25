import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';
import { ChevronDown, ChevronUp, ArrowLeft, ArrowRight, Check, DollarSign } from 'lucide-react';
import PatientSummaryCard from '../../components/care/PatientSummaryCard';
import ServiceCard from '../../components/care/ServiceCard';
import BundleSummary from '../../components/care/BundleSummary';
import { INITIAL_SERVICES } from '../../data/careBundleConstants';

const CareBundleWizard = () => {
    const { patientId } = useParams();
    const navigate = useNavigate();
    const [step, setStep] = useState(1);
    const [patient, setPatient] = useState(null);
    const [tnp, setTnp] = useState(null);
    const [loading, setLoading] = useState(true);

    // Step 1 State
    const [bundles, setBundles] = useState([]);
    const [selectedBundle, setSelectedBundle] = useState(null);

    // Step 2 State
    const [services, setServices] = useState(INITIAL_SERVICES);
    const [expandedSection, setExpandedSection] = useState('CLINICAL');
    const [aiRecommendation, setAiRecommendation] = useState(null);
    const [isGeneratingAi, setIsGeneratingAi] = useState(false);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const [patientRes, tnpRes, bundlesRes] = await Promise.all([
                    axios.get(`/api/patients/${patientId}`).catch(err => {
                        console.error('Patient fetch failed', err);
                        return { data: { data: null } };
                    }),
                    axios.get(`/api/patients/${patientId}/tnp`).catch(err => {
                        console.warn('TNP fetch failed (likely 404), continuing without TNP data.', err);
                        return { data: null };
                    }),
                    axios.get('/api/v2/bundle-templates').catch(err => {
                        console.error('Bundles fetch failed', err);
                        return { data: [] };
                    })
                ]);

                if (patientRes.data.data) {
                    setPatient(patientRes.data.data);
                } else {
                    console.error("Critical: Patient data missing");
                }

                setTnp(tnpRes.data);

                // Enrich bundles with UI metadata (colors/bands) until DB has them
                const enrichedBundles = (bundlesRes.data || []).map(b => ({
                    ...b,
                    colorTheme: b.code === 'COMPLEX' ? 'green' : b.code === 'PALLIATIVE' ? 'purple' : 'blue',
                    band: b.code === 'COMPLEX' ? 'Band B' : b.code === 'PALLIATIVE' ? 'Band C' : 'Band A',
                    price: b.price || 1200
                }));
                setBundles(enrichedBundles);

                // Auto-select based on TNP
                if (tnpRes.data?.clinical_flags?.includes('Cognitive')) {
                    const dem = enrichedBundles.find(b => b.code === 'DEM-SUP');
                    if (dem) setSelectedBundle(dem);
                } else if (tnpRes.data?.clinical_flags?.includes('Wound')) {
                    const cpx = enrichedBundles.find(b => b.code === 'COMPLEX');
                    if (cpx) setSelectedBundle(cpx);
                } else {
                    setSelectedBundle(enrichedBundles.find(b => b.code === 'STD-MED') || enrichedBundles[0]);
                }

            } catch (error) {
                console.error('Failed to fetch wizard data', error);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, [patientId]);

    const handleUpdateService = (id, field, value) => {
        setServices(prev => prev.map(s =>
            s.id === id ? { ...s, [field]: value } : s
        ));
    };

    const totalCost = services.reduce((acc, curr) => {
        return acc + (curr.costPerVisit * curr.currentFrequency * curr.currentDuration);
    }, 0);

    const generateRecommendation = async () => {
        setIsGeneratingAi(true);
        // Simulate API call
        setTimeout(() => {
            setAiRecommendation("Recommendation: Based on the high TNP score (82), consider increasing Personal Support hours to 4x weekly to assist with ADLs.");
            setIsGeneratingAi(false);
        }, 1500);
    };

    const handlePublish = async () => {
        try {
            // Map frontend service codes to backend expectations
            const serviceCodeMap = {
                'RN/RPN': 'nursing',
                'PSW': 'psw',
                'OT': 'rehab',
                'PT': 'rehab',
                'SW': 'sw'
            };

            // Mock provider IDs for now
            const providerMap = {
                'CarePartners': 1,
                'VHA Home HealthCare': 2,
                'Paramed': 3,
                'SE Health': 4
            };

            const assignments = {};
            services.filter(s => s.currentFrequency > 0).forEach(s => {
                const key = serviceCodeMap[s.code] || s.code.toLowerCase();
                assignments[key] = {
                    type: 'external', // Default to external for now
                    partner: { id: providerMap[s.provider] || 1 },
                    freq: s.currentFrequency
                };
            });

            await axios.post('/api/v2/care-plans', {
                patient_id: patientId,
                bundle_id: selectedBundle?.code?.toLowerCase() || 'complex',
                assignments: assignments
            });

            // Show success message or redirect
            alert('Care Plan Published Successfully!');
            navigate('/dashboard');
        } catch (error) {
            console.error('Failed to publish plan', error);
            alert('Failed to publish plan. Please try again.');
        }
    };

    const AccordionHeader = ({ title, sectionKey }) => (
        <button
            onClick={() => setExpandedSection(expandedSection === sectionKey ? '' : sectionKey)}
            className="w-full flex justify-between items-center p-4 bg-slate-50 hover:bg-slate-100 border-b border-slate-200 transition-colors first:rounded-t-lg"
        >
            <span className="font-bold text-slate-700 uppercase text-sm tracking-wide">{title}</span>
            {expandedSection === sectionKey ? <ChevronUp className="w-4 h-4 text-slate-500" /> : <ChevronDown className="w-4 h-4 text-slate-500" />}
        </button>
    );

    if (loading) return <div className="p-8 text-center text-slate-500">Loading Care Delivery Plan...</div>;

    return (
        <div className="flex h-[calc(100vh-64px)] overflow-hidden bg-white">
            <main className="flex-1 flex flex-col h-full overflow-hidden">
                {/* Top Header */}
                <header className="h-16 bg-white border-b border-slate-200 flex justify-between items-center px-8 shrink-0">
                    <div>
                        <h2 className="text-xl font-bold text-slate-800">Care Delivery Plan (Schedule 3)</h2>
                        <span className="text-sm text-slate-500">Episode ID: #{patient?.id || '---'}</span>
                    </div>
                    <div className="flex gap-3">
                        {step === 2 ? (
                            <>
                                <button
                                    onClick={() => setStep(1)}
                                    className="px-4 py-2 border border-slate-300 rounded-md text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-2"
                                >
                                    <ArrowLeft className="w-4 h-4" /> Back to Bundles
                                </button>
                                <button
                                    onClick={handlePublish}
                                    className="px-4 py-2 bg-blue-700 text-white rounded-md font-medium hover:bg-blue-800 shadow-sm flex items-center gap-2"
                                >
                                    Next: Review & Publish <ArrowRight className="w-4 h-4" />
                                </button>
                            </>
                        ) : (
                            <button
                                onClick={() => setStep(2)}
                                disabled={!selectedBundle}
                                className="px-4 py-2 bg-blue-700 text-white rounded-md font-medium hover:bg-blue-800 shadow-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next: Customize Services <ArrowRight className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                </header>

                {/* Scrollable Content */}
                <div className="flex-1 overflow-y-auto p-8">

                    <div className="flex flex-col xl:flex-row gap-8">

                        {/* Left Column: Patient Summary */}
                        <div className="xl:w-1/4 w-full shrink-0">
                            <PatientSummaryCard patient={patient} />
                        </div>

                        {/* Middle Column: Main Form */}
                        <div className="flex-1">
                            {/* Stepper Indicator */}
                            <div className="mb-8 flex items-center justify-between relative max-w-2xl mx-auto">
                                <div className="absolute left-0 top-1/2 w-full h-0.5 bg-slate-200 -z-10"></div>
                                <div className={`flex flex-col items-center bg-white px-4 ${step >= 1 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 ${step >= 1 ? 'bg-blue-600 text-white' : 'bg-slate-100'}`}>1</div>
                                    <span className="text-sm font-medium">Select Bundle</span>
                                </div>
                                <div className={`flex flex-col items-center bg-white px-4 ${step >= 2 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 ${step >= 2 ? 'bg-blue-600 text-white' : 'bg-slate-100'}`}>2</div>
                                    <span className="text-sm font-medium">Customize Services</span>
                                </div>
                                <div className={`flex flex-col items-center bg-white px-4 ${step >= 3 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 ${step >= 3 ? 'bg-blue-600 text-white' : 'bg-slate-100'}`}>3</div>
                                    <span className="text-sm font-medium">Review & Publish</span>
                                </div>
                            </div>

                            {step === 1 && (
                                <div className="space-y-6">
                                    <div className="mb-6">
                                        <h1 className="text-xl font-bold text-slate-900 mb-2">Step 1: Select Care Bundle</h1>
                                        <p className="text-sm text-slate-600">Based on the patient's TNP score and clinical flags, we recommend the following care bundles.</p>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {bundles.map(bundle => (
                                            <div
                                                key={bundle.id}
                                                onClick={() => setSelectedBundle(bundle)}
                                                className={`cursor-pointer rounded-xl border-2 p-6 transition-all hover:shadow-md ${selectedBundle?.id === bundle.id
                                                    ? `border-${bundle.colorTheme}-600 bg-${bundle.colorTheme}-50`
                                                    : 'border-slate-200 bg-white hover:border-slate-300'
                                                    }`}
                                            >
                                                <div className="flex justify-between items-start mb-4">
                                                    <span className={`px-3 py-1 rounded-full text-xs font-bold bg-${bundle.colorTheme}-100 text-${bundle.colorTheme}-700`}>
                                                        {bundle.band}
                                                    </span>
                                                    {selectedBundle?.id === bundle.id && <Check className={`w-5 h-5 text-${bundle.colorTheme}-600`} />}
                                                </div>
                                                <h3 className="text-lg font-bold text-slate-900 mb-2">{bundle.name}</h3>
                                                <p className="text-sm text-slate-600 mb-4 line-clamp-3">{bundle.description}</p>
                                                <div className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                                    <DollarSign className="w-4 h-4 text-slate-400" />
                                                    Est. ${bundle.price}/mo
                                                </div>
                                            </div>
                                        ))}

                                        {/* Custom Bundle Option */}
                                        <div
                                            onClick={() => setSelectedBundle({ id: 'custom', name: 'Custom Bundle', code: 'CUSTOM', colorTheme: 'slate', band: 'Flexible', price: 0, description: 'Build a fully customized care plan from scratch with all available services.' })}
                                            className={`cursor-pointer rounded-xl border-2 p-6 transition-all hover:shadow-md ${selectedBundle?.id === 'custom'
                                                ? 'border-slate-600 bg-slate-50'
                                                : 'border-slate-200 bg-white hover:border-slate-300'
                                                }`}
                                        >
                                            <div className="flex justify-between items-start mb-4">
                                                <span className="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                                    Flexible
                                                </span>
                                                {selectedBundle?.id === 'custom' && <Check className="w-5 h-5 text-slate-600" />}
                                            </div>
                                            <h3 className="text-lg font-bold text-slate-900 mb-2">Build Custom Bundle</h3>
                                            <p className="text-sm text-slate-600 mb-4">Build a fully customized care plan from scratch with all available services.</p>
                                            <div className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                                <DollarSign className="w-4 h-4 text-slate-400" />
                                                Variable Cost
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {step === 2 && (
                                <>
                                    <div className="mb-6">
                                        <h1 className="text-xl font-bold text-slate-900 mb-2">Step 2: Customize Services</h1>
                                        <p className="text-sm text-slate-600">Adjust the frequency and duration of services within the selected bundle based on clinical judgment. Services are auto-filled based on clinical rules.</p>
                                    </div>

                                    <div className="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                                        {/* CLINICAL SECTION */}
                                        <AccordionHeader title="CLINICAL" sectionKey="CLINICAL" />
                                        {expandedSection === 'CLINICAL' && (
                                            <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                                {services.filter(s => s.category === 'CLINICAL').map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))}
                                            </div>
                                        )}

                                        <AccordionHeader title="SPECIALIZED" sectionKey="SPECIALIZED" />
                                        {expandedSection === 'SPECIALIZED' && <div className="p-4 text-slate-500 text-sm">No specialized services selected.</div>}

                                        <AccordionHeader title="PERSONAL SUPPORT & DAILY LIVING" sectionKey="PERSONAL_SUPPORT" />
                                        {expandedSection === 'PERSONAL_SUPPORT' && (
                                            <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                                {services.filter(s => s.category === 'PERSONAL_SUPPORT').map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))}
                                            </div>
                                        )}

                                        <AccordionHeader title="SAFETY & MONITORING" sectionKey="DIGITAL" />
                                        {expandedSection === 'DIGITAL' && (
                                            <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                                {services.filter(s => s.category === 'DIGITAL').map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))}
                                            </div>
                                        )}

                                        <AccordionHeader title="SOCIAL & LOGISTICS" sectionKey="SOCIAL_SUPPORT" />
                                        {expandedSection === 'SOCIAL_SUPPORT' && (
                                            <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                                {services.filter(s => s.category === 'SOCIAL_SUPPORT').map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Right Column: Summary & Recommendation */}
                        <div className="xl:w-80 w-full shrink-0">
                            <BundleSummary
                                services={services}
                                totalCost={totalCost}
                                isGeneratingAi={isGeneratingAi}
                                aiRecommendation={aiRecommendation}
                                onGenerateAi={generateRecommendation}
                            />
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
};

export default CareBundleWizard;