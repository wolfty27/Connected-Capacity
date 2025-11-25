import React from 'react';
import AssignmentConfigurator from './AssignmentConfigurator';

const AssignmentModal = ({ isOpen, onClose, serviceLine, onConfirm }) => {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-slate-900/50 backdrop-blur-sm p-4 md:p-0">
            <div className="relative w-full max-w-2xl max-h-full">
                <div className="relative bg-white rounded-lg shadow-xl border border-slate-200">
                    {/* Header */}
                    <div className="flex items-center justify-between p-4 md:p-5 border-b rounded-t border-slate-100">
                        <h3 className="text-xl font-semibold text-slate-900">
                            Configure Assignment
                        </h3>
                        <button 
                            onClick={onClose}
                            type="button" 
                            className="text-slate-400 bg-transparent hover:bg-slate-200 hover:text-slate-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
                        >
                            <svg className="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                            </svg>
                            <span className="sr-only">Close modal</span>
                        </button>
                    </div>
                    
                    {/* Body */}
                    <div className="p-4 md:p-5">
                        <AssignmentConfigurator 
                            serviceLine={serviceLine} 
                            onConfirm={onConfirm} 
                            onCancel={onClose} 
                        />
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AssignmentModal;