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
    Star,
    Plus
} from 'lucide-react';
import PatientSummaryCard from '../../components/care/PatientSummaryCard';
import ServiceCard from '../../components/care/ServiceCard';
import BundleSummary from '../../components/care/BundleSummary';
import ClinicalInsightsPanel from '../../components/care/ClinicalInsightsPanel';
import BundleExplanationModal from '../../components/care/BundleExplanationModal';
import useServiceTypes, { mapCategory } from '../../hooks/useServiceTypes';
import careBundleBuilderApi from '../../services/careBundleBuilderApi';
import useBundleEngine from '../../hooks/useBundleEngine';

/**
 * CareBundleWizard - RUG-driven care bundle builder
 *
 * Implements a Workday-style workflow where:
 * 1. Bundles are recommended based on patient's InterRAI HC RUG classification
 * 2. Services are auto-configured based on RUG-driven templates
 * 3. Publishing triggers transition from queue to active patient profile
 */
const CareBundleWizard = () => {
    const { patientId } = useParams();
    const navigate = useNavigate();
    const [step, setStep] = useState(1);
    const [patient, setPatient] = useState(null);
    const [loading, setLoading] = useState(true);
    const [publishing, setPublishing] = useState(false);
    const [carePlanId, setCarePlanId] = useState(null);
    const [existingPlan, setExistingPlan] = useState(null);
    const [isModifying, setIsModifying] = useState(false);

    // Fetch service types from API (SC-003)
    const { serviceTypes: apiServiceTypes, loading: servicesLoading, error: servicesError } = useServiceTypes();

    // AI Bundle Engine hook
    const bundleEngine = useBundleEngine();

    // Step 1 State - Bundle Selection
    const [bundles, setBundles] = useState([]);
    const [selectedBundle, setSelectedBundle] = useState(null);
    const [recommendedBundle, setRecommendedBundle] = useState(null);
    const [selectedAiScenario, setSelectedAiScenario] = useState(null);
    const [aiScenariosLoaded, setAiScenariosLoaded] = useState(false);

    // Step 2 & 3 State - Service Configuration
    const [services, setServices] = useState([]);
    const [expandedSection, setExpandedSection] = useState('CLINICAL');
    const [aiRecommendation, setAiRecommendation] = useState(null);
    const [isGeneratingAi, setIsGeneratingAi] = useState(false);
    const [globalDuration, setGlobalDuration] = useState(12);
    const [showAdditionalServices, setShowAdditionalServices] = useState(false);

    // v2.2: Explanation modal state
    const [showExplanationModal, setShowExplanationModal] = useState(false);
    const [explanationScenario, setExplanationScenario] = useState(null);
    const [explanationError, setExplanationError] = useState(null);

    // Initialize services from API when available
    // Initialize services from API types when available and wizard data is loaded
    useEffect(() => {
        if (!loading && apiServiceTypes.length > 0 && services.length === 0) {
            console.log('Initializing services with selected bundle:', selectedBundle?.name, 'isModifying:', isModifying);

            // If modifying existing plan, use the existing plan's services as the baseline
            if (isModifying && existingPlan?.services?.length > 0) {
                console.log('Loading existing care plan services for modification:', existingPlan.services);
                const servicesWithConfig = apiServiceTypes.map(s => {
                    // Find this service in the existing plan - try multiple matching strategies
                    const existingService = existingPlan.services.find(es =>
                        String(es.service_type_id) === String(s.id) ||
                        (es.code && s.code && es.code === s.code) ||
                        (es.name && s.name && es.name.toLowerCase() === s.name.toLowerCase())
                    );
                    const isInExistingPlan = !!existingService;

                    // Get frequency, defaulting to 1 for existing plan services
                    const freq = isInExistingPlan ? (existingService.frequency || 1) : 0;
                    const dur = isInExistingPlan ? (existingService.duration || 12) : 12;

                    return {
                        ...s,
                        is_core: isInExistingPlan,
                        currentFrequency: freq,
                        currentDuration: dur,
                        // Services in existing plan should always have defaultFrequency > 0 to show in Step 2
                        defaultFrequency: isInExistingPlan ? Math.max(freq, 1) : 0,
                        // Store original values for comparison display
                        originalFrequency: freq,
                        originalDuration: dur,
                        originalCost: isInExistingPlan ? (existingService.cost_per_visit || s.costPerVisit || 0) : 0,
                    };
                });
                setServices(servicesWithConfig);
            } else if (selectedBundle) {
                // Merge bundle services with all available services
                const servicesWithConfig = apiServiceTypes.map(s => {
                    const bundleService = selectedBundle.services?.find(b =>
                        b.id == s.id ||
                        (b.code && s.code && b.code === s.code) ||
                        (b.name && s.name && b.name === s.name)
                    );
                    const isCore = !!bundleService;

                    return {
                        ...s,
                        is_core: isCore,
                        currentFrequency: isCore ? (bundleService.pivot?.frequency || bundleService.frequency || bundleService.currentFrequency || 1) : 0,
                        currentDuration: isCore ? (bundleService.pivot?.duration || bundleService.duration || bundleService.currentDuration || 12) : 12,
                        defaultFrequency: isCore ? (bundleService.pivot?.frequency || bundleService.frequency || bundleService.currentFrequency || 1) : 0,
                        originalFrequency: 0,
                        originalDuration: 0,
                        originalCost: 0,
                    };
                });
                setServices(servicesWithConfig);
            } else {
                // Default initialization if no bundle selected
                setServices(apiServiceTypes.map(s => ({
                    ...s,
                    is_core: false,
                    currentFrequency: 0,
                    currentDuration: 12,
                    defaultFrequency: 0,
                    originalFrequency: 0,
                    originalDuration: 0,
                    originalCost: 0,
                })));
            }
        }
    }, [loading, apiServiceTypes, selectedBundle, services.length, isModifying, existingPlan]);



    // Step 3 State - Review & Publish
    const [publishSuccess, setPublishSuccess] = useState(false);
    const [transitionMessage, setTransitionMessage] = useState(null);

    useEffect(() => {
        fetchData();
    }, [patientId]);

    // Load AI scenarios on mount
    useEffect(() => {
        if (patientId && !aiScenariosLoaded && !bundleEngine.loading) {
            bundleEngine.generateScenarios(patientId).then(() => {
                setAiScenariosLoaded(true);
            }).catch(err => {
                console.error('Failed to load AI scenarios:', err);
                setAiScenariosLoaded(true); // Mark as loaded even on error to prevent retries
            });
        }
    }, [patientId, aiScenariosLoaded, bundleEngine.loading]);

    // Auto-select first/recommended scenario AFTER both apiServiceTypes AND scenarios are loaded
    useEffect(() => {
        if (aiScenariosLoaded && apiServiceTypes.length > 0 && bundleEngine.scenarios.length > 0 && !selectedAiScenario) {
            const recommended = bundleEngine.scenarios.find(s => s.meta?.is_recommended) || bundleEngine.scenarios[0];
            handleAiScenarioSelect(recommended, false); // false = don't auto-advance
        }
    }, [aiScenariosLoaded, apiServiceTypes, bundleEngine.scenarios, selectedAiScenario]);

    const fetchData = async () => {
        try {
            setLoading(true);

            // Fetch patient and existing care plans (RUG classification comes with patient)
            const [patientRes, carePlansRes] = await Promise.all([
                api.get(`/patients/${patientId}`).catch(err => {
                    console.error('Patient fetch failed', err);
                    return { data: { data: null } };
                }),
                api.get(`/v2/care-plans?patient_id=${patientId}`).catch(err => {
                    console.warn('Care plans fetch failed', err);
                    return { data: { data: [] } };
                })
            ]);

            if (patientRes.data.data) {
                setPatient(patientRes.data.data);
            } else {
                console.error("Critical: Patient data missing");
            }

            // Check for existing active care plan
            const existingPlans = carePlansRes.data?.data || carePlansRes.data || [];
            const activePlan = existingPlans.find(p => p.status === 'active' || p.status === 'approved');
            if (activePlan) {
                setExistingPlan(activePlan);
                setIsModifying(true);
                console.log('Found existing care plan to modify:', activePlan);
            }

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
                            // Don't set services here, let useEffect handle the merge with apiServiceTypes
                            // This ensures we have the full list of services (22) not just the bundle ones (7)
                        }
                    }
                } else if (configuredBundles.length > 0) {
                    setSelectedBundle(configuredBundles[0]);
                }
            } catch (bundleErr) {
                console.error('Bundle builder API failed, falling back to template API', bundleErr);
                // Fallback to basic bundle templates
                const bundlesRes = await api.get('/v2/bundle-templates').catch(() => ({ data: [] }));

                // Map bundles with RUG-based categories instead of legacy Band A/B/C
                const patientData = patientRes.data.data;
                const enrichedBundles = (bundlesRes.data || []).map(b => ({
                    ...b,
                    colorTheme: b.rug_category === 'Special Rehabilitation' ? 'amber' :
                                b.rug_category === 'Impaired Cognition' || b.rug_category === 'Behaviour Problems' ? 'purple' :
                                b.rug_category === 'Extensive Services' || b.rug_category === 'Special Care' ? 'rose' :
                                b.rug_category === 'Clinically Complex' ? 'green' : 'teal',
                    band: b.rug_category || b.category || 'RUG-Based',
                    price: b.price || b.estimatedMonthlyCost || 1200,
                    services: apiServiceTypes.length > 0 ? apiServiceTypes : services
                }));
                setBundles(enrichedBundles);

                // Auto-select based on patient's RUG classification
                if (patientData?.rug_group) {
                    const rugGroup = patientData.rug_group;
                    // Find bundle that matches the RUG group
                    const matchingBundle = enrichedBundles.find(b =>
                        b.code?.includes(rugGroup) || b.rug_groups?.includes(rugGroup)
                    );
                    if (matchingBundle) {
                        setSelectedBundle(matchingBundle);
                    } else {
                        setSelectedBundle(enrichedBundles[0]);
                    }
                } else {
                    setSelectedBundle(enrichedBundles[0]);
                }
            }

        } catch (error) {
            console.error('Failed to fetch wizard data', error);
        } finally {
            setLoading(false);
        }
    };

    // Handle AI scenario selection
    // autoAdvance: if false, won't move to step 2 (used for initial auto-selection)
    const handleAiScenarioSelect = (scenario, autoAdvance = true) => {
        setSelectedAiScenario(scenario);

        // ALWAYS reset all services first, then apply AI scenario services
        // This ensures we don't keep stale frequencies from previous selections
        const scenarioServices = scenario.services || [];
        
        const servicesWithConfig = apiServiceTypes.map(s => {
            // Find matching service in the AI scenario using multiple match strategies
            const scenarioService = scenarioServices.find(ss =>
                ss.service_module_id == s.id ||  // Use loose comparison for id
                (ss.service_code && s.code && ss.service_code === s.code) ||
                (ss.service_name && s.name && ss.service_name.toLowerCase().includes(s.name.toLowerCase().split(' ')[0]))
            );
            const isCore = !!scenarioService;

            // Get frequency - handle nested structure from API (frequency.count) or flat (frequencyCount)
            const freqCount = isCore ? (scenarioService.frequency?.count || scenarioService.frequencyCount || 1) : 0;
            const durationMins = isCore ? (scenarioService.duration?.minutes || scenarioService.durationMinutes || 60) : 0;
            const costPerVisit = isCore ? (scenarioService.cost?.per_visit || scenarioService.costPerVisit || s.costPerVisit || 0) : 0;

            return {
                ...s,
                is_core: isCore,
                currentFrequency: freqCount,
                currentDuration: 12, // Default duration in weeks
                defaultFrequency: freqCount, // Match currentFrequency so BundleSummary shows it
                originalFrequency: 0,
                originalDuration: 0,
                originalCost: costPerVisit,
                durationMinutes: durationMins,
                costPerVisit: costPerVisit,
            };
        });
        
        setServices(servicesWithConfig);

        // Create a synthetic bundle from the scenario for downstream compatibility
        const scenarioTitle = scenario.label?.title || scenario.axis?.primary?.label || 'AI Scenario';
        setSelectedBundle({
            id: scenario.scenario_id,
            name: scenarioTitle,
            description: scenario.label?.description || '',
            colorTheme: 'teal',
            band: scenario.axis?.primary?.label || 'AI-Generated',
            price: scenario.cost?.weekly_estimate || 0,
            services: scenario.services || [],
            isAiGenerated: true,
        });

        // Move to step 2 only if autoAdvance is true
        if (autoAdvance) {
            setStep(2);
        }
    };

    // When bundle selection changes, update services
    const handleBundleSelect = (bundle) => {
        setSelectedBundle(bundle);
        // Clear AI scenario selection when selecting a traditional bundle
        setSelectedAiScenario(null);

        // Merge bundle services with all available services
        // We need to map ALL available services, marking those in the bundle as isCore
        const servicesWithConfig = apiServiceTypes.map(s => {
            // Check if this service exists in the selected bundle
            // Use loose comparison for IDs, check code, and fallback to name matching
            const bundleService = bundle.services?.find(b =>
                b.id == s.id ||
                (b.code && s.code && b.code === s.code) ||
                (b.name && s.name && b.name === s.name)
            );
            const isCore = !!bundleService;

            return {
                ...s,
                is_core: isCore,
                // If it's in the bundle, use its frequency/duration, otherwise default to 0 (hidden)
                currentFrequency: isCore ? (bundleService.pivot?.frequency || bundleService.frequency || bundleService.currentFrequency || 1) : 0,
                currentDuration: isCore ? (bundleService.pivot?.duration || bundleService.duration || bundleService.currentDuration || 12) : 12,
                // Keep track of original bundle values for reset if needed
                defaultFrequency: isCore ? (bundleService.pivot?.frequency || bundleService.frequency || bundleService.currentFrequency || 1) : 0,
            };
        });

        setServices(servicesWithConfig);
        setGlobalDuration(12);
        setShowAdditionalServices(false);

        // If we are on step 1, move to step 2
        if (step === 1) {
            setStep(2);
        }
    };

    /**
     * v2.2: Handle requesting an AI explanation for a scenario
     */
    const handleExplainScenario = async (scenario, idx) => {
        setExplanationScenario(scenario);
        setShowExplanationModal(true);
        setExplanationError(null);

        try {
            await bundleEngine.getExplanation(patientId, idx, true);
        } catch (err) {
            setExplanationError(err.message || 'Failed to generate explanation');
        }
    };

    const handleUpdateService = (id, field, value) => {
        setServices(prev => prev.map(s =>
            s.id === id ? { ...s, [field]: value } : s
        ));
    };

    const handleGlobalDurationChange = (e) => {
        const newDuration = parseInt(e.target.value);
        setGlobalDuration(newDuration);
        setServices(prev => prev.map(s => ({
            ...s,
            currentDuration: newDuration
        })));
    };

    const totalCost = services.reduce((acc, curr) => {
        return acc + (curr.costPerVisit * curr.currentFrequency * curr.currentDuration);
    }, 0);

    const weeklyCost = services.reduce((acc, curr) => {
        return acc + (curr.costPerVisit * curr.currentFrequency);
    }, 0);

    const monthlyCost = careBundleBuilderApi.calculateMonthlyCost(services);

    const generateRecommendation = async () => {
        setIsGeneratingAi(true);
        // Simulate API call - could be connected to AI endpoint
        setTimeout(() => {
            setAiRecommendation("Recommendation: Based on the patient's InterRAI HC assessment and RUG classification, the bundle engine has auto-configured services. Consider reviewing Personal Support hours based on ADL hierarchy score.");
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
            setCarePlanId(response.data.id);
            setStep(4);
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

                // v2.3: Build scenario metadata object if an AI scenario was selected
                const scenarioMetadata = selectedAiScenario ? {
                    scenario_id: selectedAiScenario.scenario_id,
                    title: selectedAiScenario.label?.title || selectedAiScenario.axis?.primary?.label || 'AI Bundle',
                    axis: selectedAiScenario.axis?.primary?.value || selectedAiScenario.primary_axis,
                    source: selectedAiScenario.source || 'bundle_engine_v2.3',
                } : null;

                const buildResponse = await careBundleBuilderApi.buildPlan(
                    patientId,
                    selectedBundle.id,
                    formattedServices,
                    null, // notes
                    scenarioMetadata
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

    const ServiceList = ({ filterFn }) => {
        return (
            <div className="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                {/* CLINICAL SECTION */}
                <AccordionHeader title="CLINICAL SERVICES" sectionKey="CLINICAL" />
                {expandedSection === 'CLINICAL' && (
                    <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                        {services.filter(s => s.category && s.category.toUpperCase().includes('CLINICAL') && filterFn(s)).length > 0 ? (
                            services.filter(s => s.category && s.category.toUpperCase().includes('CLINICAL') && filterFn(s)).map(service => (
                                <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                            ))
                        ) : (
                            <div className="text-slate-500 text-sm">No clinical services available in this section.</div>
                        )}
                    </div>
                )}

                <AccordionHeader title="PERSONAL SUPPORT & DAILY LIVING" sectionKey="PERSONAL_SUPPORT" />
                {expandedSection === 'PERSONAL_SUPPORT' && (
                    <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                        {services.filter(s => s.category && s.category.toUpperCase().includes('PERSONAL') && filterFn(s)).length > 0 ? (
                            services.filter(s => s.category && s.category.toUpperCase().includes('PERSONAL') && filterFn(s)).map(service => (
                                <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                            ))
                        ) : (
                            <div className="text-slate-500 text-sm">No personal support services available in this section.</div>
                        )}
                    </div>
                )}

                <AccordionHeader title="SAFETY, MONITORING & TECHNOLOGY" sectionKey="SAFETY_TECH" />
                {expandedSection === 'SAFETY_TECH' && (
                    <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                        {services.filter(s => s.category && (s.category.toUpperCase().includes('SAFETY') || s.category.toUpperCase().includes('TECH')) && filterFn(s)).length > 0 ? (
                            services.filter(s => s.category && (s.category.toUpperCase().includes('SAFETY') || s.category.toUpperCase().includes('TECH')) && filterFn(s)).map(service => (
                                <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                            ))
                        ) : (
                            <div className="text-slate-500 text-sm">No safety/monitoring services available in this section.</div>
                        )}
                    </div>
                )}

                <AccordionHeader title="LOGISTICS & ACCESS SERVICES" sectionKey="LOGISTICS" />
                {expandedSection === 'LOGISTICS' && (
                    <div className="p-4 bg-white space-y-4 border-b border-slate-200">
                        {services.filter(s => s.category && s.category.toUpperCase().includes('LOGISTICS') && filterFn(s)).length > 0 ? (
                            services.filter(s => s.category && s.category.toUpperCase().includes('LOGISTICS') && filterFn(s)).map(service => (
                                <ServiceCard key={service.id} service={service} onUpdate={handleUpdateService} />
                            ))
                        ) : (
                            <div className="text-slate-500 text-sm">No logistics services available in this section.</div>
                        )}
                    </div>
                )}
            </div>
        );
    };

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
        <div className="flex flex-col h-screen bg-slate-50">
            {/* Page Header */}
            <div className="bg-white border-b border-slate-200 px-6 py-3 shrink-0">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-lg font-bold text-slate-900">
                            {isModifying ? 'Modify Care Plan' : 'Care Delivery Plan'}
                        </h1>
                        <span className="text-slate-300">|</span>
                        <span className="text-sm text-slate-500">Patient ID: {patient?.id || '---'}</span>
                        {patient?.is_in_queue && (
                            <span className="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs font-medium">
                                In Queue
                            </span>
                        )}
                        {isModifying && (
                            <span className="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                                Modifying
                            </span>
                        )}
                    </div>
                    <div className="flex gap-2">
                        {step === 1 && (
                            <>
                                <button
                                    onClick={() => navigate(`/patients/${patient?.id}`)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" />
                                    Back to Patient
                                </button>
                                {isModifying && (
                                    <button
                                        onClick={() => setStep(2)}
                                        className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5"
                                    >
                                        Skip to Customize Current <ArrowRight className="w-3.5 h-3.5" />
                                    </button>
                                )}
                                <button
                                    onClick={() => setStep(2)}
                                    disabled={!selectedBundle && !isModifying}
                                    className="px-3 py-1.5 bg-blue-700 text-white rounded-md text-sm font-medium hover:bg-blue-800 shadow-sm flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    {isModifying ? 'Switch Bundle & Customize' : 'Next: Customize Bundle'} <ArrowRight className="w-3.5 h-3.5" />
                                </button>
                            </>
                        )}
                        {step === 2 && (
                            <>
                                <button
                                    onClick={() => navigate(`/patients/${patient?.id}`)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" /> Back to Patient
                                </button>
                                <button
                                    onClick={() => setStep(1)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" /> Back to Bundles
                                </button>
                                <button
                                    onClick={() => setStep(3)}
                                    className="px-3 py-1.5 bg-blue-700 text-white rounded-md text-sm font-medium hover:bg-blue-800 shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    Next: Add Services <ArrowRight className="w-3.5 h-3.5" />
                                </button>
                            </>
                        )}
                        {step === 3 && (
                            <>
                                <button
                                    onClick={() => navigate(`/patients/${patient?.id}`)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" /> Back to Patient
                                </button>
                                <button
                                    onClick={() => setStep(2)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" /> Back to Customize
                                </button>
                                <button
                                    onClick={handleProceedToReview}
                                    disabled={publishing}
                                    className="px-3 py-1.5 bg-blue-700 text-white rounded-md text-sm font-medium hover:bg-blue-800 shadow-sm flex items-center gap-1.5 disabled:opacity-50 transition-colors"
                                >
                                    {publishing ? (
                                        <>
                                            <Loader2 className="w-3.5 h-3.5 animate-spin" /> Processing...
                                        </>
                                    ) : (
                                        <>
                                            Next: Review & Publish <ArrowRight className="w-3.5 h-3.5" />
                                        </>
                                    )}
                                </button>
                            </>
                        )}
                        {step === 4 && !publishSuccess && (
                            <>
                                <button
                                    onClick={() => navigate(`/patients/${patient?.id}`)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" /> Back to Patient
                                </button>
                                <button
                                    onClick={() => setStep(3)}
                                    className="px-3 py-1.5 border border-slate-300 rounded-md text-sm text-slate-700 font-medium hover:bg-slate-50 bg-white shadow-sm flex items-center gap-1.5 transition-colors"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" /> Back to Services
                                </button>
                                <button
                                    onClick={handlePublish}
                                    disabled={publishing}
                                    className="px-3 py-1.5 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 shadow-sm flex items-center gap-1.5 disabled:opacity-50 transition-colors"
                                >
                                    {publishing ? (
                                        <>
                                            <Loader2 className="w-3.5 h-3.5 animate-spin" /> Publishing...
                                        </>
                                    ) : (
                                        <>
                                            <Check className="w-3.5 h-3.5" /> Publish & Activate
                                        </>
                                    )}
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Main Content Area */}
            <div className="flex flex-1 overflow-hidden">

                    {/* Left Column: Patient Summary (Independent Scroll) */}
                    <div className="flex-[3] min-w-0 overflow-y-auto border-r border-slate-200 bg-slate-50 p-4">
                        <PatientSummaryCard patient={patient} />

                        {/* v2.2: Clinical Insights Panel - Algorithm Scores & CAPs */}
                        {(bundleEngine.algorithmScores || (Array.isArray(bundleEngine.triggeredCAPs) && bundleEngine.triggeredCAPs.length > 0)) && (
                            <ClinicalInsightsPanel
                                algorithmScores={bundleEngine.algorithmScores}
                                triggeredCAPs={bundleEngine.triggeredCAPs}
                                profileSummary={bundleEngine.profileSummary}
                            />
                        )}

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
                    <div className="flex-[6.2] min-w-0 overflow-y-auto p-4 bg-slate-50">
                        <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-6 overflow-hidden">
                        {/* Stepper Indicator - contained within card */}
                        <div className="mb-8 w-full overflow-hidden">
                            <div className="flex justify-between items-start w-full">
                                {/* Step 1 */}
                                <div className={`flex flex-col items-center flex-1 min-w-0 ${step >= 1 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 transition-colors shrink-0 ${step >= 1 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'}`}>
                                        {step > 1 ? <Check className="w-5 h-5" /> : '1'}
                                    </div>
                                    <span className="text-sm font-medium text-center">Select Bundle</span>
                                </div>

                                {/* Step 2 */}
                                <div className={`flex flex-col items-center flex-1 min-w-0 ${step >= 2 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 transition-colors shrink-0 ${step >= 2 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'}`}>
                                        {step > 2 ? <Check className="w-5 h-5" /> : '2'}
                                    </div>
                                    <span className="text-sm font-medium text-center">Customize</span>
                                </div>

                                {/* Step 3 */}
                                <div className={`flex flex-col items-center flex-1 min-w-0 ${step >= 3 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 transition-colors shrink-0 ${step >= 3 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'}`}>
                                        {step > 3 ? <Check className="w-5 h-5" /> : '3'}
                                    </div>
                                    <span className="text-sm font-medium text-center">Add Services</span>
                                </div>

                                {/* Step 4 */}
                                <div className={`flex flex-col items-center flex-1 min-w-0 ${step >= 4 ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center mb-2 transition-colors shrink-0 ${step >= 4 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'}`}>
                                        4
                                    </div>
                                    <span className="text-sm font-medium text-center">Review</span>
                                </div>
                            </div>
                        </div>

                        {/* Step 1: Bundle Selection */}
                        {step === 1 && (
                            <div className="space-y-6">
                                {/* Current Plan Info (when modifying) */}
                                {isModifying && existingPlan && (
                                    <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div className="flex items-start gap-3">
                                            <AlertCircle className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                                            <div className="flex-1">
                                                <h3 className="font-medium text-blue-800">Modifying Existing Care Plan</h3>
                                                <p className="text-sm text-blue-700 mt-1">
                                                    Current bundle: <strong>{existingPlan.bundle || 'Current Plan'}</strong> with {existingPlan.services?.length || 0} services
                                                    (${existingPlan.total_cost?.toLocaleString() || 0}/week)
                                                </p>
                                                <p className="text-sm text-blue-600 mt-2">
                                                    Select a new bundle below or proceed to customize the current services.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="mb-6">
                                    <h1 className="text-xl font-bold text-slate-900 mb-2">
                                        {isModifying ? 'Step 1: Change Bundle (Optional)' : 'Step 1: Select Care Bundle'}
                                    </h1>
                                    <p className="text-sm text-slate-600">
                                        {isModifying
                                            ? 'You can switch to a different bundle or skip to customize the existing services.'
                                            : 'Based on the patient\'s assessment, AI-generated care scenarios have been tailored to their needs.'
                                        }
                                    </p>
                                </div>

                                {/* AI Scenarios Section */}
                                {bundleEngine.loading && (
                                    <div className="p-8 text-center">
                                        <div className="inline-flex items-center gap-3">
                                            <Loader2 className="w-6 h-6 text-teal-600 animate-spin" />
                                            <span className="text-slate-600">Generating care scenarios...</span>
                                        </div>
                                    </div>
                                )}

                                {bundleEngine.error && (
                                    <div className="p-6 bg-amber-50 border border-amber-200 rounded-xl mb-6">
                                        <div className="flex items-start gap-3">
                                            <AlertCircle className="w-5 h-5 text-amber-500 mt-0.5" />
                                            <div>
                                                <h3 className="font-semibold text-amber-800">Unable to Generate AI Scenarios</h3>
                                                <p className="text-sm text-amber-600 mt-1">{bundleEngine.error}</p>
                                                <p className="text-sm text-amber-600 mt-2">You can still select a bundle below or build a custom plan.</p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Profile Summary */}
                                {bundleEngine.profileSummary && (
                                    <div className="p-4 bg-slate-100 rounded-xl mb-6">
                                        <div className="flex items-center gap-6 flex-wrap text-sm">
                                            <div className="flex items-center gap-2">
                                                <span className="text-slate-500">Classification:</span>
                                                <span className="font-semibold text-slate-700">
                                                    {bundleEngine.profileSummary.primary_classification || 'N/A'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-slate-500">Episode Type:</span>
                                                <span className="font-semibold text-slate-700 capitalize">
                                                    {bundleEngine.profileSummary.episode_type?.replace('_', ' ') || 'N/A'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-slate-500">Rehab Potential:</span>
                                                <span className={`font-semibold ${
                                                    bundleEngine.profileSummary.rehab_potential
                                                        ? 'text-emerald-600'
                                                        : 'text-slate-700'
                                                }`}>
                                                    {bundleEngine.profileSummary.rehab_potential ? 'Yes' : 'No'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* AI Scenarios Grid + Custom Bundle */}
                                {!bundleEngine.loading && (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        {/* AI Generated Scenarios */}
                                        {bundleEngine.scenarios.map((scenario, idx) => {
                                            const isRecommended = idx === 0 || scenario.meta?.is_recommended;
                                            const weeklyCost = scenario.cost?.weekly_estimate || 0;
                                            const weeklyHours = scenario.operations?.weekly_hours || 0;
                                            const serviceCount = scenario.services?.length || 0;
                                            const disciplineCount = scenario.operations?.discipline_count || 0;
                                            const description = scenario.label?.description || scenario.axis?.primary?.description || '';
                                            const icon = scenario.label?.icon || scenario.axis?.primary?.emoji || '📋';
                                            const axisLabel = scenario.axis?.primary?.label || 'AI Scenario';

                                            return (
                                                <div
                                                    key={scenario.scenario_id || idx}
                                                    onClick={() => handleAiScenarioSelect(scenario, false)}
                                                    className={`cursor-pointer rounded-xl border-2 p-3 transition-all hover:shadow-md relative flex flex-col ${
                                                        selectedAiScenario?.scenario_id === scenario.scenario_id
                                                            ? 'border-teal-600 bg-teal-50 shadow-md ring-2 ring-teal-200'
                                                            : 'border-slate-200 bg-white hover:border-teal-300'
                                                    }`}
                                                >
                                                    {/* Recommended Badge */}
                                                    {isRecommended && (
                                                        <div className="absolute -top-2 left-3 px-2 py-0.5 bg-teal-600 text-white text-[10px] font-semibold rounded-full">
                                                            Recommended
                                                        </div>
                                                    )}

                                                    {/* Header - Icon + Title Badge + Check */}
                                                    <div className="flex items-start justify-between mb-2 mt-1 gap-2">
                                                        <div className="flex items-start gap-1.5 min-w-0 flex-1">
                                                            <span className="text-xl shrink-0 leading-none">{icon}</span>
                                                            <span 
                                                                className="px-2 py-0.5 rounded text-[11px] font-bold bg-teal-100 text-teal-700 leading-relaxed"
                                                                style={{boxDecorationBreak: 'clone', WebkitBoxDecorationBreak: 'clone'}}
                                                            >
                                                                {axisLabel}
                                                            </span>
                                                        </div>
                                                        {selectedAiScenario?.scenario_id === scenario.scenario_id && (
                                                            <Check className="w-4 h-4 text-teal-600 shrink-0 mt-0.5 ml-1" />
                                                        )}
                                                    </div>

                                                    {/* Description - full text, smaller font */}
                                                    <p className="text-xs text-slate-600 mb-3 flex-grow leading-relaxed">
                                                        {description}
                                                    </p>

                                                    {/* Cost & Hours - auto-sized inline badges */}
                                                    <div className="flex flex-col items-center gap-1 mb-2">
                                                        <span className={`inline-flex items-center justify-center px-2 py-1 rounded-md text-xs font-semibold ${
                                                            scenario.cost?.status === 'within_cap' ? 'bg-emerald-100 text-emerald-700' :
                                                            scenario.cost?.status === 'near_cap' ? 'bg-amber-100 text-amber-700' :
                                                            'bg-slate-100 text-slate-700'
                                                        }`}>
                                                            ${Math.round(weeklyCost).toLocaleString()}/wk
                                                        </span>
                                                        <span className="inline-flex items-center justify-center px-2 py-1 rounded-md bg-slate-100 text-slate-600 text-xs font-semibold">
                                                            {weeklyHours.toFixed(1)}h/wk
                                                        </span>
                                                    </div>

                                                    {/* Footer stats - always at bottom */}
                                                    <div className="flex flex-wrap items-center justify-between mt-auto pt-2 border-t border-slate-100 gap-1">
                                                        <div className="flex flex-col text-[10px] text-slate-400 min-w-0">
                                                            <span>{serviceCount} services</span>
                                                            <span>{disciplineCount} disciplines</span>
                                                        </div>
                                                        {/* v2.2: Explain button */}
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                handleExplainScenario(scenario, idx);
                                                            }}
                                                            className="px-2 py-1 text-[10px] font-medium text-teal-600 hover:text-teal-700 hover:bg-teal-50 rounded transition-colors"
                                                        >
                                                            Explain
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })}

                                        {/* Custom Bundle Option */}
                                        <div
                                            onClick={() => handleBundleSelect({ id: 'custom', name: 'Custom Bundle', code: 'CUSTOM', colorTheme: 'slate', band: 'Flexible', price: 0, description: 'Build a fully customized care plan from scratch with all available services.', services: apiServiceTypes.length > 0 ? apiServiceTypes : services })}
                                            className={`cursor-pointer rounded-xl border-2 border-dashed p-3 transition-all hover:shadow-md flex flex-col ${selectedBundle?.id === 'custom'
                                                ? 'border-slate-600 bg-slate-100 shadow-md'
                                                : 'border-slate-300 bg-slate-50 hover:border-slate-400 hover:bg-white'
                                                }`}
                                        >
                                            {/* Header */}
                                            <div className="flex items-start justify-between mb-2 gap-1">
                                                <div className="flex items-start gap-1.5 min-w-0 flex-1">
                                                    <span className="text-xl shrink-0 leading-none">🛠️</span>
                                                    <span 
                                                        className="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 text-slate-600 leading-relaxed"
                                                        style={{boxDecorationBreak: 'clone', WebkitBoxDecorationBreak: 'clone'}}
                                                    >
                                                        Custom
                                                    </span>
                                                </div>
                                                {selectedBundle?.id === 'custom' && <Check className="w-4 h-4 text-slate-600 shrink-0 mt-0.5" />}
                                            </div>
                                            
                                            {/* Description */}
                                            <p className="text-xs text-slate-600 mb-3 flex-grow leading-relaxed">
                                                Build a fully customized care plan from scratch with complete control over all services and frequencies.
                                            </p>
                                            
                                            {/* Footer - matches other cards */}
                                            <div className="flex items-center gap-3 text-[10px] text-slate-400 mt-auto pt-2 border-t border-slate-100">
                                                <span>Variable Cost</span>
                                                <span>Full Flexibility</span>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Step 2: Customize Bundle */}
                        {step === 2 && (
                            <div className="space-y-6">
                                <div>
                                    <h1 className="text-xl font-bold text-slate-900 mb-2">
                                        Step 2: {isModifying ? 'Modify Services' : `Customize ${selectedBundle?.name}`}
                                    </h1>
                                    <p className="text-sm text-slate-600">
                                        {isModifying
                                            ? 'Adjust the frequency and duration of services. Changes from the current plan are tracked and will be shown in the review step.'
                                            : 'Services have been auto-configured based on the patient\'s InterRAI HC assessment and RUG classification. Adjust frequency and duration as needed.'
                                        }
                                    </p>
                                </div>

                                {/* Global Duration Slider */}
                                <div className="p-6 bg-blue-50 rounded-lg border border-blue-100">
                                    <div className="flex items-start gap-4 mb-4">
                                        <div className="p-2 bg-blue-100 rounded-full text-blue-600">
                                            <AlertCircle className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-slate-900 text-sm">Plan Duration</h3>
                                            <p className="text-xs text-slate-600 mt-1">
                                                Set the default duration for the entire care plan. You can still adjust individual services below if they require a different timeline.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <span className="text-sm font-medium text-slate-700 w-20">Global Duration:</span>
                                        <input
                                            type="range"
                                            min="1"
                                            max="52"
                                            value={globalDuration}
                                            onChange={handleGlobalDurationChange}
                                            className="flex-1 h-2 bg-blue-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                        />
                                        <span className="text-sm font-bold text-blue-700 w-16 text-right">{globalDuration} weeks</span>
                                    </div>
                                </div>

                                {/* Service List - Only Core Services */}
                                <ServiceList filterFn={(s) => s.defaultFrequency > 0} />
                            </div>
                        )}

                        {/* Step 3: Add Additional Services */}
                        {step === 3 && (
                            <>
                                <div className="mb-6">
                                    <h1 className="text-xl font-bold text-slate-900 mb-2">Step 3: Add Additional Services</h1>
                                    <p className="text-sm text-slate-600">
                                        Add any other services that were not included in the pre-built bundle.
                                    </p>
                                </div>

                                {!showAdditionalServices ? (
                                    <div className="flex justify-center py-12 border-2 border-dashed border-slate-200 rounded-lg bg-slate-50">
                                        <button
                                            onClick={() => setShowAdditionalServices(true)}
                                            className="px-6 py-3 bg-white border border-blue-200 text-blue-700 font-bold rounded-lg shadow-sm hover:bg-blue-50 hover:border-blue-300 transition-all flex items-center gap-2"
                                        >
                                            <Plus className="w-5 h-5" />
                                            Add Services To Bundle
                                        </button>
                                    </div>
                                ) : (
                                    /* Show only Non-Core services */
                                    <ServiceList filterFn={(s) => s.defaultFrequency === 0} />
                                )}
                            </>
                        )}

                        {/* Step 4: Review & Publish */}
                        {step === 4 && (
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
                                            <h1 className="text-xl font-bold text-slate-900 mb-2">Step 4: Review & Publish</h1>
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
                                            <h3 className="font-bold text-slate-900 mb-4">
                                                {isModifying ? 'Care Plan Changes' : 'Care Plan Summary'}
                                            </h3>

                                            {/* Current Plan Summary (only when modifying) */}
                                            {isModifying && existingPlan && (
                                                <div className="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-lg">
                                                    <h4 className="font-medium text-slate-700 mb-3 flex items-center gap-2">
                                                        <span className="w-3 h-3 bg-slate-400 rounded-full"></span>
                                                        Current Plan: {existingPlan.bundle || 'Current Bundle'}
                                                    </h4>
                                                    <div className="space-y-1 text-sm text-slate-600">
                                                        {existingPlan.services?.map((s, idx) => (
                                                            <div key={idx} className="flex justify-between">
                                                                <span>{s.name}</span>
                                                                <span>{s.frequency}x/week • ${(s.cost_per_visit * s.frequency * 4).toLocaleString()}/mo</span>
                                                            </div>
                                                        ))}
                                                        <div className="pt-2 mt-2 border-t border-slate-300 font-medium flex justify-between">
                                                            <span>Current Weekly Cost</span>
                                                            <span>${existingPlan.total_cost?.toLocaleString() || 0}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            {/* New/Modified Plan Summary */}
                                            <div className={isModifying ? 'p-4 bg-blue-50 border border-blue-200 rounded-lg' : ''}>
                                                {isModifying && (
                                                    <h4 className="font-medium text-blue-700 mb-3 flex items-center gap-2">
                                                        <span className="w-3 h-3 bg-blue-500 rounded-full"></span>
                                                        Modified Plan: {selectedBundle?.name || 'Custom'}
                                                    </h4>
                                                )}

                                                <div className="grid grid-cols-3 gap-4 mb-6">
                                                    <div className="p-4 bg-white rounded-lg border border-slate-200">
                                                        <div className="text-sm text-slate-500">Selected Bundle</div>
                                                        <div className="font-medium text-slate-900">
                                                            {selectedBundle?.name || 'Custom'}
                                                            <span className="text-slate-500 font-normal text-xs ml-1">
                                                                ({services.some(s => s.defaultFrequency === 0 && s.currentFrequency > 0) ? 'Customized' : 'Base'})
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="p-4 bg-white rounded-lg border border-slate-200">
                                                        <div className="text-sm text-slate-500">Est. Weekly Cost</div>
                                                        <div className="font-medium text-slate-900">
                                                            ${weeklyCost.toLocaleString()}
                                                            {isModifying && existingPlan?.total_cost && weeklyCost !== existingPlan.total_cost && (
                                                                <span className={`ml-2 text-xs ${weeklyCost > existingPlan.total_cost ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                                    ({weeklyCost > existingPlan.total_cost ? '+' : ''}{(weeklyCost - existingPlan.total_cost).toLocaleString()})
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="p-4 bg-white rounded-lg border border-slate-200">
                                                        <div className="text-sm text-slate-500">Est. Monthly Cost</div>
                                                        <div className="font-medium text-slate-900">${monthlyCost.toLocaleString()}</div>
                                                    </div>
                                                </div>

                                                <h4 className="font-medium text-slate-900 mb-3">
                                                    {isModifying ? 'Modified Services' : 'Active Services'}
                                                </h4>
                                                <div className="space-y-2">
                                                    {services.filter(s => (s.currentFrequency || 0) > 0).map(service => {
                                                        const hasChanged = isModifying && (
                                                            service.currentFrequency !== service.originalFrequency ||
                                                            service.currentDuration !== service.originalDuration
                                                        );
                                                        const isNew = isModifying && service.originalFrequency === 0 && service.currentFrequency > 0;

                                                        return (
                                                            <div key={service.id} className={`flex items-center justify-between py-2 border-b border-slate-100 last:border-0 ${hasChanged ? 'bg-blue-50 -mx-2 px-2 rounded' : ''}`}>
                                                                <div>
                                                                    <span className="font-medium text-slate-800">{service.name}</span>
                                                                    {isNew && (
                                                                        <span className="ml-2 px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded-full">New</span>
                                                                    )}
                                                                    {hasChanged && !isNew && (
                                                                        <span className="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full">Changed</span>
                                                                    )}
                                                                    <span className="text-slate-500 text-sm ml-2">
                                                                        {service.currentFrequency}x/week for {service.currentDuration} weeks
                                                                        {hasChanged && !isNew && (
                                                                            <span className="text-slate-400 ml-1">
                                                                                (was: {service.originalFrequency}x/week)
                                                                            </span>
                                                                        )}
                                                                    </span>
                                                                </div>
                                                                <span className="text-slate-600">
                                                                    ${(service.costPerVisit * service.currentFrequency * 4).toLocaleString()}/mo
                                                                </span>
                                                            </div>
                                                        );
                                                    })}
                                                </div>

                                                {/* Show removed services when modifying */}
                                                {isModifying && services.filter(s => s.originalFrequency > 0 && s.currentFrequency === 0).length > 0 && (
                                                    <>
                                                        <h4 className="font-medium text-rose-700 mb-3 mt-4">Removed Services</h4>
                                                        <div className="space-y-2">
                                                            {services.filter(s => s.originalFrequency > 0 && s.currentFrequency === 0).map(service => (
                                                                <div key={service.id} className="flex items-center justify-between py-2 border-b border-slate-100 last:border-0 bg-rose-50 -mx-2 px-2 rounded">
                                                                    <div>
                                                                        <span className="font-medium text-slate-800 line-through">{service.name}</span>
                                                                        <span className="ml-2 px-2 py-0.5 bg-rose-100 text-rose-700 text-xs rounded-full">Removed</span>
                                                                        <span className="text-slate-400 text-sm ml-2">
                                                                            (was: {service.originalFrequency}x/week)
                                                                        </span>
                                                                    </div>
                                                                    <span className="text-slate-400 line-through">
                                                                        ${(service.costPerVisit * service.originalFrequency * 4).toLocaleString()}/mo
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </>
                                                )}

                                                {services.filter(s => (s.currentFrequency || 0) > 0).length === 0 && (
                                                    <p className="text-slate-500 text-sm italic">No services selected. Please go back and configure services.</p>
                                                )}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        )}
                        </div>
                    </div>

                    {/* Right Column: Summary & Recommendation (Independent Scroll) */}
                    {step !== 4 && (
                        <div className="flex-[3] min-w-0 overflow-y-auto border-l border-slate-200 bg-slate-50 p-4">
                            <BundleSummary
                                services={services}
                                totalCost={weeklyCost}
                                isGeneratingAi={isGeneratingAi}
                                aiRecommendation={aiRecommendation}
                                onGenerateAi={generateRecommendation}
                                bundleName={
                                    selectedAiScenario
                                        ? `${selectedAiScenario.label?.title || selectedAiScenario.axis?.primary?.label || 'AI Scenario'}`
                                        : selectedBundle?.name
                                            ? `${selectedBundle.name} ${services.some(s => s.defaultFrequency === 0 && s.currentFrequency > 0) ? '(Customized)' : '(Base)'}`
                                            : 'Select a Bundle'
                                }
                            />
                        </div>
                    )}
            </div>

            {/* v2.2: Bundle Explanation Modal */}
            <BundleExplanationModal
                isOpen={showExplanationModal}
                onClose={() => {
                    setShowExplanationModal(false);
                    bundleEngine.clearExplanation();
                }}
                explanation={bundleEngine.explanation}
                loading={bundleEngine.explanationLoading}
                scenarioTitle={explanationScenario?.label?.title || explanationScenario?.axis?.primary?.label || 'Scenario'}
                error={explanationError}
            />
        </div>
    );
};

export default CareBundleWizard;
