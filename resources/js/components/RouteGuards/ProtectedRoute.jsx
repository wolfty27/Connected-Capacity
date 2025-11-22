import React from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { Outlet } from 'react-router-dom';

const ProtectedRoute = () => {
    const { user, loading } = useAuth();

    if (loading) {
        return (
            <div className="flex justify-center items-center h-screen">
                <div className="text-xl text-gray-600">Loading...</div>
            </div>
        );
    }

    if (!user) {
        // Redirect to Laravel's Blade login page
        window.location.href = '/login';
        return null;
    }

    return <Outlet />;
};

export default ProtectedRoute;
