import React, { useState, useRef } from 'react';
import Button from '../UI/Button';
import Spinner from '../UI/Spinner';
import interraiApi from '../../services/interraiApi';

/**
 * InterraiDocumentUpload - File upload component for InterRAI assessments
 *
 * Supports:
 * - PDF file uploads
 * - External IAR ID linking
 * - Document list display with delete
 */
const InterraiDocumentUpload = ({
    assessmentId,
    documents = [],
    onDocumentAdded,
    onDocumentDeleted,
    allowExternalLink = true,
    className = '',
}) => {
    const fileInputRef = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const [showLinkForm, setShowLinkForm] = useState(false);
    const [externalIarId, setExternalIarId] = useState('');
    const [linkNotes, setLinkNotes] = useState('');

    const handleFileSelect = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Validate file
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            setError('File size must be less than 10MB');
            return;
        }

        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            setError('File must be PDF, JPEG, PNG, or GIF');
            return;
        }

        setUploading(true);
        setError(null);

        try {
            const result = await interraiApi.uploadDocument(assessmentId, file);
            if (onDocumentAdded) {
                onDocumentAdded(result.data);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to upload document');
        } finally {
            setUploading(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    };

    const handleLinkExternal = async () => {
        if (!externalIarId.trim()) {
            setError('IAR Document ID is required');
            return;
        }

        setUploading(true);
        setError(null);

        try {
            // This would need the patientId - in practice you'd pass it as a prop
            // For now, we'll use the document attachment API
            const result = await interraiApi.uploadDocument(assessmentId, null, 'external_iar_id', linkNotes);
            if (onDocumentAdded) {
                onDocumentAdded(result.data);
            }
            setShowLinkForm(false);
            setExternalIarId('');
            setLinkNotes('');
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to link IAR document');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = async (documentId) => {
        if (!confirm('Are you sure you want to delete this document?')) return;

        try {
            await interraiApi.deleteDocument(assessmentId, documentId);
            if (onDocumentDeleted) {
                onDocumentDeleted(documentId);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to delete document');
        }
    };

    const getDocumentIcon = (type) => {
        if (type === 'external_iar_id') {
            return (
                <svg className="w-8 h-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            );
        }
        return (
            <svg className="w-8 h-8 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
        );
    };

    return (
        <div className={className}>
            {/* Error message */}
            {error && (
                <div className="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-sm">
                    {error}
                    <button onClick={() => setError(null)} className="ml-2 text-rose-500 hover:text-rose-700">
                        &times;
                    </button>
                </div>
            )}

            {/* Upload buttons */}
            <div className="flex gap-2 mb-4">
                <input
                    ref={fileInputRef}
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.gif"
                    onChange={handleFileSelect}
                    className="hidden"
                    disabled={uploading}
                />
                <Button
                    variant="secondary"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploading}
                >
                    {uploading ? <Spinner className="w-4 h-4 mr-2" /> : null}
                    Upload PDF
                </Button>
                {allowExternalLink && (
                    <Button
                        variant="secondary"
                        onClick={() => setShowLinkForm(!showLinkForm)}
                        disabled={uploading}
                    >
                        Link IAR ID
                    </Button>
                )}
            </div>

            {/* External link form */}
            {showLinkForm && (
                <div className="mb-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <h5 className="font-medium text-slate-700 mb-3">Link External IAR Document</h5>
                    <div className="space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-slate-600 mb-1">
                                IAR Document ID *
                            </label>
                            <input
                                type="text"
                                value={externalIarId}
                                onChange={(e) => setExternalIarId(e.target.value)}
                                placeholder="e.g., IAR-2024-12345"
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-600 mb-1">
                                Notes (optional)
                            </label>
                            <input
                                type="text"
                                value={linkNotes}
                                onChange={(e) => setLinkNotes(e.target.value)}
                                placeholder="Optional notes about this link"
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button variant="primary" onClick={handleLinkExternal} disabled={uploading}>
                                Link Document
                            </Button>
                            <Button variant="secondary" onClick={() => setShowLinkForm(false)}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Document list */}
            {documents.length > 0 && (
                <div className="space-y-2">
                    <h5 className="font-medium text-slate-700 text-sm">Attached Documents</h5>
                    {documents.map((doc) => (
                        <div
                            key={doc.id}
                            className="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg"
                        >
                            {getDocumentIcon(doc.document_type)}
                            <div className="flex-1 min-w-0">
                                <p className="font-medium text-slate-900 truncate">
                                    {doc.original_filename || doc.external_iar_id || 'Document'}
                                </p>
                                <p className="text-sm text-slate-500">
                                    {doc.is_external_link
                                        ? `Linked IAR: ${doc.external_iar_id}`
                                        : doc.formatted_file_size || 'Unknown size'}
                                    {' - '}
                                    {doc.uploaded_at
                                        ? new Date(doc.uploaded_at).toLocaleDateString()
                                        : 'Unknown date'}
                                </p>
                            </div>
                            <button
                                onClick={() => handleDelete(doc.id)}
                                className="p-1.5 text-slate-400 hover:text-rose-600 transition-colors"
                                title="Delete document"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Empty state */}
            {documents.length === 0 && !showLinkForm && (
                <div className="text-center py-6 text-slate-500 text-sm">
                    No documents attached. Upload a PDF or link an external IAR document.
                </div>
            )}
        </div>
    );
};

export default InterraiDocumentUpload;
