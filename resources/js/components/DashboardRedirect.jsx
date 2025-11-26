import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const DashboardRedirect = () => {
    const { user, loading } = useAuth();

    if (loading) {
        return <div className="p-4 text-center text-gray-500">Redirecting...</div>;
    }

    if (!user) {
        // Redirect to home page which has login modal
        return <Navigate to="/" replace />;
    }

    // Field Staff -> Worklist
    if (user.role === 'FIELD_STAFF') {
        return <Navigate to="/worklist" replace />;
    }

    // SSPO Partners -> Partner Portal
    if (user.role === 'SSPO_ADMIN' || user.role === 'SSPO_COORDINATOR') {
        return <Navigate to="/sspo/dashboard" replace />;
    }

    // All admin/coordinator roles -> Care Dashboard
    const adminRoles = [
        'admin',
        'MASTER',
        'SPO_ADMIN',
        'ORG_ADMIN',
        'SPO_COORDINATOR',
    ];

    if (adminRoles.includes(user.role)) {
        return <Navigate to="/care-dashboard" replace />;
    }

    // Default fallback -> Care Dashboard
    return <Navigate to="/care-dashboard" replace />;
};

export default DashboardRedirect;
