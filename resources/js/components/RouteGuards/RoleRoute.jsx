import React from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { Outlet, Navigate } from 'react-router-dom';

const RoleRoute = ({ roles }) => {
    const { user, loading } = useAuth();
    console.log('RoleRoute Check: ' + JSON.stringify({ user, roles, allowed: user && roles.includes(user.role) }));

    if (loading) {
        return <div>Loading...</div>;
    }

    if (!user) {
        return null; // ProtectedRoute handles redirect
    }

    if (!roles.includes(user.role)) {
        return (
            <div className="p-6 text-center">
                <h1 className="text-2xl font-bold text-red-600">403 Unauthorized</h1>
                <p className="mt-2">You do not have permission to access this page.</p>
                <p className="mt-2 text-sm text-gray-500">Current Role: {user ? user.role : 'None'}</p>
            </div>
        );
    }

    return <Outlet />;
};

export default RoleRoute;
