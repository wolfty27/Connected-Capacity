import api from '../services/api';

/**
 * Patient Queue API Service
 *
 * Provides methods for interacting with the Workday-style patient queue
 * management system. Handles queue CRUD operations and status transitions.
 */
const patientQueueApi = {
    /**
     * Get all patients in the queue with optional filters.
     *
     * @param {Object} params - Query parameters
     * @param {string} params.status - Filter by queue status
     * @param {number} params.coordinator_id - Filter by assigned coordinator
     * @param {number} params.priority - Filter by priority (<=)
     * @param {number} params.per_page - Results per page
     * @returns {Promise} API response with queue data and summary
     */
    async getQueue(params = {}) {
        const response = await api.get('/v2/patient-queue', { params });
        return response.data;
    },

    /**
     * Get a specific queue entry by ID.
     *
     * @param {number} id - Queue entry ID
     * @returns {Promise} Queue entry with patient and transition history
     */
    async getQueueEntry(id) {
        const response = await api.get(`/v2/patient-queue/${id}`);
        return response.data;
    },

    /**
     * Add a patient to the queue.
     *
     * @param {Object} data - Queue entry data
     * @param {number} data.patient_id - Patient ID
     * @param {number} data.priority - Priority (1-10, 1 is highest)
     * @param {number} data.assigned_coordinator_id - Coordinator user ID
     * @param {string} data.notes - Optional notes
     * @returns {Promise} Created queue entry
     */
    async addToQueue(data) {
        const response = await api.post('/v2/patient-queue', data);
        return response.data;
    },

    /**
     * Update a queue entry.
     *
     * @param {number} id - Queue entry ID
     * @param {Object} data - Update data
     * @returns {Promise} Updated queue entry
     */
    async updateQueueEntry(id, data) {
        const response = await api.put(`/v2/patient-queue/${id}`, data);
        return response.data;
    },

    /**
     * Transition a queue entry to a new status.
     *
     * @param {number} id - Queue entry ID
     * @param {string} toStatus - Target status
     * @param {string} reason - Transition reason (optional)
     * @param {Object} context - Additional context data (optional)
     * @returns {Promise} Updated queue entry with transitions
     */
    async transition(id, toStatus, reason = null, context = null) {
        const response = await api.post(`/v2/patient-queue/${id}/transition`, {
            to_status: toStatus,
            reason,
            context,
        });
        return response.data;
    },

    /**
     * Get patients ready for bundle building.
     *
     * @param {Object} params - Query parameters
     * @returns {Promise} List of patients ready for bundles
     */
    async getReadyForBundle(params = {}) {
        const response = await api.get('/v2/patient-queue/ready-for-bundle', { params });
        return response.data;
    },

    /**
     * Get transition history for a queue entry.
     *
     * @param {number} id - Queue entry ID
     * @returns {Promise} List of transitions
     */
    async getTransitions(id) {
        const response = await api.get(`/v2/patient-queue/${id}/transitions`);
        return response.data;
    },

    /**
     * Start bundle building for a patient.
     *
     * @param {number} id - Queue entry ID
     * @returns {Promise} Updated queue entry with redirect URL
     */
    async startBundleBuilding(id) {
        const response = await api.post(`/v2/patient-queue/${id}/start-bundle`);
        return response.data;
    },

    /**
     * Queue status constants.
     */
    STATUSES: {
        PENDING_INTAKE: 'pending_intake',
        TRIAGE_IN_PROGRESS: 'triage_in_progress',
        TRIAGE_COMPLETE: 'triage_complete',
        ASSESSMENT_IN_PROGRESS: 'assessment_in_progress',
        ASSESSMENT_COMPLETE: 'assessment_complete',
        BUNDLE_BUILDING: 'bundle_building',
        BUNDLE_REVIEW: 'bundle_review',
        BUNDLE_APPROVED: 'bundle_approved',
        TRANSITIONED: 'transitioned',
    },

    /**
     * Status display labels.
     */
    STATUS_LABELS: {
        pending_intake: 'Pending Intake',
        triage_in_progress: 'Triage In Progress',
        triage_complete: 'Triage Complete',
        assessment_in_progress: 'InterRAI HC Assessment In Progress',
        assessment_complete: 'InterRAI HC Assessment Complete - Ready for Bundle',
        bundle_building: 'Building Care Bundle',
        bundle_review: 'Bundle Under Review',
        bundle_approved: 'Bundle Approved',
        transitioned: 'Transitioned to Active',
    },

    /**
     * Get display label for a status.
     */
    getStatusLabel(status) {
        return this.STATUS_LABELS[status] || status;
    },

    /**
     * Status colors for UI - standardized per CC2.1 design:
     * gray → intake | yellow → triage | blue → assessment | green → ready | purple → bundle_building
     */
    STATUS_COLORS: {
        pending_intake: 'gray',
        triage_in_progress: 'yellow',
        triage_complete: 'yellow',
        assessment_in_progress: 'blue',
        assessment_complete: 'green',
        bundle_building: 'purple',
        bundle_review: 'orange',
        bundle_approved: 'emerald',
        transitioned: 'slate',
    },

    /**
     * Get color class for a status.
     */
    getStatusColor(status) {
        return this.STATUS_COLORS[status] || 'gray';
    },
};

export default patientQueueApi;
