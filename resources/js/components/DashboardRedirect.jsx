import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const DashboardRedirect = () => {
    const { user, loading } = useAuth();

    if (loading) {
        return <div className="p-4 text-center text-gray-500">Redirecting...</div>;
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    // Field Staff -> Worklist
    if (user.role === 'FIELD_STAFF') {
        return <Navigate to="/worklist" replace />;
    }

    // SSPO Partners -> Partner Portal
    if (user.role === 'SSPO_ADMIN' || user.role === 'SSPO_COORDINATOR') {
        return <Navigate to="/sspo/dashboard" replace />;
    }

    // All other admin/manager roles -> Care Dashboard
    const adminRoles = [
        'admin', 
        'MASTER', 
        'SPO_ADMIN', 
        'SSPO_ADMIN', 
        'hospital', 
        'retirement-home', 
        'ORG_ADMIN', 
        'SPO_COORDINATOR', 
        'SSPO_COORDINATOR'
    ];

    if (adminRoles.includes(user.role)) {
        return <Navigate to="/care-dashboard" replace />;
    }

    // Fallback for unknown roles (e.g. 'patient' if they ever login here)
    // For now, send them to care dashboard or show a "No Dashboard" message.
    // We'll try Care Dashboard as a default safe landing for staff.
    return <Navigate to="/care-dashboard" replace />;
};

export default DashboardRedirect;
