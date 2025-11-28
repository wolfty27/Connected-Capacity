import React, { useState } from 'react';
import { ChevronDown, ChevronRight, AlertTriangle, Check, X } from 'lucide-react';

/**
 * FullInterraiAssessment - Renders the complete InterRAI HC assessment
 *
 * Displays all assessment sections derived from the raw_items data,
 * with human-readable labels and values.
 */
const FullInterraiAssessment = ({ assessment, className = '' }) => {
    const [expandedSections, setExpandedSections] = useState({
        identification: true,
        cognition: true,
        adl: true,
        iadl: false,
        health_conditions: true,
        continence: false,
        mood: false,
        communication: false,
        diseases: false,
        treatments: false,
        social_supports: false,
    });

    const sections = assessment?.sections;

    if (!sections || Object.keys(sections).length === 0) {
        return (
            <div className={`bg-slate-50 border border-slate-200 rounded-lg p-6 text-center ${className}`}>
                <AlertTriangle className="w-8 h-8 text-slate-400 mx-auto mb-2" />
                <p className="text-slate-500 font-medium">No detailed assessment data available</p>
                <p className="text-sm text-slate-400 mt-1">
                    This assessment may have been completed externally without raw item data.
                </p>
            </div>
        );
    }

    const toggleSection = (sectionId) => {
        setExpandedSections(prev => ({
            ...prev,
            [sectionId]: !prev[sectionId],
        }));
    };

    return (
        <div className={`space-y-4 ${className}`}>
            <h3 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                Full InterRAI HC Assessment
                <span className="text-sm font-normal text-slate-500">
                    ({Object.keys(sections).length} sections)
                </span>
            </h3>

            {/* Identification Section */}
            <SectionCard
                title="Identification"
                sectionId="identification"
                isExpanded={expandedSections.identification}
                onToggle={toggleSection}
            >
                <IdentificationSection data={sections.identification} />
            </SectionCard>

            {/* Cognition Section */}
            <SectionCard
                title="Cognition"
                sectionId="cognition"
                isExpanded={expandedSections.cognition}
                onToggle={toggleSection}
                badge={sections.cognition?.cps_score !== undefined ? `CPS ${sections.cognition.cps_score}` : null}
            >
                <CognitionSection data={sections.cognition} />
            </SectionCard>

            {/* ADL Section */}
            <SectionCard
                title="Activities of Daily Living (ADL)"
                sectionId="adl"
                isExpanded={expandedSections.adl}
                onToggle={toggleSection}
                badge={sections.adl?.adl_hierarchy_score !== undefined ? `ADL ${sections.adl.adl_hierarchy_score}` : null}
            >
                <AdlSection data={sections.adl} />
            </SectionCard>

            {/* IADL Section */}
            <SectionCard
                title="Instrumental Activities of Daily Living (IADL)"
                sectionId="iadl"
                isExpanded={expandedSections.iadl}
                onToggle={toggleSection}
                badge={sections.iadl?.iadl_difficulty_score !== undefined ? `IADL ${sections.iadl.iadl_difficulty_score}` : null}
            >
                <IadlSection data={sections.iadl} />
            </SectionCard>

            {/* Health Conditions Section */}
            <SectionCard
                title="Health Conditions"
                sectionId="health_conditions"
                isExpanded={expandedSections.health_conditions}
                onToggle={toggleSection}
                badge={sections.health_conditions?.chess_score !== undefined ? `CHESS ${sections.health_conditions.chess_score}` : null}
            >
                <HealthConditionsSection data={sections.health_conditions} />
            </SectionCard>

            {/* Continence Section */}
            <SectionCard
                title="Continence"
                sectionId="continence"
                isExpanded={expandedSections.continence}
                onToggle={toggleSection}
            >
                <ContinenceSection data={sections.continence} />
            </SectionCard>

            {/* Mood Section */}
            <SectionCard
                title="Mood & Behaviour"
                sectionId="mood"
                isExpanded={expandedSections.mood}
                onToggle={toggleSection}
                badge={sections.mood?.depression_rating_scale !== undefined ? `DRS ${sections.mood.depression_rating_scale}` : null}
            >
                <MoodSection data={sections.mood} />
            </SectionCard>

            {/* Communication Section */}
            <SectionCard
                title="Communication"
                sectionId="communication"
                isExpanded={expandedSections.communication}
                onToggle={toggleSection}
            >
                <CommunicationSection data={sections.communication} />
            </SectionCard>

            {/* Diseases Section */}
            <SectionCard
                title="Diagnoses & Conditions"
                sectionId="diseases"
                isExpanded={expandedSections.diseases}
                onToggle={toggleSection}
            >
                <DiseasesSection data={sections.diseases} />
            </SectionCard>

            {/* Treatments Section */}
            <SectionCard
                title="Treatments & Procedures"
                sectionId="treatments"
                isExpanded={expandedSections.treatments}
                onToggle={toggleSection}
            >
                <TreatmentsSection data={sections.treatments} />
            </SectionCard>

            {/* Social Supports Section */}
            <SectionCard
                title="Social Supports"
                sectionId="social_supports"
                isExpanded={expandedSections.social_supports}
                onToggle={toggleSection}
            >
                <SocialSupportsSection data={sections.social_supports} />
            </SectionCard>
        </div>
    );
};

