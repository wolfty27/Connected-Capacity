import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../services/api';
import {
    ChevronDown,
    ChevronUp,
    ArrowLeft,
    ArrowRight,
    Check,
    DollarSign,
    AlertCircle,
    Loader2,
    CheckCircle2,
    Users,
    Star
} from 'lucide-react';
import PatientSummaryCard from '../../components/care/PatientSummaryCard';
import ServiceCard from '../../components/care/ServiceCard';
import BundleSummary from '../../components/care/BundleSummary';
import useServiceTypes, { mapCategory } from '../../hooks/useServiceTypes';
import careBundleBuilderApi from '../../services/careBundleBuilderApi';

/**
 * CareBundleWizard - Metadata-driven care bundle builder
 *
 * Implements a Workday-style workflow where:
 * 1. Bundles are pre-configured based on patient's TNP via metadata engine
 * 2. Services are auto-adjusted based on clinical rules
 * 3. Publishing triggers transition from queue to active patient profile
 */
const CareBundleWizard = () => {
    const { patientId } = useParams();
    const navigate = useNavigate();
    const [step, setStep] = useState(1);
    const [patient, setPatient] = useState(null);
    const [tnp, setTnp] = useState(null);
    const [loading, setLoading] = useState(true);
    const [publishing, setPublishing] = useState(false);
    const [carePlanId, setCarePlanId] = useState(null);

    // Fetch service types from API (SC-003)
    const { serviceTypes: apiServiceTypes, loading: servicesLoading, error: servicesError } = useServiceTypes();

    // Step 1 State - Bundle Selection
    const [bundles, setBundles] = useState([]);
    const [selectedBundle, setSelectedBundle] = useState(null);
    const [recommendedBundle, setRecommendedBundle] = useState(null);

    // Step 2 State - Service Configuration (initialized from API)
    const [services, setServices] = useState([]);
    const [expandedSection, setExpandedSection] = useState('CLINICAL');
    const [aiRecommendation, setAiRecommendation] = useState(null);
    const [isGeneratingAi, setIsGeneratingAi] = useState(false);

    // Initialize services from API when available
    useEffect(() => {
        if (apiServiceTypes.length > 0 && services.length === 0) {
            console.log('Setting services from apiServiceTypes');
            setServices(apiServiceTypes);
        }
    }, [apiServiceTypes, services.length]);



    // Step 3 State - Review & Publish
    const [publishSuccess, setPublishSuccess] = useState(false);
    const [transitionMessage, setTransitionMessage] = useState(null);

    useEffect(() => {
        fetchData();
    }, [patientId]);

    const fetchData = async () => {
        try {
            setLoading(true);

            // Fetch patient and TNP data
            const [patientRes, tnpRes] = await Promise.all([
                api.get(`/api/patients/${patientId}`).catch(err => {
                    console.error('Patient fetch failed', err);
                    return { data: { data: null } };
                }),
                api.get(`/api/patients/${patientId}/tnp`).catch(err => {
                    console.warn('TNP fetch failed (likely 404), continuing without TNP data.', err);
                    return { data: null };
                })
            ]);

            if (patientRes.data.data) {
                setPatient(patientRes.data.data);
            } else {
                console.error("Critical: Patient data missing");
            }

            setTnp(tnpRes.data);

            // Fetch bundles with metadata-driven configuration
            try {
                const bundleResponse = await careBundleBuilderApi.getBundles(patientId);
                const configuredBundles = bundleResponse.data || [];
                setBundles(configuredBundles);

                // Set recommended bundle from API
                if (bundleResponse.recommended_bundle) {
                    setRecommendedBundle(bundleResponse.recommended_bundle);
                    const recommended = configuredBundles.find(b => b.id === bundleResponse.recommended_bundle.id);
                    if (recommended) {
                        setSelectedBundle(recommended);
                        // Pre-populate services from recommended bundle
                        if (recommended.services) {
                            const normalizedServices = recommended.services.map(s => ({
                                ...s,
                                category: mapCategory(s.category || s.category_code)
                            }));
                            setServices(normalizedServices);
                        }
                    }
                } else if (configuredBundles.length > 0) {
                    setSelectedBundle(configuredBundles[0]);
                }
            } catch (bundleErr) {
                console.error('Bundle builder API failed, falling back to template API', bundleErr);
                // Fallback to basic bundle templates
                const bundlesRes = await api.get('/api/v2/bundle-templates').catch(() => ({ data: [] }));
                const enrichedBundles = (bundlesRes.data || []).map(b => ({
                    ...b,
                    colorTheme: b.code === 'COMPLEX' ? 'green' : b.code === 'PALLIATIVE' ? 'purple' : 'blue',
                    band: b.code === 'COMPLEX' ? 'Band B' : b.code === 'PALLIATIVE' ? 'Band C' : 'Band A',
                    price: b.price || 1200,
                    services: apiServiceTypes.length > 0 ? apiServiceTypes : services
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
            }

        } catch (error) {
            console.error('Failed to fetch wizard data', error);
        } finally {
            setLoading(false);
        }
    };

    // When bundle selection changes, update services
    const handleBundleSelect = (bundle) => {
        setSelectedBundle(bundle);
        if (bundle.services && bundle.services.length > 0) {
            const normalizedServices = bundle.services.map(s => ({
                ...s,
                category: mapCategory(s.category || s.category_code)
            }));
            setServices(normalizedServices);
        }
    };

    const handleUpdateService = (id, field, value) => {
        setServices(prev => prev.map(s =>
            s.id === id ? { ...s, [field]: value } : s
        ));
    };

    const totalCost = services.reduce((acc, curr) => {
        return acc + (curr.costPerVisit * curr.currentFrequency * curr.currentDuration);
    }, 0);

    const monthlyCost = careBundleBuilderApi.calculateMonthlyCost(services);

    const generateRecommendation = async () => {
        setIsGeneratingAi(true);
        // Simulate API call - could be connected to AI endpoint
        setTimeout(() => {
            setAiRecommendation("Recommendation: Based on the patient's TNP assessment and clinical flags, the metadata engine has auto-configured services. Consider reviewing Personal Support hours based on ADL needs.");
            setIsGeneratingAi(false);
        }, 1500);
    };

    // Step 2 -> Step 3: Build draft plan
    const handleProceedToReview = async () => {
        if (!selectedBundle) return;

        // Prevent custom bundle (not in database)
        if (selectedBundle.id === 'custom') {
            alert('Custom bundles are not yet supported. Please select a pre-configured bundle.');
            return;
        }

        try {
            setPublishing(true);

            // Build the care plan draft
            const formattedServices = careBundleBuilderApi.formatServicesForApi(services);
            console.log('Building plan with:', { patientId, bundleId: selectedBundle.id, services: formattedServices });

            const response = await careBundleBuilderApi.buildPlan(
                patientId,
                selectedBundle.id,
                formattedServices
            );

            setCarePlanId(response.data.id);
            setStep(3);
        } catch (error) {
            console.error('Failed to build care plan', error);
            const errorMsg = error.response?.data?.error || error.response?.data?.message || 'Unknown error';
            const validationErrors = error.response?.data?.errors;
            if (validationErrors) {
                alert(`Failed to create care plan:\n${JSON.stringify(validationErrors, null, 2)}`);
            } else {
                alert(`Failed to create care plan: ${errorMsg}`);
            }
        } finally {
            setPublishing(false);
        }
    };

    // Step 3: Publish plan and transition patient to active
    const handlePublish = async () => {
        if (!carePlanId) {
            // If no draft plan exists, create and publish in one go
            try {
                setPublishing(true);

                const formattedServices = careBundleBuilderApi.formatServicesForApi(services);
                const buildResponse = await careBundleBuilderApi.buildPlan(
                    patientId,
                    selectedBundle.id,
                    formattedServices
                );

                const publishResponse = await careBundleBuilderApi.publishPlan(
                    patientId,
                    buildResponse.data.id
                );

                setPublishSuccess(true);
                setTransitionMessage(publishResponse.message);

                // Redirect after success message
                setTimeout(() => {
                    navigate(`/patients/${patientId}`);
                }, 2000);
            } catch (error) {
                console.error('Failed to publish plan', error);
                alert('Failed to publish plan. Please try again.');
            } finally {
                setPublishing(false);
            }
        } else {
            // Publish existing draft
            try {
                setPublishing(true);

                const publishResponse = await careBundleBuilderApi.publishPlan(patientId, carePlanId);

                setPublishSuccess(true);
                setTransitionMessage(publishResponse.message);

                // Redirect after success message
                setTimeout(() => {
                    navigate(`/patients/${patientId}`);
                }, 2000);
            } catch (error) {
                console.error('Failed to publish plan', error);
                alert('Failed to publish plan. Please try again.');
            } finally {
                setPublishing(false);
            }
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

    if (loading || servicesLoading) return <div className="p-8 text-center text-slate-500">Loading Care Delivery Plan...</div>;

    if (servicesError) {
        return (
            <div className="p-8">
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong className="font-bold">Error loading services: </strong>
                    <span className="block sm:inline">{servicesError}</span>
                    <pre className="mt-2 text-xs bg-red-100 p-2 rounded overflow-auto">
                        {JSON.stringify(servicesError, null, 2)}
                    </pre>
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-[calc(100vh-64px)] overflow-hidden bg-white">
            <main className="flex-1 flex flex-col h-full overflow-hidden">
                {/* Top Header */}
                <header className="h-16 bg-white border-b border-slate-200 flex justify-between items-center px-8 shrink-0">
                    <div>
                        <h2 className="text-xl font-bold text-slate-800">Care Delivery Plan (Schedule 3)</h2>
                        <div className="flex items-center gap-2 text-sm text-slate-500">
                            <span>Episode ID: #{patient?.id || '---'}</span>
                            {patient?.is_in_queue && (
                                <span className="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs font-medium">
                                    In Queue
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-3">
                        {step === 1 && (
                            <button
                                onClick={() => setStep(2)}
                                disabled={!selectedBundle}
                                className="px-4 py-2 bg-blue-700 text-white rounded-md font-medium hover:bg-blue-800 shadow-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next: Customize Services <ArrowRight className="w-4 h-4" />
                            </button>
                        )}
                        {step === 2 && (
                            <>
                                <button
                                    onClick={() => setStep(1)}
                                    className="px-4 py-2 border border-slate-300 rounded-md text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-2"
                                >
                                    <ArrowLeft className="w-4 h-4" /> Back to Bundles
                                </button>
                                <button
                                    onClick={handleProceedToReview}
                                    disabled={publishing}
                                    className="px-4 py-2 bg-blue-700 text-white rounded-md font-medium hover:bg-blue-800 shadow-sm flex items-center gap-2 disabled:opacity-50"
                                >
                                    {publishing ? (
                                        <>
                                            <Loader2 className="w-4 h-4 animate-spin" /> Processing...
                                        </>
                                    ) : (
                                        <>
                                            Next: Review & Publish <ArrowRight className="w-4 h-4" />
                                        </>
                                    )}
                                </button>
                            </>
                        )}
                        {step === 3 && !publishSuccess && (
                            <>
                                <button
                                    onClick={() => setStep(2)}
                                    className="px-4 py-2 border border-slate-300 rounded-md text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-2"
                                >
                                    <ArrowLeft className="w-4 h-4" /> Back to Services
                                </button>
                                <button
                                    onClick={handlePublish}
                                    disabled={publishing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md font-medium hover:bg-green-700 shadow-sm flex items-center gap-2 disabled:opacity-50"
                                >
                                    {publishing ? (
                                        <>
                                            <Loader2 className="w-4 h-4 animate-spin" /> Publishing...
                                        </>
                                    ) : (
                                        <>
                                            <Check className="w-4 h-4" /> Publish & Activate Patient
                                        </>
                                    )}
                                </button>
                            </>
                        )}
                    </div>
                </header>

                {/* Scrollable Content Container */}
                <div className="flex-1 flex overflow-hidden">

                    {/* Left Column: Patient Summary (Independent Scroll) */}
                    <div className="w-1/4 min-w-[320px] shrink-0 overflow-y-auto border-r border-slate-200 bg-slate-50/50 p-6">
                        <PatientSummaryCard patient={patient} />

                        {/* Queue Status Card */}
                        {patient?.is_in_queue && (
                            <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div className="flex items-center gap-2 text-yellow-700 font-medium mb-2">
                                    <Users className="w-4 h-4" />
                                    Patient In Queue
                                </div>
                                <p className="text-sm text-yellow-600">
                                    This patient is currently in the intake queue. Publishing this care bundle will transition them to an active patient profile.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Middle Column: Main Form (Independent Scroll) */}
                    <div className="flex-1 overflow-y-auto p-8 bg-white">
                        {/* Stepper Indicator */}
                        <div className="mb-8 flex items-center justify-between relative max-w-2xl mx-auto">
                            <div className="absolute left-0 top-1/2 w-full h-0.5 bg-slate-200 -z-10"></div>
                            <div className={`flex flex-col items-center bg-white px-4 ${step >= 1 ? 'text-blue-600' : 'text-slate-400'}`}>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 ${step >= 1 ? 'bg-blue-600 text-white' : 'bg-slate-100'}`}>
                                    {step > 1 ? <Check className="w-4 h-4" /> : '1'}
                                </div>
                                <span className="text-sm font-medium">Select Bundle</span>
                            </div>
                            <div className={`flex flex-col items-center bg-white px-4 ${step >= 2 ? 'text-blue-600' : 'text-slate-400'}`}>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 ${step >= 2 ? 'bg-blue-600 text-white' : 'bg-slate-100'}`}>
                                    {step > 2 ? <Check className="w-4 h-4" /> : '2'}
                                </div>
                                <span className="text-sm font-medium">Customize Services</span>
                            </div>
                            <div className={`flex flex-col items-center bg-white px-4 ${step >= 3 ? 'text-blue-600' : 'text-slate-400'}`}>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 ${step >= 3 ? 'bg-blue-600 text-white' : 'bg-slate-100'}`}>3</div>
                                <span className="text-sm font-medium">Review & Publish</span>
                            </div>
                        </div>

                        {/* Step 1: Bundle Selection */}
                        {step === 1 && (
                            <div className="space-y-6">
                                <div className="mb-6">
                                    <h1 className="text-xl font-bold text-slate-900 mb-2">Step 1: Select Care Bundle</h1>
                                    <p className="text-sm text-slate-600">
                                        Based on the patient's TNP score and clinical flags, bundles have been pre-configured by the metadata engine.
                                    </p>
                                    {recommendedBundle && (
                                        <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg flex items-start gap-2">
                                            <Star className="w-5 h-5 text-green-600 shrink-0 mt-0.5" />
                                            <div>
                                                <span className="font-medium text-green-800">Recommended: {recommendedBundle.name}</span>
                                                <p className="text-sm text-green-700 mt-0.5">{recommendedBundle.reason}</p>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {bundles.map(bundle => (
                                        <div
                                            key={bundle.id}
                                            onClick={() => handleBundleSelect(bundle)}
                                            className={`cursor-pointer rounded-xl border-2 p-6 transition-all hover:shadow-md ${selectedBundle?.id === bundle.id
                                                ? 'border-blue-600 bg-blue-50'
                                                : 'border-slate-200 bg-white hover:border-slate-300'
                                                }`}
                                        >
                                            <div className="flex justify-between items-start mb-4">
                                                <div className="flex items-center gap-2">
                                                    <span className={`px-3 py-1 rounded-full text-xs font-bold ${bundle.colorTheme === 'green' ? 'bg-green-100 text-green-700' :
                                                        bundle.colorTheme === 'purple' ? 'bg-purple-100 text-purple-700' :
                                                            bundle.colorTheme === 'amber' ? 'bg-amber-100 text-amber-700' :
                                                                'bg-blue-100 text-blue-700'
                                                        }`}>
                                                        {bundle.band}
                                                    </span>
                                                    {bundle.isRecommended && (
                                                        <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />
                                                    )}
                                                </div>
                                                {selectedBundle?.id === bundle.id && <Check className="w-5 h-5 text-blue-600" />}
                                            </div>
                                            <h3 className="text-lg font-bold text-slate-900 mb-2">{bundle.name}</h3>
                                            <p className="text-sm text-slate-600 mb-4 line-clamp-3">{bundle.description}</p>
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                                    <DollarSign className="w-4 h-4 text-slate-400" />
                                                    Est. ${bundle.estimatedMonthlyCost || bundle.price || 0}/mo
                                                </div>
                                                {bundle.serviceCount && (
                                                    <span className="text-xs text-slate-500">{bundle.serviceCount} services</span>
                                                )}
                                            </div>
                                        </div>
                                    ))}

                                    {/* Custom Bundle Option */}
                                    <div
                                        onClick={() => handleBundleSelect({ id: 'custom', name: 'Custom Bundle', code: 'CUSTOM', colorTheme: 'slate', band: 'Flexible', price: 0, description: 'Build a fully customized care plan from scratch with all available services.', services: apiServiceTypes.length > 0 ? apiServiceTypes : services })}
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

                        {/* Step 2: Service Configuration */}
                        {step === 2 && (
                            <>
                                <div className="mb-6">
                                    <h1 className="text-xl font-bold text-slate-900 mb-2">Step 2: Customize Services</h1>
                                    <p className="text-sm text-slate-600">
                                        Services have been auto-configured based on the patient's TNP and clinical flags. Adjust frequency and duration as needed.
                                    </p>
                                </div>

                                <div className="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                                    {/* CLINICAL SECTION */}
                                    <AccordionHeader title="CLINICAL SERVICES" sectionKey="CLINICAL" />
                                    {expandedSection === 'CLINICAL' && (
                                        <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                            {services.filter(s => s.category && s.category.toUpperCase().includes('CLINICAL')).length > 0 ? (
                                                services.filter(s => s.category && s.category.toUpperCase().includes('CLINICAL')).map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))
                                            ) : (
                                                <div className="text-slate-500 text-sm">No clinical services available.</div>
                                            )}
                                        </div>
                                    )}

                                    <AccordionHeader title="PERSONAL SUPPORT & DAILY LIVING" sectionKey="PERSONAL_SUPPORT" />
                                    {expandedSection === 'PERSONAL_SUPPORT' && (
                                        <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                            {services.filter(s => s.category && s.category.toUpperCase().includes('PERSONAL')).length > 0 ? (
                                                services.filter(s => s.category && s.category.toUpperCase().includes('PERSONAL')).map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))
                                            ) : (
                                                <div className="text-slate-500 text-sm">No personal support services available.</div>
                                            )}
                                        </div>
                                    )}

                                    <AccordionHeader title="SAFETY, MONITORING & TECHNOLOGY" sectionKey="SAFETY_TECH" />
                                    {expandedSection === 'SAFETY_TECH' && (
                                        <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                            {services.filter(s => s.category && (s.category.toUpperCase().includes('SAFETY') || s.category.toUpperCase().includes('TECH'))).length > 0 ? (
                                                services.filter(s => s.category && (s.category.toUpperCase().includes('SAFETY') || s.category.toUpperCase().includes('TECH'))).map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))
                                            ) : (
                                                <div className="text-slate-500 text-sm">No safety/monitoring services available.</div>
                                            )}
                                        </div>
                                    )}

                                    <AccordionHeader title="LOGISTICS & ACCESS SERVICES" sectionKey="LOGISTICS" />
                                    {expandedSection === 'LOGISTICS' && (
                                        <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                                            {services.filter(s => s.category && s.category.toUpperCase().includes('LOGISTICS')).length > 0 ? (
                                                services.filter(s => s.category && s.category.toUpperCase().includes('LOGISTICS')).map(service => (
                                                    <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                                                ))
                                            ) : (
                                                <div className="text-slate-500 text-sm">No logistics services available.</div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </>
                        )}

                        {/* Step 3: Review & Publish */}
                        {step === 3 && (
                            <div className="space-y-6">
                                {publishSuccess ? (
                                    <div className="text-center py-12">
                                        <CheckCircle2 className="w-16 h-16 text-green-500 mx-auto mb-4" />
                                        <h2 className="text-2xl font-bold text-slate-900 mb-2">Care Plan Published!</h2>
                                        <p className="text-slate-600 mb-4">{transitionMessage}</p>
                                        <p className="text-sm text-slate-500">Redirecting to patient profile...</p>
                                    </div>
                                ) : (
                                    <>
                                        <div className="mb-6">
                                            <h1 className="text-xl font-bold text-slate-900 mb-2">Step 3: Review & Publish</h1>
                                            <p className="text-sm text-slate-600">
                                                Review the care plan summary below. Publishing will activate services and transition the patient from the queue to their active profile.
                                            </p>
                                        </div>

                                        {/* Transition Warning */}
                                        {patient?.is_in_queue && (
                                            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-start gap-3">
                                                <AlertCircle className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                                                <div>
                                                    <h3 className="font-medium text-blue-800">Queue Transition</h3>
                                                    <p className="text-sm text-blue-700 mt-1">
                                                        Publishing this care plan will transition the patient from the intake queue to their active patient profile. All configured services will be activated.
                                                    </p>
                                                </div>
                                            </div>
                                        )}

                                        {/* Summary */}
                                        <div className="bg-white border border-slate-200 rounded-lg p-6">
                                            <h3 className="font-bold text-slate-900 mb-4">Care Plan Summary</h3>

                                            <div className="grid grid-cols-2 gap-4 mb-6">
                                                <div className="p-4 bg-slate-50 rounded-lg">
                                                    <div className="text-sm text-slate-500">Selected Bundle</div>
                                                    <div className="font-medium text-slate-900">{selectedBundle?.name || 'Custom'}</div>
                                                </div>
                                                <div className="p-4 bg-slate-50 rounded-lg">
                                                    <div className="text-sm text-slate-500">Estimated Monthly Cost</div>
                                                    <div className="font-medium text-slate-900">${monthlyCost.toLocaleString()}</div>
                                                </div>
                                            </div>

                                            <h4 className="font-medium text-slate-900 mb-3">Active Services</h4>
                                            <div className="space-y-2">
                                                {services.filter(s => (s.currentFrequency || 0) > 0).map(service => (
                                                    <div key={service.id} className="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                                                        <div>
                                                            <span className="font-medium text-slate-800">{service.name}</span>
                                                            <span className="text-slate-500 text-sm ml-2">
                                                                {service.currentFrequency}x/week for {service.currentDuration} weeks
                                                            </span>
                                                        </div>
                                                        <span className="text-slate-600">
                                                            ${(service.costPerVisit * service.currentFrequency * 4).toLocaleString()}/mo
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>

                                            {services.filter(s => (s.currentFrequency || 0) > 0).length === 0 && (
                                                <p className="text-slate-500 text-sm italic">No services selected. Please go back and configure services.</p>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Right Column: Summary & Recommendation (Independent Scroll) */}
                    {step !== 3 && (
                        <div className="w-80 shrink-0 overflow-y-auto border-l border-slate-200 bg-white p-6">
                            <BundleSummary
                                services={services}
                                totalCost={totalCost}
                                isGeneratingAi={isGeneratingAi}
                                aiRecommendation={aiRecommendation}
                                onGenerateAi={generateRecommendation}
                            />
                        </div>
                    )}
                </div>
            </main>
        </div>
    );
};

export default CareBundleWizard;
