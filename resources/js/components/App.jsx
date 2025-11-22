import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import AppLayout from './Layout/AppLayout';
import DashboardPlaceholder from './DashboardPlaceholder';
import ProtectedRoute from './RouteGuards/ProtectedRoute';
import NotFoundPage from './NotFoundPage';

import TnpReviewListPage from '../pages/Tnp/TnpReviewListPage';
import TnpReviewDetailPage from '../pages/Tnp/TnpReviewDetailPage';

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
                        
                        <Route path="/tnp" element={<TnpReviewListPage />} />
                        <Route path="/tnp/:patientId" element={<TnpReviewDetailPage />} />
                        
                        {/* Catch-all for authenticated layout */}
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Routes>
        </AuthProvider>
    );
};

export default App;