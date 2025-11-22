import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import AppLayout from './Layout/AppLayout';
import DashboardPlaceholder from './DashboardPlaceholder';
import ProtectedRoute from './RouteGuards/ProtectedRoute';
import NotFoundPage from './NotFoundPage';

const App = () => {
    return (
        <AuthProvider>
            <Routes>
                {/* Public Routes (if any) go here */}

                {/* Protected Routes */}
                <Route element={<ProtectedRoute />}>
                    <Route element={<AppLayout />}>
                        <Route path="/" element={<DashboardPlaceholder />} />
                        <Route path="/dashboard" element={<DashboardPlaceholder />} />
                        {/* Add more authenticated routes here */}
                        
                        {/* Catch-all for authenticated layout */}
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Routes>
        </AuthProvider>
    );
};

export default App;