import api from './api';

/**
 * InterRAI API Service
 *
 * Handles all InterRAI HC assessment operations including:
 * - Assessment CRUD and status
 * - External assessment creation
 * - Document management
 * - Reassessment triggers
 * - Admin dashboard data
 */
const interraiApi = {
    // ===== Patient Assessment Status =====

    /**
     * Get patients needing InterRAI assessment
     */
    async getPatientsNeedingAssessment(limit = 50) {
        const response = await api.get('/api/v2/interrai/patients-needing-assessment', {
            params: { limit },
        });
        return response.data;
    },

    /**
     * Get InterRAI status for a patient
     */
    async getPatientStatus(patientId) {
        const response = await api.get(`/api/v2/interrai/patients/${patientId}/status`);
        return response.data;
    },

    /**
     * Get assessment history for a patient
     */
    async getPatientAssessments(patientId) {
        const response = await api.get(`/api/v2/interrai/patients/${patientId}/assessments`);
        return response.data;
    },

    // ===== Assessment CRUD =====

    /**
     * Get assessment details
     */
    async getAssessment(assessmentId) {
        const response = await api.get(`/api/v2/interrai/assessments/${assessmentId}`);
        return response.data;
    },

    /**
     * Create SPO-completed assessment
     */
    async createAssessment(patientId, data) {
        const response = await api.post(`/api/v2/interrai/patients/${patientId}/assessments`, data);
        return response.data;
    },

    /**
     * Start a new InterRAI HC assessment
     */
    async startAssessment(patientId, reassessmentReason = null) {
        const response = await api.post(`/api/v2/interrai/patients/${patientId}/assessments/start`, {
            reassessment_reason: reassessmentReason,
        });
        return response.data;
    },

    /**
     * Save assessment progress (auto-save)
     */
    async saveAssessmentProgress(assessmentId, data) {
        const response = await api.patch(`/api/v2/interrai/assessments/${assessmentId}/progress`, data);
        return response.data;
    },

    /**
     * Calculate scores from raw items
     */
    async calculateScores(assessmentId, rawItems) {
        const response = await api.post(`/api/v2/interrai/assessments/${assessmentId}/calculate-scores`, {
            raw_items: rawItems,
        });
        return response.data;
    },

    /**
     * Complete and finalize assessment
     */
    async completeAssessment(assessmentId, data) {
        const response = await api.post(`/api/v2/interrai/assessments/${assessmentId}/complete`, data);
        return response.data;
    },

    /**
     * Create externally-completed assessment
     */
    async createExternalAssessment(patientId, data) {
        const response = await api.post(`/api/v2/interrai/patients/${patientId}/assessments/external`, data);
        return response.data;
    },

    /**
     * Link external IAR document ID
     */
    async linkExternalIar(patientId, iarDocumentId, notes = null) {
        const response = await api.post(`/api/v2/interrai/patients/${patientId}/link-external`, {
            iar_document_id: iarDocumentId,
            notes,
        });
        return response.data;
    },

    // ===== Form Schema =====

    /**
     * Get form schema with options
     */
    async getFormSchema() {
        const response = await api.get('/api/v2/interrai/form-schema');
        return response.data;
    },

    /**
     * Get full assessment form schema with all sections
     */
    async getFullFormSchema() {
        const response = await api.get('/api/v2/interrai/full-form-schema');
        return response.data;
    },

    // ===== Document Management =====

    /**
     * Upload document to assessment
     */
    async uploadDocument(assessmentId, file, documentType = 'pdf', notes = null) {
        const formData = new FormData();
        formData.append('document', file);
        formData.append('document_type', documentType);
        if (notes) formData.append('notes', notes);

        const response = await api.post(
            `/api/v2/interrai/assessments/${assessmentId}/documents`,
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            }
        );
        return response.data;
    },

    /**
     * Get documents for assessment
     */
    async getDocuments(assessmentId) {
        const response = await api.get(`/api/v2/interrai/assessments/${assessmentId}/documents`);
        return response.data;
    },

    /**
     * Delete document
     */
    async deleteDocument(assessmentId, documentId) {
        const response = await api.delete(
            `/api/v2/interrai/assessments/${assessmentId}/documents/${documentId}`
        );
        return response.data;
    },

    // ===== Reassessment Triggers =====

    /**
     * Request reassessment for patient
     */
    async requestReassessment(patientId, reason, notes = null, priority = 'medium') {
        const response = await api.post(`/api/v2/interrai/patients/${patientId}/request-reassessment`, {
            reason,
            notes,
            priority,
        });
        return response.data;
    },

    /**
     * Get pending reassessment triggers
     */
    async getReassessmentTriggers(limit = 50, priority = null) {
        const response = await api.get('/api/v2/interrai/reassessment-triggers', {
            params: { limit, priority },
        });
        return response.data;
    },

    /**
     * Resolve reassessment trigger
     */
    async resolveReassessmentTrigger(triggerId, assessmentId, notes = null) {
        const response = await api.post(`/api/v2/interrai/reassessment-triggers/${triggerId}/resolve`, {
            assessment_id: assessmentId,
            notes,
        });
        return response.data;
    },

    /**
     * Get trigger form options
     */
    async getReassessmentTriggerOptions() {
        const response = await api.get('/api/v2/interrai/reassessment-trigger-options');
        return response.data;
    },

    // ===== IAR Upload Management =====

    /**
     * Retry IAR upload
     */
    async retryIarUpload(assessmentId) {
        const response = await api.post(`/api/v2/interrai/assessments/${assessmentId}/retry-iar`);
        return response.data;
    },

    /**
     * Get pending IAR uploads
     */
    async getPendingIarUploads() {
        const response = await api.get('/api/v2/interrai/pending-iar-uploads');
        return response.data;
    },

    /**
     * Get failed IAR uploads
     */
    async getFailedIarUploads() {
        const response = await api.get('/api/v2/interrai/failed-iar-uploads');
        return response.data;
    },

    // ===== Admin Dashboard =====

    /**
     * Get dashboard statistics
     */
    async getDashboardStats() {
        const response = await api.get('/api/v2/admin/interrai/dashboard-stats');
        return response.data;
    },

    /**
     * Get stale assessments list
     */
    async getStaleAssessments(limit = 50) {
        const response = await api.get('/api/v2/admin/interrai/stale-assessments', {
            params: { limit },
        });
        return response.data;
    },

    /**
     * Get missing assessments list
     */
    async getMissingAssessments(limit = 50) {
        const response = await api.get('/api/v2/admin/interrai/missing-assessments', {
            params: { limit },
        });
        return response.data;
    },

    /**
     * Get failed uploads list
     */
    async getFailedUploads(limit = 50) {
        const response = await api.get('/api/v2/admin/interrai/failed-uploads', {
            params: { limit },
        });
        return response.data;
    },

    /**
     * Bulk retry failed IAR uploads
     */
    async bulkRetryIar() {
        const response = await api.post('/api/v2/admin/interrai/bulk-retry-iar');
        return response.data;
    },

    /**
     * Sync all patient statuses
     */
    async syncStatuses() {
        const response = await api.post('/api/v2/admin/interrai/sync-statuses');
        return response.data;
    },

    /**
     * Get pending triggers for admin
     */
    async getPendingTriggers(limit = 50, priority = null) {
        const response = await api.get('/api/v2/admin/interrai/pending-triggers', {
            params: { limit, priority },
        });
        return response.data;
    },

    /**
     * Get compliance report
     */
    async getComplianceReport() {
        const response = await api.get('/api/v2/admin/interrai/compliance-report');
        return response.data;
    },
};

export default interraiApi;
