export const INITIAL_SERVICES = [
    // Clinical Services
    {
        id: '1',
        category: 'CLINICAL',
        name: 'Nursing (RN/RPN)',
        code: 'NUR',
        description: 'Wound Care: Surgical, pressure ulcers, negative pressure therapy. Infusion: IV therapy, CVAD maintenance. Palliative: Pain/symptom management.',
        defaultFrequency: 2,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 120,
        costCode: 'COST-NUR',
        costDriver: 'Hourly Labour or Per Visit Rate'
    },
    {
        id: '2',
        category: 'CLINICAL',
        name: 'Physiotherapy (PT)',
        code: 'PT',
        description: 'Mobility: Gait training, fall prevention, transfer training. Chest PT: Postural drainage. Modalities: Ultrasound, TENS.',
        defaultFrequency: 2,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 140,
        costCode: 'COST-PT',
        costDriver: 'Per Visit Rate'
    },
    {
        id: '3',
        category: 'CLINICAL',
        name: 'Occupational Therapy (OT)',
        code: 'OT',
        description: 'ADL Training: Feeding, dressing, bathing retraining. Safety: Home environment assessment, equipment prescription (ADP).',
        defaultFrequency: 1,
        defaultDuration: 8,
        currentFrequency: 0,
        currentDuration: 8,
        provider: '',
        costPerVisit: 150,
        costCode: 'COST-OT',
        costDriver: 'Per Visit Rate'
    },
    {
        id: '4',
        category: 'CLINICAL',
        name: 'Respiratory Therapy (RT)',
        code: 'RT',
        description: 'Airway: Tracheostomy care, deep suctioning, ventilator management. Oxygen: Home oxygen titration and setup.',
        defaultFrequency: 1,
        defaultDuration: 4,
        currentFrequency: 0,
        currentDuration: 4,
        provider: '',
        costPerVisit: 130,
        costCode: 'COST-RT',
        costDriver: 'Per Visit Rate'
    },
    {
        id: '5',
        category: 'CLINICAL',
        name: 'Social Work (SW)',
        code: 'SW',
        description: 'Counseling: Grief, adjustment to illness, crisis intervention. Navigation: Financial aid, housing, LTC placement.',
        defaultFrequency: 1,
        defaultDuration: 6,
        currentFrequency: 0,
        currentDuration: 6,
        provider: '',
        costPerVisit: 135,
        costCode: 'COST-SW',
        costDriver: 'Per Visit Rate'
    },
    {
        id: '6',
        category: 'CLINICAL',
        name: 'Dietetics (RD)',
        code: 'RD',
        description: 'Nutrition: Therapeutic diets (diabetes, dysphagia), tube feeding formulas. Assessment: Weight monitoring, malnutrition.',
        defaultFrequency: 1,
        defaultDuration: 4,
        currentFrequency: 0,
        currentDuration: 4,
        provider: '',
        costPerVisit: 125,
        costCode: 'COST-RD',
        costDriver: 'Per Visit Rate'
    },
    {
        id: '7',
        category: 'CLINICAL',
        name: 'Speech-Language (SLP)',
        code: 'SLP',
        description: 'Swallowing: Dysphagia management, texture modification. Communication: Aphasia therapy, voice devices.',
        defaultFrequency: 1,
        defaultDuration: 8,
        currentFrequency: 0,
        currentDuration: 8,
        provider: '',
        costPerVisit: 145,
        costCode: 'COST-SLP',
        costDriver: 'Per Visit Rate'
    },
    {
        id: '8',
        category: 'CLINICAL',
        name: 'Nurse Practitioner (NP)',
        code: 'NP',
        description: 'Advanced Care: Prescribing, diagnosing, higher-acuity management to prevent ED visits.',
        defaultFrequency: 1,
        defaultDuration: 1,
        currentFrequency: 0,
        currentDuration: 1,
        provider: '',
        costPerVisit: 200,
        costCode: 'COST-NP',
        costDriver: 'Salaried / Hourly'
    },

    // Personal Support & Daily Living
    {
        id: '9',
        category: 'PERSONAL',
        name: 'Personal Care (PSW)',
        code: 'PSW',
        description: 'Hygiene: Bathing, grooming, toileting. Mobility: Transfers, turning/positioning.',
        defaultFrequency: 7,
        defaultDuration: 52,
        currentFrequency: 0,
        currentDuration: 52,
        provider: '',
        costPerVisit: 45,
        costCode: 'COST-PSW',
        costDriver: 'Hourly Labour'
    },
    {
        id: '10',
        category: 'PERSONAL',
        name: 'Homemaking',
        code: 'HMK',
        description: 'Cleaning: Light housekeeping, laundry, changing linens. Errands: Banking, grocery shopping assistance.',
        defaultFrequency: 1,
        defaultDuration: 52,
        currentFrequency: 0,
        currentDuration: 52,
        provider: '',
        costPerVisit: 40,
        costCode: 'COST-PSW',
        costDriver: 'Hourly Labour'
    },
    {
        id: '11',
        category: 'PERSONAL',
        name: 'Delegated Acts',
        code: 'DEL-ACTS',
        description: 'Regulated Tasks: Pre-loaded injections, glucometer testing, suctioning (must be taught/delegated by Nurse).',
        defaultFrequency: 7,
        defaultDuration: 52,
        currentFrequency: 0,
        currentDuration: 52,
        provider: '',
        costPerVisit: 50,
        costCode: 'COST-PSW',
        costDriver: 'Hourly Labour'
    },
    {
        id: '12',
        category: 'PERSONAL',
        name: 'Respite Care',
        code: 'RES',
        description: 'Caregiver Relief: In-home supervision to allow family caregivers a break.',
        defaultFrequency: 1,
        defaultDuration: 52,
        currentFrequency: 0,
        currentDuration: 52,
        provider: '',
        costPerVisit: 45,
        costCode: 'COST-RFS',
        costDriver: 'Hourly Labour'
    },

    // Safety, Monitoring & Technology
    {
        id: '13',
        category: 'SAFETY',
        name: 'Lifeline (PERS)',
        code: 'PERS',
        description: 'Personal Emergency Response System. Wearable button connecting to 24/7 emergency response.',
        defaultFrequency: 1,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 50,
        costCode: 'COST-PERS',
        costDriver: 'Monthly Subscription'
    },
    {
        id: '14',
        category: 'SAFETY',
        name: 'Remote Patient Monitoring (RPM)',
        code: 'RPM',
        description: 'Digital Health Tracking. Equipment (tablets, BP cuffs) to track vitals remotely. Includes monitoring.',
        defaultFrequency: 1,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 150,
        costCode: 'COST-RPM',
        costDriver: 'Device Lease + Software Fee'
    },
    {
        id: '15',
        category: 'SAFETY',
        name: 'Security Checks',
        code: 'SEC',
        description: 'Safety Checks. Telephone reassurance or physical safety checks for isolated patients.',
        defaultFrequency: 7,
        defaultDuration: 52,
        currentFrequency: 0,
        currentDuration: 52,
        provider: '',
        costPerVisit: 30,
        costCode: 'COST-SEC',
        costDriver: 'Staff Time (Admin/PSW)'
    },

    // Logistics & Access Services
    {
        id: '16',
        category: 'LOGISTICS',
        name: 'Medical Transportation',
        code: 'TRANS',
        description: 'Patient Transport. Covers travel to medical appointments (local and out-of-town).',
        defaultFrequency: 1,
        defaultDuration: 1,
        currentFrequency: 0,
        currentDuration: 1,
        provider: '',
        costPerVisit: 80,
        costCode: 'COST-TRSPT',
        costDriver: 'Per Trip / Per Km'
    },
    {
        id: '17',
        category: 'LOGISTICS',
        name: 'In-Home Laboratory',
        code: 'LAB',
        description: 'Mobile Lab Services. Technicians dispatched to the home for blood draws/specimen collection.',
        defaultFrequency: 1,
        defaultDuration: 1,
        currentFrequency: 0,
        currentDuration: 1,
        provider: '',
        costPerVisit: 60,
        costCode: 'COST-LAB',
        costDriver: 'Per Visit Fee'
    },
    {
        id: '18',
        category: 'LOGISTICS',
        name: 'Pharmacy Support',
        code: 'PHAR',
        description: 'Medication Logistics. Delivery fees, blister packing, or medication reconciliation support.',
        defaultFrequency: 1,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 25,
        costCode: 'COST-PHAR',
        costDriver: 'Per Delivery / Service Fee'
    },
    {
        id: '19',
        category: 'LOGISTICS',
        name: 'Language Services',
        code: 'INTERP',
        description: 'Interpretation. Professional translation/interpretation for non-English/French speaking patients.',
        defaultFrequency: 1,
        defaultDuration: 1,
        currentFrequency: 0,
        currentDuration: 1,
        provider: '',
        costPerVisit: 100,
        costCode: 'COST-RFS',
        costDriver: 'Per Minute / Per Hour'
    },
    {
        id: '20',
        category: 'LOGISTICS',
        name: 'Meal Delivery',
        code: 'MEAL',
        description: 'Nutrition Support. Coordination and payment for prepared meal delivery (e.g., Meals on Wheels).',
        defaultFrequency: 7,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 15,
        costCode: 'COST-MEAL',
        costDriver: 'Per Meal Cost'
    },
    {
        id: '21',
        category: 'LOGISTICS',
        name: 'Social/Recreational',
        code: 'REC',
        description: 'Activation. Adult day programs, friendly visiting, or social inclusion programming.',
        defaultFrequency: 1,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 50,
        costCode: 'COST-REC',
        costDriver: 'Program Fee / Hourly'
    },
    {
        id: '22',
        category: 'LOGISTICS',
        name: 'Behavioral Supports',
        code: 'BEH',
        description: 'Dementia Care. Specialized support strategies for responsive behaviors (BSO).',
        defaultFrequency: 1,
        defaultDuration: 12,
        currentFrequency: 0,
        currentDuration: 12,
        provider: '',
        costPerVisit: 100,
        costCode: 'COST-BEH',
        costDriver: 'Hourly (Specialized)'
    }
];

// Service categories for grouping
export const SERVICE_CATEGORIES = {
    CLINICAL: {
        code: 'CLINICAL',
        name: 'Clinical Services',
        description: 'Medical and therapeutic services provided by licensed clinicians'
    },
    PERSONAL: {
        code: 'PERSONAL',
        name: 'Personal Support & Daily Living',
        description: 'Personal care and daily living support services'
    },
    SAFETY: {
        code: 'SAFETY',
        name: 'Safety, Monitoring & Technology',
        description: 'Technology-enabled safety and monitoring services'
    },
    LOGISTICS: {
        code: 'LOGISTICS',
        name: 'Logistics & Access Services',
        description: 'Support services for access to care and daily needs'
    }
};

// Get services by category
export const getServicesByCategory = (categoryCode) => {
    return INITIAL_SERVICES.filter(service => service.category === categoryCode);
};

// Get service by code
export const getServiceByCode = (code) => {
    return INITIAL_SERVICES.find(service => service.code === code);
};
