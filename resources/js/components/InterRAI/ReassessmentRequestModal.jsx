import React, { useState, useEffect } from 'react';
import Modal from '../UI/Modal';
import Button from '../UI/Button';
import Spinner from '../UI/Spinner';
import interraiApi from '../../services/interraiApi';

/**
 * ReassessmentRequestModal - Request a new InterRAI assessment
 *
 * Used when clinical condition changes require reassessment
 */
const ReassessmentRequestModal = ({
    isOpen,
    onClose,
    patientId,
    patientName,
    onSuccess,
}) => {
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [options, setOptions] = useState({ reasons: [], priorities: [] });
    const [formData, setFormData] = useState({
        reason: '',
        notes: '',
        priority: 'medium',
    });

    useEffect(() => {
        if (isOpen) {
            loadOptions();
            setFormData({ reason: '', notes: '', priority: 'medium' });
            setError(null);
        }
    }, [isOpen]);

    const loadOptions = async () => {
        setLoading(true);
        try {
            const result = await interraiApi.getReassessmentTriggerOptions();
            setOptions(result.data);
        } catch (err) {
            setError('Failed to load form options');
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.reason) {
            setError('Please select a reason for reassessment');
            return;
        }

        setSubmitting(true);
        setError(null);

        try {
            const result = await interraiApi.requestReassessment(
                patientId,
                formData.reason,
                formData.notes || null,
                formData.priority
            );

            if (onSuccess) {
                onSuccess(result.data);
            }
            onClose();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to submit reassessment request');
        } finally {
            setSubmitting(false);
        }
    };

    const getPriorityColor = (priority) => {
        const colors = {
            low: 'bg-slate-100 text-slate-700 border-slate-200',
            medium: 'bg-amber-100 text-amber-700 border-amber-200',
            high: 'bg-orange-100 text-orange-700 border-orange-200',
            urgent: 'bg-rose-100 text-rose-700 border-rose-200',
        };
        return colors[priority] || colors.medium;
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Request Reassessment">
            {loading ? (
                <div className="flex justify-center py-8">
                    <Spinner />
                </div>
            ) : (
                <form onSubmit={handleSubmit}>
                    {/* Patient info */}
                    <div className="mb-6 p-4 bg-slate-50 rounded-lg">
                        <p className="text-sm text-slate-500">Requesting reassessment for:</p>
                        <p className="font-medium text-slate-900">{patientName || `Patient #${patientId}`}</p>
                    </div>

                    {/* Error message */}
                    {error && (
                        <div className="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-sm">
                            {error}
                        </div>
                    )}

                    {/* Reason */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-slate-700 mb-2">
                            Reason for Reassessment *
                        </label>
                        <select
                            value={formData.reason}
                            onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                            required
                        >
                            <option value="">Select a reason...</option>
                            {options.reasons?.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Priority */}
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-slate-700 mb-2">
                            Priority
                        </label>
                        <div className="grid grid-cols-4 gap-2">
                            {options.priorities?.map((opt) => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => setFormData({ ...formData, priority: opt.value })}
                                    className={`
                                        px-3 py-2 rounded-lg border text-sm font-medium transition-all
                                        ${formData.priority === opt.value
                                            ? `${getPriorityColor(opt.value)} ring-2 ring-offset-1 ring-current`
                                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
                                        }
                                    `}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Notes */}
                    <div className="mb-6">
                        <label className="block text-sm font-medium text-slate-700 mb-2">
                            Additional Notes
                        </label>
                        <textarea
                            value={formData.notes}
                            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                            placeholder="Describe the clinical changes or specific concerns..."
                            rows={4}
                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                            maxLength={1000}
                        />
                        <p className="text-xs text-slate-400 mt-1">
                            {formData.notes.length}/1000 characters
                        </p>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" type="button" onClick={onClose} disabled={submitting}>
                            Cancel
                        </Button>
                        <Button variant="primary" type="submit" disabled={submitting}>
                            {submitting && <Spinner className="w-4 h-4 mr-2" />}
                            Submit Request
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
};

export default ReassessmentRequestModal;
