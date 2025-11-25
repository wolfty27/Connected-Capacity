import React, { useState, useEffect, useCallback } from 'react';
import api from '../../services/api';
import { useParams, useNavigate } from 'react-router-dom';
import Wizard from '../../components/UI/Wizard';
import Button from '../../components/UI/Button';
import Section from '../../components/UI/Section';

/**
 * InterraiCompletionWizard - Multi-step wizard for SPO to complete InterRAI HC assessments
 *
 * Per IR-006: Used when a patient requires a new InterRAI assessment because:
 * - No prior assessment exists
 * - Existing assessment is stale (>90 days old)
 *
 * Steps:
 * 1. Patient Confirmation - Verify patient and review history
 * 2. Assessment Info - Type, date, assessor details
 * 3. Functional Scores - ADL, IADL, MAPLe, CPS
 * 4. Clinical Indicators - Pain, CHESS, falls, wandering
 * 5. CAPs & Diagnoses - Triggered CAPs and ICD-10 codes
 * 6. Review & Submit - Final review before submission
 */
const InterraiCompletionWizard = () => {
    const { patientId } = useParams();
    const navigate = useNavigate();

    const [currentStep, setCurrentStep] = useState(0);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    // Patient and form schema
    const [patient, setPatient] = useState(null);
    const [formSchema, setFormSchema] = useState(null);
    const [assessmentHistory, setAssessmentHistory] = useState([]);

    // Form data
    const [formData, setFormData] = useState({
        // Step 2: Assessment Info
        assessment_type: 'hc',
        assessment_date: new Date().toISOString().split('T')[0],
        assessor_role: 'Care Coordinator',

        // Step 3: Functional Scores
        maple_score: '',
        adl_hierarchy: '',
        iadl_difficulty: '',
        cognitive_performance_scale: '',

        // Step 4: Clinical Indicators
        depression_rating_scale: '',
        pain_scale: '',
        chess_score: '',
        method_for_locomotion: 'Independent',
        falls_in_last_90_days: false,
        wandering_flag: false,

        // Step 5: CAPs & Diagnoses
        caps_triggered: [],
        primary_diagnosis_icd10: '',
        secondary_diagnoses: [],
    });

    const steps = [
        'Patient',
        'Assessment',
        'Functional',
        'Clinical',
        'CAPs',
        'Review',
    ];

    // Load patient data and form schema
    useEffect(() => {
        const fetchData = async () => {
            try {
                setLoading(true);
                const [statusRes, historyRes, schemaRes] = await Promise.all([
                    api.get(`/api/v2/interrai/patients/${patientId}/status`),
                    api.get(`/api/v2/interrai/patients/${patientId}/assessments`),
                    api.get('/api/v2/interrai/form-schema'),
                ]);

                setPatient(statusRes.data.data);
                setAssessmentHistory(historyRes.data.data || []);
                setFormSchema(schemaRes.data.data);
            } catch (err) {
                console.error('Failed to load InterRAI data:', err);
                setError('Failed to load patient data. Please try again.');
            } finally {
                setLoading(false);
            }
        };

        if (patientId) {
            fetchData();
        }
    }, [patientId]);

    const updateFormData = useCallback((field, value) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    }, []);

    const toggleCap = useCallback((cap) => {
        setFormData((prev) => ({
            ...prev,
            caps_triggered: prev.caps_triggered.includes(cap)
                ? prev.caps_triggered.filter((c) => c !== cap)
                : [...prev.caps_triggered, cap],
        }));
    }, []);

    const handleNext = () => {
        if (currentStep < steps.length - 1) {
            setCurrentStep((s) => s + 1);
        } else {
            handleSubmit();
        }
    };

    const handleBack = () => {
        if (currentStep > 0) {
            setCurrentStep((s) => s - 1);
        }
    };

    const handleSubmit = async () => {
        try {
            setSubmitting(true);
            setError(null);

            const response = await api.post(
                `/api/v2/interrai/patients/${patientId}/assessments`,
                formData
            );

            // Navigate to patient detail or success page
            navigate(`/patients/${patientId}`, {
                state: {
                    message: 'InterRAI assessment completed successfully',
                    assessmentId: response.data.data?.id,
                },
            });
        } catch (err) {
            console.error('Failed to submit InterRAI assessment:', err);
            setError(err.response?.data?.message || 'Failed to submit assessment. Please try again.');
            setSubmitting(false);
        }
    };

    const isStepValid = () => {
        switch (currentStep) {
            case 0: // Patient confirmation
                return patient !== null;
            case 1: // Assessment info
                return formData.assessment_type && formData.assessment_date;
            case 2: // Functional scores
                return (
                    formData.maple_score &&
                    formData.adl_hierarchy !== '' &&
                    formData.cognitive_performance_scale !== ''
                );
            case 3: // Clinical indicators
                return true; // Optional fields
            case 4: // CAPs & diagnoses
                return true; // Optional fields
            case 5: // Review
                return true;
            default:
                return false;
        }
    };

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-teal-600 mx-auto"></div>
                    <p className="mt-4 text-slate-600">Loading patient data...</p>
                </div>
            </div>
        );
    }

    if (!patient) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="text-center max-w-md">
                    <div className="text-rose-500 text-5xl mb-4">!</div>
                    <h2 className="text-xl font-bold text-slate-900 mb-2">Patient Not Found</h2>
                    <p className="text-slate-600 mb-4">Unable to load patient data for the InterRAI assessment.</p>
                    <Button onClick={() => navigate(-1)}>Go Back</Button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 py-8 px-4">
            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="mb-8 text-center">
                    <h1 className="text-2xl font-bold text-slate-900">InterRAI HC Assessment</h1>
                    <p className="text-slate-600">Complete the InterRAI Home Care assessment for {patient.patient_name}</p>
                </div>

                {error && (
                    <div className="mb-6 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-lg">
                        {error}
                    </div>
                )}

                <Wizard
                    steps={steps}
                    currentStep={currentStep}
                    onNext={handleNext}
                    onBack={handleBack}
                    isNextDisabled={!isStepValid()}
                    isSubmitting={submitting}
                >
                    {/* Step 0: Patient Confirmation */}
                    {currentStep === 0 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-900">Confirm Patient Information</h2>

                            <div className="bg-teal-50 border border-teal-200 rounded-lg p-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <span className="text-sm text-slate-500">Patient Name</span>
                                        <p className="font-bold text-slate-900">{patient.patient_name}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm text-slate-500">Patient ID</span>
                                        <p className="font-bold text-slate-900">{patient.patient_id}</p>
                                    </div>
                                </div>
                            </div>

                            {patient.requires_assessment && (
                                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                    <div className="flex gap-3">
                                        <svg className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <div>
                                            <p className="font-medium text-amber-800">Assessment Required</p>
                                            <p className="text-sm text-amber-700 mt-1">{patient.message}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {assessmentHistory.length > 0 && (
                                <div>
                                    <h3 className="font-medium text-slate-900 mb-3">Assessment History</h3>
                                    <div className="space-y-2">
                                        {assessmentHistory.slice(0, 3).map((assessment) => (
                                            <div
                                                key={assessment.id}
                                                className={`p-3 rounded-lg border ${assessment.is_stale ? 'bg-amber-50 border-amber-200' : 'bg-white border-slate-200'}`}
                                            >
                                                <div className="flex justify-between items-center">
                                                    <div>
                                                        <span className="font-medium">{assessment.assessment_date?.split('T')[0]}</span>
                                                        <span className="text-slate-500 ml-2 text-sm">({assessment.source})</span>
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-sm">MAPLe: <strong>{assessment.maple_score || 'N/A'}</strong></span>
                                                        {assessment.is_stale && (
                                                            <span className="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full font-medium">Stale</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 1: Assessment Info */}
                    {currentStep === 1 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-900">Assessment Information</h2>

                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Assessment Type <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={formData.assessment_type}
                                        onChange={(e) => updateFormData('assessment_type', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        {formSchema?.assessment_types?.map((type) => (
                                            <option key={type.value} value={type.value}>{type.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Assessment Date <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={formData.assessment_date}
                                        onChange={(e) => updateFormData('assessment_date', e.target.value)}
                                        max={new Date().toISOString().split('T')[0]}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    />
                                </div>

                                <div className="col-span-2">
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Assessor Role
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.assessor_role}
                                        onChange={(e) => updateFormData('assessor_role', e.target.value)}
                                        placeholder="e.g., Care Coordinator, RN, OT"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Functional Scores */}
                    {currentStep === 2 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-900">Functional Assessment Scores</h2>
                            <p className="text-slate-600">Enter the calculated InterRAI HC output scores.</p>

                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        MAPLe Score <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={formData.maple_score}
                                        onChange={(e) => updateFormData('maple_score', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        <option value="">Select MAPLe Score</option>
                                        {formSchema?.maple_scores?.map((score) => (
                                            <option key={score.value} value={score.value}>{score.label}</option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">Method for Assigning Priority Levels</p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        ADL Hierarchy <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={formData.adl_hierarchy}
                                        onChange={(e) => updateFormData('adl_hierarchy', parseInt(e.target.value))}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        <option value="">Select ADL Level</option>
                                        {formSchema?.adl_hierarchy?.map((level) => (
                                            <option key={level.value} value={level.value}>{level.label}</option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">Activities of Daily Living</p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        IADL Difficulty
                                    </label>
                                    <select
                                        value={formData.iadl_difficulty}
                                        onChange={(e) => updateFormData('iadl_difficulty', e.target.value ? parseInt(e.target.value) : '')}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        <option value="">Select IADL Level</option>
                                        {[0, 1, 2, 3, 4, 5, 6].map((v) => (
                                            <option key={v} value={v}>{v}</option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">Instrumental Activities of Daily Living</p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Cognitive Performance Scale <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={formData.cognitive_performance_scale}
                                        onChange={(e) => updateFormData('cognitive_performance_scale', parseInt(e.target.value))}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        <option value="">Select CPS Level</option>
                                        {formSchema?.cps_scale?.map((level) => (
                                            <option key={level.value} value={level.value}>{level.label}</option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">CPS Score</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Clinical Indicators */}
                    {currentStep === 3 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-900">Clinical Indicators</h2>

                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Pain Scale
                                    </label>
                                    <select
                                        value={formData.pain_scale}
                                        onChange={(e) => updateFormData('pain_scale', e.target.value ? parseInt(e.target.value) : '')}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        <option value="">Select Pain Level</option>
                                        {formSchema?.pain_scale?.map((level) => (
                                            <option key={level.value} value={level.value}>{level.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        CHESS Score
                                    </label>
                                    <select
                                        value={formData.chess_score}
                                        onChange={(e) => updateFormData('chess_score', e.target.value ? parseInt(e.target.value) : '')}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        <option value="">Select CHESS Level</option>
                                        {formSchema?.chess_scale?.map((level) => (
                                            <option key={level.value} value={level.value}>{level.label}</option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">Changes in Health, End-Stage Disease, Signs and Symptoms</p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Depression Rating Scale
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="14"
                                        value={formData.depression_rating_scale}
                                        onChange={(e) => updateFormData('depression_rating_scale', e.target.value ? parseInt(e.target.value) : '')}
                                        placeholder="0-14"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">DRS (0-14)</p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Method for Locomotion
                                    </label>
                                    <select
                                        value={formData.method_for_locomotion}
                                        onChange={(e) => updateFormData('method_for_locomotion', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    >
                                        {formSchema?.locomotion_methods?.map((method) => (
                                            <option key={method} value={method}>{method}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="border-t pt-6">
                                <h3 className="font-medium text-slate-900 mb-4">Risk Indicators</h3>
                                <div className="space-y-4">
                                    <label className="flex items-center gap-3 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                                        <input
                                            type="checkbox"
                                            checked={formData.falls_in_last_90_days}
                                            onChange={(e) => updateFormData('falls_in_last_90_days', e.target.checked)}
                                            className="w-5 h-5 text-teal-600 rounded focus:ring-teal-500"
                                        />
                                        <div>
                                            <span className="font-medium text-slate-900">Falls in Last 90 Days</span>
                                            <p className="text-sm text-slate-500">Patient has experienced one or more falls</p>
                                        </div>
                                    </label>

                                    <label className="flex items-center gap-3 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                                        <input
                                            type="checkbox"
                                            checked={formData.wandering_flag}
                                            onChange={(e) => updateFormData('wandering_flag', e.target.checked)}
                                            className="w-5 h-5 text-teal-600 rounded focus:ring-teal-500"
                                        />
                                        <div>
                                            <span className="font-medium text-slate-900">Wandering/Elopement Risk</span>
                                            <p className="text-sm text-slate-500">Patient exhibits wandering behavior</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 4: CAPs & Diagnoses */}
                    {currentStep === 4 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-900">CAPs & Diagnoses</h2>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-3">
                                    Triggered CAPs (Clinical Assessment Protocols)
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    {formSchema?.common_caps?.map((cap) => (
                                        <label
                                            key={cap}
                                            className={`flex items-center gap-2 p-2 rounded border cursor-pointer transition-colors ${
                                                formData.caps_triggered.includes(cap)
                                                    ? 'bg-teal-50 border-teal-300'
                                                    : 'bg-white border-slate-200 hover:bg-slate-50'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={formData.caps_triggered.includes(cap)}
                                                onChange={() => toggleCap(cap)}
                                                className="w-4 h-4 text-teal-600 rounded focus:ring-teal-500"
                                            />
                                            <span className="text-sm">{cap}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Primary Diagnosis (ICD-10)
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.primary_diagnosis_icd10}
                                        onChange={(e) => updateFormData('primary_diagnosis_icd10', e.target.value.toUpperCase())}
                                        placeholder="e.g., G30.9"
                                        maxLength={20}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Secondary Diagnoses (comma-separated)
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.secondary_diagnoses.join(', ')}
                                        onChange={(e) => updateFormData(
                                            'secondary_diagnoses',
                                            e.target.value.split(',').map((s) => s.trim().toUpperCase()).filter(Boolean)
                                        )}
                                        placeholder="e.g., I10, E11.9"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 5: Review & Submit */}
                    {currentStep === 5 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-900">Review & Submit</h2>
                            <p className="text-slate-600">Please review the assessment data before submitting.</p>

                            <div className="space-y-4">
                                {/* Patient Info */}
                                <div className="bg-slate-50 rounded-lg p-4">
                                    <h3 className="font-medium text-slate-900 mb-2">Patient</h3>
                                    <p className="text-slate-700">{patient.patient_name}</p>
                                </div>

                                {/* Assessment Info */}
                                <div className="bg-slate-50 rounded-lg p-4">
                                    <h3 className="font-medium text-slate-900 mb-2">Assessment</h3>
                                    <div className="grid grid-cols-3 gap-4 text-sm">
                                        <div>
                                            <span className="text-slate-500">Type:</span>
                                            <span className="ml-2 font-medium">{formData.assessment_type.toUpperCase()}</span>
                                        </div>
                                        <div>
                                            <span className="text-slate-500">Date:</span>
                                            <span className="ml-2 font-medium">{formData.assessment_date}</span>
                                        </div>
                                        <div>
                                            <span className="text-slate-500">Assessor Role:</span>
                                            <span className="ml-2 font-medium">{formData.assessor_role}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Scores */}
                                <div className="bg-slate-50 rounded-lg p-4">
                                    <h3 className="font-medium text-slate-900 mb-2">Clinical Scores</h3>
                                    <div className="grid grid-cols-4 gap-4 text-sm">
                                        <div className="text-center p-2 bg-white rounded border">
                                            <span className="text-slate-500 block text-xs">MAPLe</span>
                                            <span className="text-xl font-bold text-teal-600">{formData.maple_score || '-'}</span>
                                        </div>
                                        <div className="text-center p-2 bg-white rounded border">
                                            <span className="text-slate-500 block text-xs">ADL</span>
                                            <span className="text-xl font-bold text-blue-600">{formData.adl_hierarchy ?? '-'}</span>
                                        </div>
                                        <div className="text-center p-2 bg-white rounded border">
                                            <span className="text-slate-500 block text-xs">CPS</span>
                                            <span className="text-xl font-bold text-purple-600">{formData.cognitive_performance_scale ?? '-'}</span>
                                        </div>
                                        <div className="text-center p-2 bg-white rounded border">
                                            <span className="text-slate-500 block text-xs">CHESS</span>
                                            <span className="text-xl font-bold text-amber-600">{formData.chess_score ?? '-'}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Risk Flags */}
                                {(formData.falls_in_last_90_days || formData.wandering_flag) && (
                                    <div className="bg-rose-50 border border-rose-200 rounded-lg p-4">
                                        <h3 className="font-medium text-rose-800 mb-2">Risk Flags</h3>
                                        <div className="flex gap-2">
                                            {formData.falls_in_last_90_days && (
                                                <span className="px-2 py-1 bg-rose-100 text-rose-800 text-xs rounded-full font-medium">Fall Risk</span>
                                            )}
                                            {formData.wandering_flag && (
                                                <span className="px-2 py-1 bg-rose-100 text-rose-800 text-xs rounded-full font-medium">Wandering Risk</span>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* CAPs */}
                                {formData.caps_triggered.length > 0 && (
                                    <div className="bg-slate-50 rounded-lg p-4">
                                        <h3 className="font-medium text-slate-900 mb-2">Triggered CAPs ({formData.caps_triggered.length})</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {formData.caps_triggered.map((cap) => (
                                                <span key={cap} className="px-2 py-1 bg-teal-100 text-teal-800 text-xs rounded-full">{cap}</span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p className="text-sm text-blue-800">
                                    <strong>Note:</strong> Upon submission, this assessment will be automatically queued for upload
                                    to the Ontario IAR (Integrated Assessment Record) system.
                                </p>
                            </div>
                        </div>
                    )}
                </Wizard>
            </div>
        </div>
    );
};

export default InterraiCompletionWizard;
