import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import interraiApi from '../../services/interraiApi';

/**
 * InterRAI HC Assessment Form
 *
 * Multi-section wizard for conducting InterRAI Home Care assessments.
 * Sections are completed in order with auto-save functionality.
 */
const InterraiAssessmentForm = () => {
    const { patientId, assessmentId } = useParams();
    const navigate = useNavigate();

    const [assessment, setAssessment] = useState(null);
    const [patient, setPatient] = useState(null);
    const [currentSection, setCurrentSection] = useState('C');
    const [items, setItems] = useState({});
    const [completedSections, setCompletedSections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [calculatedScores, setCalculatedScores] = useState(null);

    // Assessment sections in order
    const sections = [
        { code: 'C', name: 'Cognition', description: 'Decision making, memory, communication' },
        { code: 'D', name: 'Communication & Vision', description: 'Hearing, vision, speech' },
        { code: 'E', name: 'Mood & Behavior', description: 'Depression indicators, behavior' },
        { code: 'G', name: 'Functional Status', description: 'ADL and IADL performance' },
        { code: 'H', name: 'Continence', description: 'Bladder and bowel control' },
        { code: 'J', name: 'Health Conditions', description: 'Pain, symptoms, falls' },
        { code: 'P', name: 'Social Supports', description: 'Caregiver status' },
    ];

    // Load assessment data
    useEffect(() => {
        loadAssessment();
    }, [patientId, assessmentId]);

    const loadAssessment = async () => {
        try {
            setLoading(true);
            setError(null);

            if (assessmentId) {
                // Load existing assessment
                const response = await interraiApi.getAssessment(assessmentId);
                setAssessment(response.data);
                setPatient(response.data.patient);
                setItems(response.data.raw_items || {});
                setCompletedSections(response.data.sections_completed || []);
            } else {
                // Create new assessment
                const response = await interraiApi.startAssessment(patientId);
                setAssessment(response.data);
                setPatient(response.data.patient);
                setItems({});
                setCompletedSections([]);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load assessment');
        } finally {
            setLoading(false);
        }
    };

    // Auto-save when items change
    const handleItemChange = (itemCode, value) => {
        const newItems = { ...items, [itemCode]: parseInt(value) };
        setItems(newItems);

        // Debounced auto-save
        if (assessment?.id) {
            debouncedSave(newItems);
        }
    };

    const debouncedSave = React.useMemo(() => {
        let timeoutId;
        return (newItems) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => saveProgress(newItems), 1000);
        };
    }, [assessment?.id]);

    const saveProgress = async (itemsToSave) => {
        if (!assessment?.id) return;

        try {
            setSaving(true);
            await interraiApi.saveAssessmentProgress(assessment.id, {
                raw_items: itemsToSave,
                sections_completed: completedSections,
                current_section: currentSection,
            });
        } catch (err) {
            console.error('Auto-save failed:', err);
        } finally {
            setSaving(false);
        }
    };

    const completeSection = () => {
        if (!completedSections.includes(currentSection)) {
            const newCompleted = [...completedSections, currentSection];
            setCompletedSections(newCompleted);
        }

        // Move to next section
        const currentIndex = sections.findIndex(s => s.code === currentSection);
        if (currentIndex < sections.length - 1) {
            setCurrentSection(sections[currentIndex + 1].code);
        }
    };

    const calculateScores = async () => {
        try {
            const response = await interraiApi.calculateScores(assessment.id, items);
            setCalculatedScores(response.data);
        } catch (err) {
            setError('Failed to calculate scores');
        }
    };

    const submitAssessment = async () => {
        try {
            setSaving(true);
            await interraiApi.completeAssessment(assessment.id, {
                raw_items: items,
                sections_completed: completedSections,
            });
            navigate(`/patients/${patientId}`);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to submit assessment');
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-teal-600"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6">
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    {error}
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50">
            {/* Header */}
            <div className="bg-white border-b border-slate-200 sticky top-0 z-10">
                <div className="max-w-5xl mx-auto px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">InterRAI HC Assessment</h1>
                            <p className="text-sm text-slate-500">
                                Patient: {patient?.user?.name || patient?.name || 'Unknown'}
                            </p>
                        </div>
                        <div className="flex items-center gap-4">
                            {saving && (
                                <span className="text-sm text-slate-500 flex items-center gap-2">
                                    <div className="animate-spin h-4 w-4 border-2 border-slate-300 border-t-teal-600 rounded-full"></div>
                                    Saving...
                                </span>
                            )}
                            <button
                                onClick={() => navigate(-1)}
                                className="px-4 py-2 text-slate-600 hover:text-slate-900"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    {/* Section Navigation */}
                    <div className="mt-4 flex gap-2 overflow-x-auto pb-2">
                        {sections.map((section, index) => {
                            const isCompleted = completedSections.includes(section.code);
                            const isCurrent = currentSection === section.code;

                            return (
                                <button
                                    key={section.code}
                                    onClick={() => setCurrentSection(section.code)}
                                    className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
                                        isCurrent
                                            ? 'bg-teal-600 text-white'
                                            : isCompleted
                                                ? 'bg-teal-100 text-teal-700'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    {isCompleted && !isCurrent && (
                                        <span className="mr-1">✓</span>
                                    )}
                                    {section.code}. {section.name}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="max-w-5xl mx-auto px-6 py-8">
                <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <SectionForm
                        sectionCode={currentSection}
                        items={items}
                        onItemChange={handleItemChange}
                    />

                    {/* Section Actions */}
                    <div className="mt-8 pt-6 border-t border-slate-200 flex justify-between">
                        <button
                            onClick={() => {
                                const currentIndex = sections.findIndex(s => s.code === currentSection);
                                if (currentIndex > 0) {
                                    setCurrentSection(sections[currentIndex - 1].code);
                                }
                            }}
                            disabled={currentSection === sections[0].code}
                            className="px-6 py-2 text-slate-600 hover:text-slate-900 disabled:opacity-50"
                        >
                            ← Previous Section
                        </button>

                        {currentSection === sections[sections.length - 1].code ? (
                            <div className="flex gap-4">
                                <button
                                    onClick={calculateScores}
                                    className="px-6 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
                                >
                                    Preview Scores
                                </button>
                                <button
                                    onClick={submitAssessment}
                                    disabled={saving}
                                    className="px-6 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 disabled:opacity-50"
                                >
                                    Complete Assessment
                                </button>
                            </div>
                        ) : (
                            <button
                                onClick={completeSection}
                                className="px-6 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700"
                            >
                                Next Section →
                            </button>
                        )}
                    </div>
                </div>

                {/* Score Preview */}
                {calculatedScores && (
                    <div className="mt-6 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                        <h3 className="text-lg font-semibold text-slate-900 mb-4">Calculated Scores</h3>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <ScoreCard
                                label="CPS"
                                value={calculatedScores.scores?.cognitive_performance_scale}
                                max={6}
                                description={calculatedScores.score_descriptions?.cognitive_performance_scale}
                            />
                            <ScoreCard
                                label="ADL Hierarchy"
                                value={calculatedScores.scores?.adl_hierarchy}
                                max={6}
                                description={calculatedScores.score_descriptions?.adl_hierarchy}
                            />
                            <ScoreCard
                                label="MAPLe"
                                value={calculatedScores.scores?.maple_score}
                                max={5}
                                description={calculatedScores.score_descriptions?.maple_score}
                            />
                            <ScoreCard
                                label="CHESS"
                                value={calculatedScores.scores?.chess_score}
                                max={5}
                                description={calculatedScores.score_descriptions?.chess_score}
                            />
                            <ScoreCard
                                label="DRS"
                                value={calculatedScores.scores?.depression_rating_scale}
                                max={14}
                            />
                            <ScoreCard
                                label="Pain"
                                value={calculatedScores.scores?.pain_scale}
                                max={4}
                                description={calculatedScores.score_descriptions?.pain_scale}
                            />
                            <ScoreCard
                                label="IADL Difficulty"
                                value={calculatedScores.scores?.iadl_difficulty}
                                max={6}
                            />
                        </div>

                        {calculatedScores.recommended_psw_hours && (
                            <div className="mt-4 pt-4 border-t border-slate-200">
                                <div className="bg-teal-50 border border-teal-200 rounded-lg p-4">
                                    <h4 className="text-sm font-medium text-teal-900 mb-1">Recommended PSW Hours</h4>
                                    <p className="text-2xl font-bold text-teal-700">
                                        {calculatedScores.recommended_psw_hours} <span className="text-sm font-normal">hours/week</span>
                                    </p>
                                </div>
                            </div>
                        )}

                        {calculatedScores.caps_triggered?.length > 0 && (
                            <div className="mt-4 pt-4 border-t border-slate-200">
                                <h4 className="text-sm font-medium text-slate-700 mb-2">Triggered CAPs</h4>
                                <div className="flex flex-wrap gap-2">
                                    {calculatedScores.caps_triggered.map((cap, i) => (
                                        <span
                                            key={i}
                                            className={`px-3 py-1 rounded-full text-sm ${
                                                cap.priority === 'high'
                                                    ? 'bg-red-100 text-red-700'
                                                    : 'bg-amber-100 text-amber-700'
                                            }`}
                                        >
                                            {cap.name}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

/**
 * Score display card component.
 */
const ScoreCard = ({ label, value, max, description }) => {
    const percentage = value !== null && value !== undefined ? (value / max) * 100 : 0;
    const severity = percentage > 66 ? 'high' : percentage > 33 ? 'medium' : 'low';

    return (
        <div className="bg-slate-50 rounded-lg p-4">
            <div className="text-sm text-slate-500 mb-1">{label}</div>
            <div className={`text-2xl font-bold ${
                severity === 'high' ? 'text-red-600' :
                severity === 'medium' ? 'text-amber-600' : 'text-teal-600'
            }`}>
                {value !== null && value !== undefined ? value : '-'} <span className="text-sm text-slate-400">/ {max}</span>
            </div>
            {description && (
                <div className="text-xs text-slate-500 mt-1">{description}</div>
            )}
        </div>
    );
};

/**
 * Section form component - renders items for the current section.
 */
const SectionForm = ({ sectionCode, items, onItemChange }) => {
    const sectionItems = getSectionItems(sectionCode);

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-semibold text-slate-900">
                Section {sectionCode}: {getSectionTitle(sectionCode)}
            </h2>
            <p className="text-sm text-slate-500 mb-6">
                {getSectionInstructions(sectionCode)}
            </p>

            {sectionItems.map((item) => (
                <AssessmentItem
                    key={item.code}
                    item={item}
                    value={items[item.code]}
                    onChange={(value) => onItemChange(item.code, value)}
                />
            ))}
        </div>
    );
};

/**
 * Individual assessment item component.
 */
const AssessmentItem = ({ item, value, onChange }) => {
    return (
        <div className="border border-slate-200 rounded-lg p-4">
            <div className="flex items-start justify-between gap-4 mb-3">
                <div>
                    <span className="text-sm font-medium text-teal-600 mr-2">{item.code}</span>
                    <span className="font-medium text-slate-900">{item.label}</span>
                </div>
            </div>

            {item.description && (
                <p className="text-sm text-slate-500 mb-3">{item.description}</p>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                {item.options.map((option) => (
                    <label
                        key={option.value}
                        className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                            value === option.value
                                ? 'border-teal-500 bg-teal-50'
                                : 'border-slate-200 hover:border-slate-300'
                        }`}
                    >
                        <input
                            type="radio"
                            name={item.code}
                            value={option.value}
                            checked={value === option.value}
                            onChange={(e) => onChange(e.target.value)}
                            className="mt-1 text-teal-600 focus:ring-teal-500"
                        />
                        <div>
                            <span className="font-medium text-slate-700">{option.value}</span>
                            <span className="mx-1 text-slate-400">-</span>
                            <span className="text-slate-600">{option.label}</span>
                        </div>
                    </label>
                ))}
            </div>
        </div>
    );
};

// Helper functions for section data
function getSectionTitle(code) {
    const titles = {
        'C': 'Cognition',
        'D': 'Communication and Vision',
        'E': 'Mood and Behavior',
        'G': 'Functional Status',
        'H': 'Continence',
        'J': 'Health Conditions',
        'P': 'Social Supports',
    };
    return titles[code] || code;
}

function getSectionInstructions(code) {
    const instructions = {
        'C': 'Assess cognitive skills for daily decision making and memory recall over the last 3 days.',
        'D': 'Evaluate hearing, vision, and ability to make self understood.',
        'E': 'Identify indicators of depression and mood disturbance over the last 3 days.',
        'G': 'Rate performance in Activities of Daily Living (ADLs) and Instrumental ADLs over the last 3 days.',
        'H': 'Assess bladder and bowel continence control.',
        'J': 'Document health conditions including pain, symptoms, and fall history.',
        'P': 'Evaluate caregiver availability and stress level.',
    };
    return instructions[code] || '';
}

function getSectionItems(code) {
    const itemDefinitions = {
        'C': [
            {
                code: 'C1',
                label: 'Cognitive Skills for Daily Decision Making',
                description: 'Making decisions about tasks of daily life',
                options: [
                    { value: 0, label: 'Independent - Decisions consistent, reasonable, safe' },
                    { value: 1, label: 'Modified independence - Some difficulty in new situations' },
                    { value: 2, label: 'Minimally impaired - Poor decisions in specific situations' },
                    { value: 3, label: 'Moderately impaired - Decisions consistently poor, needs cues' },
                    { value: 4, label: 'Severely impaired - Never/rarely makes decisions' },
                    { value: 5, label: 'No discernible consciousness' },
                ]
            },
            {
                code: 'C2a',
                label: 'Short-term Memory OK',
                description: 'Seems to recall after 5 minutes',
                options: [
                    { value: 0, label: 'Yes, memory OK' },
                    { value: 1, label: 'Memory problem' },
                ]
            },
            {
                code: 'C2b',
                label: 'Procedural Memory OK',
                description: 'Can perform all steps in a multitask sequence',
                options: [
                    { value: 0, label: 'Yes, memory OK' },
                    { value: 1, label: 'Memory problem' },
                ]
            },
            {
                code: 'C3',
                label: 'Making Self Understood',
                description: 'Expressing information content',
                options: [
                    { value: 0, label: 'Understood' },
                    { value: 1, label: 'Usually understood' },
                    { value: 2, label: 'Often understood' },
                    { value: 3, label: 'Sometimes understood' },
                    { value: 4, label: 'Rarely/never understood' },
                ]
            },
        ],
        'E': [
            {
                code: 'E1a',
                label: 'Made Negative Statements',
                description: 'e.g., "Nothing matters", "Would rather be dead"',
                options: [
                    { value: 0, label: 'Not present' },
                    { value: 1, label: 'Present 1-2 of last 3 days' },
                    { value: 2, label: 'Present daily in last 3 days' },
                ]
            },
            {
                code: 'E1b',
                label: 'Persistent Anger',
                description: 'With self or others',
                options: [
                    { value: 0, label: 'Not present' },
                    { value: 1, label: 'Present 1-2 of last 3 days' },
                    { value: 2, label: 'Present daily in last 3 days' },
                ]
            },
            {
                code: 'E1f',
                label: 'Sad, Pained, Worried Facial Expressions',
                description: 'Observable expressions of distress',
                options: [
                    { value: 0, label: 'Not present' },
                    { value: 1, label: 'Present 1-2 of last 3 days' },
                    { value: 2, label: 'Present daily in last 3 days' },
                ]
            },
            {
                code: 'E1g',
                label: 'Crying, Tearfulness',
                description: '',
                options: [
                    { value: 0, label: 'Not present' },
                    { value: 1, label: 'Present 1-2 of last 3 days' },
                    { value: 2, label: 'Present daily in last 3 days' },
                ]
            },
        ],
        'G': [
            {
                code: 'G5a',
                label: 'Bathing',
                description: 'How person takes a full-body bath/shower',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
            {
                code: 'G5c',
                label: 'Personal Hygiene',
                description: 'Combing hair, brushing teeth, shaving, washing face/hands',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
            {
                code: 'G5g',
                label: 'Locomotion',
                description: 'How person moves between locations on same floor',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
            {
                code: 'G5i',
                label: 'Toilet Use',
                description: 'Using toilet, cleansing, adjusting clothes',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
            {
                code: 'G5k',
                label: 'Eating',
                description: 'How person eats and drinks',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
            {
                code: 'G4a',
                label: 'IADL: Meal Preparation',
                description: 'Preparing meals for self',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
            {
                code: 'G4d',
                label: 'IADL: Managing Medications',
                description: 'Taking medications as prescribed',
                options: [
                    { value: 0, label: 'Independent' },
                    { value: 1, label: 'Setup help only' },
                    { value: 2, label: 'Supervision' },
                    { value: 3, label: 'Limited assistance' },
                    { value: 4, label: 'Extensive assistance' },
                    { value: 5, label: 'Maximal assistance' },
                    { value: 6, label: 'Total dependence' },
                    { value: 8, label: 'Activity did not occur' },
                ]
            },
        ],
        'H': [
            {
                code: 'H1',
                label: 'Bladder Continence',
                description: 'Control of urinary bladder function',
                options: [
                    { value: 0, label: 'Continent' },
                    { value: 1, label: 'Control with catheter/ostomy' },
                    { value: 2, label: 'Infrequently incontinent' },
                    { value: 3, label: 'Occasionally incontinent' },
                    { value: 4, label: 'Frequently incontinent' },
                    { value: 5, label: 'Incontinent' },
                ]
            },
            {
                code: 'H2',
                label: 'Bowel Continence',
                description: 'Control of bowel movement',
                options: [
                    { value: 0, label: 'Continent' },
                    { value: 1, label: 'Control with ostomy' },
                    { value: 2, label: 'Infrequently incontinent' },
                    { value: 3, label: 'Occasionally incontinent' },
                    { value: 4, label: 'Frequently incontinent' },
                    { value: 5, label: 'Incontinent' },
                ]
            },
        ],
        'J': [
            {
                code: 'J1a',
                label: 'Pain Frequency',
                description: 'Frequency of pain',
                options: [
                    { value: 0, label: 'No pain' },
                    { value: 1, label: 'Pain present but not in last 3 days' },
                    { value: 2, label: 'Less than daily' },
                    { value: 3, label: 'Daily' },
                ]
            },
            {
                code: 'J1b',
                label: 'Pain Intensity',
                description: 'Intensity of highest level of pain',
                options: [
                    { value: 1, label: 'Mild' },
                    { value: 2, label: 'Moderate' },
                    { value: 3, label: 'Severe' },
                    { value: 4, label: 'Times when pain is horrible/excruciating' },
                ]
            },
            {
                code: 'J3',
                label: 'Falls',
                description: 'Any fall in last 90 days',
                options: [
                    { value: 0, label: 'No falls' },
                    { value: 1, label: 'Fell, no injury' },
                    { value: 2, label: 'Fell, injury' },
                ]
            },
            {
                code: 'J4',
                label: 'Weight Loss',
                description: 'Unintended weight loss',
                options: [
                    { value: 0, label: 'No' },
                    { value: 1, label: '5% or more in last 30 days' },
                    { value: 2, label: '10% or more in last 180 days' },
                ]
            },
            {
                code: 'J6',
                label: 'Insufficient Fluid Intake',
                description: 'Dehydration risk',
                options: [
                    { value: 0, label: 'No' },
                    { value: 1, label: 'Yes' },
                ]
            },
        ],
        'P': [
            {
                code: 'P1',
                label: 'Primary Caregiver Lives With Client',
                description: '',
                options: [
                    { value: 0, label: 'No' },
                    { value: 1, label: 'Yes' },
                ]
            },
            {
                code: 'P2',
                label: 'Caregiver Unable to Continue',
                description: 'Primary caregiver expresses inability to continue caring activities',
                options: [
                    { value: 0, label: 'No' },
                    { value: 1, label: 'Yes - caregiver unable/unwilling to continue' },
                ]
            },
        ],
        'D': [
            {
                code: 'D1',
                label: 'Hearing',
                description: 'Ability to hear with hearing aid if used',
                options: [
                    { value: 0, label: 'Adequate - no difficulty in normal conversation' },
                    { value: 1, label: 'Minimal difficulty' },
                    { value: 2, label: 'Moderate difficulty' },
                    { value: 3, label: 'Severe difficulty' },
                    { value: 4, label: 'No hearing' },
                ]
            },
            {
                code: 'D2',
                label: 'Vision',
                description: 'Ability to see in adequate light with glasses if used',
                options: [
                    { value: 0, label: 'Adequate' },
                    { value: 1, label: 'Impaired' },
                    { value: 2, label: 'Moderately impaired' },
                    { value: 3, label: 'Severely impaired' },
                    { value: 4, label: 'No vision' },
                ]
            },
        ],
    };

    return itemDefinitions[code] || [];
}

export default InterraiAssessmentForm;
