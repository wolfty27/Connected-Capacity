import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import AppLayout from './Layout/AppLayout';
import DashboardPlaceholder from './DashboardPlaceholder';
import ProtectedRoute from './RouteGuards/ProtectedRoute';
import NotFoundPage from './NotFoundPage';

import TnpReviewListPage from '../pages/Tnp/TnpReviewListPage';
import TnpReviewDetailPage from '../pages/Tnp/TnpReviewDetailPage';
import CareDashboardPage from '../pages/CareOps/CareDashboardPage';
import FieldStaffWorklistPage from '../pages/CareOps/FieldStaffWorklistPage';
import RoleRoute from './RouteGuards/RoleRoute';

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

                        {/* Role-Based Routes */}
                        <Route element={<RoleRoute roles={['SPO_ADMIN', 'SSPO_ADMIN', 'hospital', 'retirement-home', 'admin']} />}>
                             <Route path="/care-dashboard" element={<CareDashboardPage />} />
                        </Route>

                        <Route element={<RoleRoute roles={['FIELD_STAFF', 'SPO_ADMIN']} />}>
                            <Route path="/worklist" element={<FieldStaffWorklistPage />} />
                        </Route>
                        
                        {/* Catch-all for authenticated layout */}
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Routes>
        </AuthProvider>
    );
};

export default App;