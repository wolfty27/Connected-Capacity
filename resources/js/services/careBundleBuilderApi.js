import api from './api';

/**
 * Care Bundle Builder API Service
 *
 * Provides methods for the metadata-driven care bundle building process.
 * Handles bundle retrieval, plan creation, and publishing (which triggers
 * the transition from queue to active patient profile).
 */
const careBundleBuilderApi = {
    /**
     * Get available bundles configured for a patient.
     *
     * Returns bundles with services pre-configured based on the patient's
     * TNP assessment and clinical flags using the metadata engine.
     *
     * @param {number} patientId - Patient ID
     * @returns {Promise} Bundles with recommendations
     */
    async getBundles(patientId) {
        const response = await api.get(`/v2/care-builder/${patientId}/bundles`);
        return response.data;
    },

    /**
     * Get a specific bundle configured for a patient.
     *
     * @param {number} patientId - Patient ID
     * @param {number} bundleId - Bundle ID
     * @returns {Promise} Bundle with configured services
     */
    async getBundle(patientId, bundleId) {
        const response = await api.get(`/v2/care-builder/${patientId}/bundles/${bundleId}`);
        return response.data;
    },

    /**
     * Preview a bundle configuration without saving.
     *
     * @param {number} patientId - Patient ID
     * @param {number} bundleId - Bundle ID
     * @param {Array} services - Optional service overrides
     * @returns {Promise} Preview of bundle with calculated costs
     */
    async previewBundle(patientId, bundleId, services = null) {
        const response = await api.post(`/v2/care-builder/${patientId}/bundles/preview`, {
            bundle_id: bundleId,
            services,
        });
        return response.data;
    },

    /**
     * Build a care plan from a bundle configuration.
     *
     * Creates a draft care plan with service assignments.
     *
     * @param {number} patientId - Patient ID
     * @param {number} bundleId - Bundle ID
     * @param {Array} services - Service configurations
     * @param {string} notes - Optional notes
     * @returns {Promise} Created care plan
     */
    async buildPlan(patientId, bundleId, services, notes = null) {
        const response = await api.post(`/v2/care-builder/${patientId}/plans`, {
            bundle_id: bundleId,
            services,
            notes,
        });
        return response.data;
    },

    /**
     * Publish a care plan and transition patient to active profile.
     *
     * This is the key transition point where the patient moves from
     * the queue list to their regular patient profile.
     *
     * @param {number} patientId - Patient ID
     * @param {number} carePlanId - Care plan ID
     * @returns {Promise} Published plan with transition details
     */
    async publishPlan(patientId, carePlanId) {
        const response = await api.post(`/v2/care-builder/${patientId}/plans/${carePlanId}/publish`);
        return response.data;
    },

    /**
     * Get care plan history for a patient.
     *
     * @param {number} patientId - Patient ID
     * @returns {Promise} List of care plans with summary
     */
    async getPlanHistory(patientId) {
        const response = await api.get(`/v2/care-builder/${patientId}/plans`);
        return response.data;
    },

    /**
     * Transform service configuration for API.
     *
     * @param {Array} services - Frontend service configurations
     * @returns {Array} Formatted for API
     */
    formatServicesForApi(services) {
        return services
            .filter(s => (s.currentFrequency || 0) > 0)
            .map(s => ({
                service_type_id: parseInt(s.service_type_id || s.id),
                currentFrequency: s.currentFrequency,
                currentDuration: s.currentDuration,
                provider_id: s.provider_id || null,
                notes: s.notes || null,
            }));
    },

    /**
     * Calculate total cost from services.
     *
     * @param {Array} services - Service configurations
     * @returns {number} Total cost
     */
    calculateTotalCost(services) {
        return services.reduce((total, service) => {
            const frequency = service.currentFrequency || 0;
            const duration = service.currentDuration || 0;
            const cost = service.costPerVisit || 0;
            return total + (frequency * duration * cost);
        }, 0);
    },

    /**
     * Calculate monthly cost from services.
     *
     * @param {Array} services - Service configurations
     * @returns {number} Monthly cost (4 weeks)
     */
    calculateMonthlyCost(services) {
        return services.reduce((total, service) => {
            const frequency = service.currentFrequency || 0;
            const cost = service.costPerVisit || 0;
            return total + (frequency * 4 * cost);
        }, 0);
    },
};

export default careBundleBuilderApi;