/**
 * Collapsible Section Card
 */
const SectionCard = ({ title, sectionId, isExpanded, onToggle, badge, children }) => (
    <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <button
            onClick={() => onToggle(sectionId)}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 transition-colors"
        >
            <div className="flex items-center gap-3">
                {isExpanded ? (
                    <ChevronDown className="w-5 h-5 text-slate-400" />
                ) : (
                    <ChevronRight className="w-5 h-5 text-slate-400" />
                )}
                <span className="font-medium text-slate-900">{title}</span>
            </div>
            {badge && (
                <span className="px-2 py-0.5 bg-teal-100 text-teal-700 text-xs font-medium rounded">
                    {badge}
                </span>
            )}
        </button>
        {isExpanded && (
            <div className="p-4 pt-0 border-t border-slate-100">
                {children}
            </div>
        )}
    </div>
);

/**
 * Field Row Component
 */
const FieldRow = ({ label, value, valueClass = '' }) => {
    if (value === null || value === undefined) return null;

    return (
        <div className="flex justify-between py-2 border-b border-slate-50 last:border-0">
            <span className="text-sm text-slate-600">{label}</span>
            <span className={`text-sm font-medium text-slate-900 ${valueClass}`}>{value}</span>
        </div>
    );
};

/**
 * Boolean Indicator
 */
const BooleanIndicator = ({ value, trueLabel = 'Yes', falseLabel = 'No' }) => {
    if (value) {
        return (
            <span className="inline-flex items-center gap-1 text-emerald-600">
                <Check className="w-4 h-4" />
                {trueLabel}
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 text-slate-400">
            <X className="w-4 h-4" />
            {falseLabel}
        </span>
    );
};

/**
 * Activity Grid for ADL/IADL
 */
const ActivityGrid = ({ activities, title }) => (
    <div className="mt-3">
        <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">{title}</h5>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            {Object.entries(activities).map(([key, activity]) => {
                if (!activity || activity.value === null) return null;
                const label = formatActivityLabel(key);
                return (
                    <div key={key} className="flex justify-between p-2 bg-slate-50 rounded text-sm">
                        <span className="text-slate-600">{label}</span>
                        <span className={`font-medium ${getActivityColor(activity.value)}`}>
                            {activity.label || activity.value}
                        </span>
                    </div>
                );
            })}
        </div>
    </div>
);

// Helper to format activity keys to labels
const formatActivityLabel = (key) => {
    return key
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// Helper to get color based on activity value
const getActivityColor = (value) => {
    if (value === 0) return 'text-emerald-600';
    if (value <= 2) return 'text-amber-600';
    if (value <= 4) return 'text-orange-600';
    return 'text-rose-600';
};

/* ============================================
   SECTION COMPONENTS
   ============================================ */

const IdentificationSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No identification data</p>;

    return (
        <div className="space-y-1">
            <FieldRow label="Assessment Type" value={data.assessment_type} />
            <FieldRow label="Assessment Date" value={data.assessment_date} />
            <FieldRow label="Source" value={data.source} />
            <FieldRow label="Assessor Role" value={data.assessor_role} />
            <FieldRow label="Primary Diagnosis (ICD-10)" value={data.primary_diagnosis} />
        </div>
    );
};

const CognitionSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No cognition data</p>;

    return (
        <div className="space-y-3">
            <div className="p-3 bg-teal-50 rounded-lg">
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-teal-800">CPS Score</span>
                    <span className="text-lg font-bold text-teal-700">{data.cps_score ?? '-'}</span>
                </div>
                <p className="text-sm text-teal-600">{data.cps_description}</p>
            </div>
            <FieldRow
                label="Decision Making"
                value={data.decision_making?.label}
            />
            <FieldRow
                label="Short-term Memory"
                value={data.short_term_memory?.label}
            />
            <FieldRow
                label="Making Self Understood"
                value={data.making_self_understood?.label}
            />
        </div>
    );
};

const AdlSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No ADL data</p>;

    return (
        <div className="space-y-3">
            <div className="p-3 bg-teal-50 rounded-lg">
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-teal-800">ADL Hierarchy Score</span>
                    <span className="text-lg font-bold text-teal-700">{data.adl_hierarchy_score ?? '-'}</span>
                </div>
                <p className="text-sm text-teal-600">{data.adl_hierarchy_description}</p>
            </div>
            {data.activities && (
                <ActivityGrid activities={data.activities} title="Individual ADL Activities" />
            )}
        </div>
    );
};

const IadlSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No IADL data</p>;

    return (
        <div className="space-y-3">
            <div className="p-3 bg-slate-100 rounded-lg">
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-slate-700">IADL Difficulty Score</span>
                    <span className="text-lg font-bold text-slate-800">{data.iadl_difficulty_score ?? '-'}</span>
                </div>
            </div>
            {data.activities && (
                <ActivityGrid activities={data.activities} title="Individual IADL Activities" />
            )}
        </div>
    );
};

const HealthConditionsSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No health conditions data</p>;

    const symptoms = data.symptoms || {};
    const activeSymptoms = Object.entries(symptoms).filter(([, v]) => v);

    return (
        <div className="space-y-4">
            {/* CHESS Score */}
            <div className="p-3 bg-amber-50 rounded-lg">
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-amber-800">CHESS Score (Health Instability)</span>
                    <span className="text-lg font-bold text-amber-700">{data.chess_score ?? '-'}</span>
                </div>
            </div>

            {/* Pain */}
            {data.pain && (
                <div>
                    <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">Pain</h5>
                    <div className="grid grid-cols-3 gap-2">
                        <div className="p-2 bg-slate-50 rounded text-center">
                            <div className="text-xs text-slate-500">Scale</div>
                            <div className="font-bold">{data.pain.pain_scale ?? '-'}</div>
                        </div>
                        <div className="p-2 bg-slate-50 rounded text-center">
                            <div className="text-xs text-slate-500">Frequency</div>
                            <div className="text-sm font-medium">{data.pain.frequency?.label || '-'}</div>
                        </div>
                        <div className="p-2 bg-slate-50 rounded text-center">
                            <div className="text-xs text-slate-500">Intensity</div>
                            <div className="text-sm font-medium">{data.pain.intensity?.label || '-'}</div>
                        </div>
                    </div>
                </div>
            )}

            {/* Symptoms */}
            {activeSymptoms.length > 0 && (
                <div>
                    <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">Active Symptoms</h5>
                    <div className="flex flex-wrap gap-2">
                        {activeSymptoms.map(([key]) => (
                            <span key={key} className="px-2 py-1 bg-rose-100 text-rose-700 text-xs rounded-full">
                                {formatActivityLabel(key)}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* Falls */}
            <FieldRow
                label="Fall History (Last 90 Days)"
                value={data.falls?.in_last_90_days ? 'Yes - ' + (data.falls.label || '') : 'No falls'}
            />

            {/* Other flags */}
            <div className="flex flex-wrap gap-3 text-sm">
                <div className="flex items-center gap-2">
                    <span className="text-slate-600">Weight Loss:</span>
                    <BooleanIndicator value={data.weight_loss} />
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-slate-600">Dehydration:</span>
                    <BooleanIndicator value={data.dehydration} />
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-slate-600">Vomiting:</span>
                    <BooleanIndicator value={data.vomiting} />
                </div>
            </div>
        </div>
    );
};

const ContinenceSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No continence data</p>;

    return (
        <div className="space-y-1">
            <FieldRow
                label="Bladder Continence"
                value={data.bladder_continence?.label}
            />
            <FieldRow
                label="Bowel Continence"
                value={data.bowel_continence?.label}
            />
        </div>
    );
};

const MoodSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No mood data</p>;

    const indicators = data.indicators || {};
    const activeIndicators = Object.entries(indicators).filter(
        ([, v]) => v && v.value && v.value > 0
    );

    return (
        <div className="space-y-3">
            <div className="p-3 bg-purple-50 rounded-lg">
                <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-purple-800">Depression Rating Scale</span>
                    <span className="text-lg font-bold text-purple-700">{data.depression_rating_scale ?? '-'}</span>
                </div>
            </div>

            {activeIndicators.length > 0 ? (
                <div>
                    <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">Mood Indicators Present</h5>
                    <div className="space-y-1">
                        {activeIndicators.map(([key, indicator]) => (
                            <FieldRow
                                key={key}
                                label={formatActivityLabel(key)}
                                value={indicator.label}
                            />
                        ))}
                    </div>
                </div>
            ) : (
                <p className="text-sm text-slate-500">No significant mood indicators present</p>
            )}
        </div>
    );
};

const CommunicationSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No communication data</p>;

    return (
        <div className="space-y-1">
            <FieldRow
                label="Hearing"
                value={data.hearing?.label}
            />
            <FieldRow
                label="Vision"
                value={data.vision?.label}
            />
        </div>
    );
};

const DiseasesSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No diagnoses data</p>;

    const categories = data.categories || {};

    return (
        <div className="space-y-4">
            <FieldRow
                label="Primary Diagnosis (ICD-10)"
                value={data.primary_diagnosis_icd10}
            />

            {data.secondary_diagnoses?.length > 0 && (
                <div>
                    <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">Secondary Diagnoses</h5>
                    <div className="flex flex-wrap gap-2">
                        {data.secondary_diagnoses.map((d, i) => (
                            <span key={i} className="px-2 py-1 bg-slate-100 text-slate-700 text-xs rounded">
                                {d}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {Object.entries(categories).map(([category, conditions]) => (
                conditions?.length > 0 && (
                    <div key={category}>
                        <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">
                            {formatActivityLabel(category)}
                        </h5>
                        <div className="flex flex-wrap gap-2">
                            {conditions.map((c, i) => (
                                <span key={i} className="px-2 py-1 bg-amber-100 text-amber-700 text-xs rounded">
                                    {c}
                                </span>
                            ))}
                        </div>
                    </div>
                )
            ))}
        </div>
    );
};

const TreatmentsSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No treatments data</p>;

    const extensiveServices = data.extensive_services || {};
    const clinicalTreatments = data.clinical_treatments || {};
    const therapy = data.therapy_services || {};

    const activeExtensive = Object.entries(extensiveServices).filter(([, v]) => v);
    const activeClinical = Object.entries(clinicalTreatments).filter(([, v]) => v);

    return (
        <div className="space-y-4">
            {activeExtensive.length > 0 && (
                <div>
                    <h5 className="text-xs font-semibold text-rose-600 uppercase mb-2">Extensive Services</h5>
                    <div className="flex flex-wrap gap-2">
                        {activeExtensive.map(([key]) => (
                            <span key={key} className="px-2 py-1 bg-rose-100 text-rose-700 text-xs rounded font-medium">
                                {formatActivityLabel(key)}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {activeClinical.length > 0 && (
                <div>
                    <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">Clinical Treatments</h5>
                    <div className="flex flex-wrap gap-2">
                        {activeClinical.map(([key]) => (
                            <span key={key} className="px-2 py-1 bg-amber-100 text-amber-700 text-xs rounded">
                                {formatActivityLabel(key)}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {(therapy.physical_therapy_minutes > 0 || therapy.occupational_therapy_minutes > 0 || therapy.speech_language_therapy_minutes > 0) && (
                <div>
                    <h5 className="text-xs font-semibold text-slate-500 uppercase mb-2">Therapy Services (Minutes/Week)</h5>
                    <div className="grid grid-cols-3 gap-2">
                        <div className="p-2 bg-slate-50 rounded text-center">
                            <div className="text-xs text-slate-500">Physical Therapy</div>
                            <div className="font-bold">{therapy.physical_therapy_minutes || 0}</div>
                        </div>
                        <div className="p-2 bg-slate-50 rounded text-center">
                            <div className="text-xs text-slate-500">Occupational Therapy</div>
                            <div className="font-bold">{therapy.occupational_therapy_minutes || 0}</div>
                        </div>
                        <div className="p-2 bg-slate-50 rounded text-center">
                            <div className="text-xs text-slate-500">Speech-Language</div>
                            <div className="font-bold">{therapy.speech_language_therapy_minutes || 0}</div>
                        </div>
                    </div>
                </div>
            )}

            {activeExtensive.length === 0 && activeClinical.length === 0 && !therapy.physical_therapy_minutes && !therapy.occupational_therapy_minutes && !therapy.speech_language_therapy_minutes && (
                <p className="text-sm text-slate-500">No active treatments or services</p>
            )}
        </div>
    );
};

const SocialSupportsSection = ({ data }) => {
    if (!data) return <p className="text-sm text-slate-400">No social supports data</p>;

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between py-2">
                <span className="text-sm text-slate-600">Primary Caregiver Lives With Client</span>
                <BooleanIndicator value={data.primary_caregiver_lives_with_client} />
            </div>
            <div className="flex items-center justify-between py-2">
                <span className="text-sm text-slate-600">Caregiver Unable to Continue</span>
                <BooleanIndicator value={data.caregiver_unable_to_continue} />
            </div>
            <div className="flex items-center justify-between py-2">
                <span className="text-sm text-slate-600">Wandering Behaviour</span>
                <BooleanIndicator value={data.wandering_behaviour} />
            </div>
        </div>
    );
};

export default FullInterraiAssessment;
