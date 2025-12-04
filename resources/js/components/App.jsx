import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import AppLayout from './Layout/AppLayout';
import DashboardRedirect from './DashboardRedirect';
import ProtectedRoute from './RouteGuards/ProtectedRoute';
import NotFoundPage from './NotFoundPage';
import ErrorBoundary from './ErrorBoundary';
import ServerErrorPage from './ServerErrorPage';

import TnpReviewListPage from '../pages/Tnp/TnpReviewListPage';
import TnpReviewDetailPage from '../pages/Tnp/TnpReviewDetailPage';
import CareDashboardPage from '../pages/CareOps/CareDashboardPage';
import TfsDetailPage from '../pages/Metrics/TfsDetailPage';
import FieldStaffWorklistPage from '../pages/CareOps/FieldStaffWorklistPage';
import RoleRoute from './RouteGuards/RoleRoute';
import PatientsList from '../pages/Patients/PatientsList';
import PatientDetailPage from '../pages/Patients/PatientDetailPage';
import AddPatientPage from '../pages/Patients/AddPatientPage';
import ProfilePage from '../pages/Organization/ProfilePage';
import CareAssignmentDetailPage from '../pages/CareOps/CareAssignmentDetailPage';

import CreateReferral from '../pages/Referrals/CreateReferral';
import Homepage from '../pages/Homepage';
import UnderConstruction from './UnderConstruction';
import CareBundleWizard from '../pages/CarePlanning/CareBundleWizard';
import CarePlanListPage from '../pages/CarePlanning/CarePlanListPage';
import QinManagerPage from '../pages/Compliance/QinManagerPage';
import QipFormPage from '../pages/Compliance/QipFormPage';
import WeeklyHuddlePage from '../pages/CareOps/WeeklyHuddlePage';
import SpoStaffPage from '../pages/CareOps/SpoStaffPage';
import StaffProfilePage from '../pages/Staff/StaffProfilePage';
import WorkforceManagementPage from '../pages/CareOps/WorkforceManagementPage';
import WorkforceCapacityPage from '../pages/CareOps/WorkforceCapacityPage';
import SspoMarketplacePage from '../pages/CarePlanning/SspoMarketplacePage';
import SspoProfilePage from '../pages/CarePlanning/SspoProfilePage';
import ShadowBillingPage from '../pages/Finance/ShadowBillingPage';
import SuppliesPage from '../pages/Logistics/SuppliesPage';
import SspoDashboardPage from '../pages/SSPO/SspoDashboardPage';
import SspoWorkforcePage from '../pages/SSPO/SspoWorkforcePage';
import SspoCapabilityPage from '../pages/CareOps/SspoCapabilityPage';
import SchedulingPage from '../pages/CareOps/SchedulingPage';
import { SchedulingShell } from '../pages/CareOps/scheduler';
import InterraiCompletionWizard from '../pages/InterRAI/InterraiCompletionWizard';
import InterraiDashboardPage from '../pages/InterRAI/InterraiDashboardPage';
import InterraiAssessmentForm from '../pages/InterRAI/InterraiAssessmentForm';
import ServiceRatesPage from '../pages/Admin/ServiceRatesPage';

import { useAuth } from '../contexts/AuthContext';

const AppRoutes = () => {
    const { loading } = useAuth();

    if (loading) {
        return (
            <div className="flex items-center justify-center h-screen bg-slate-50">
                <div className="flex flex-col items-center">
                    <div className="w-10 h-10 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin mb-4"></div>
                    <p className="text-slate-500 font-medium">Initializing Connected Capacity...</p>
                </div>
            </div>
        );
    }

    return (
        <Routes>
            {/* Public Routes */}
            <Route path="/" element={<Homepage />} />

            {/* Error Pages */}
            <Route path="/server-error" element={<ServerErrorPage />} />
            <Route path="/not-found" element={<NotFoundPage />} />

            {/* Protected Routes */}
            <Route element={<ProtectedRoute />}>
                <Route element={<AppLayout />}>
                    <Route path="/dashboard" element={<DashboardRedirect />} />

                    { /* TNP page disabled */ }
                    { /* TNP detail disabled */ }

                    {/* Role-Based Routes */}
                    <Route element={<RoleRoute roles={[
                        'SPO_ADMIN',
                        'SSPO_ADMIN',
                        'admin',
                        'MASTER',
                        'ORG_ADMIN',
                        'SPO_COORDINATOR',
                        'SSPO_COORDINATOR'
                    ]} />}>
                        <Route path="/care-dashboard" element={<CareDashboardPage />} />
                        <Route path="/metrics/tfs" element={<TfsDetailPage />} />
                        <Route path="/patients" element={<PatientsList />} />
                        <Route path="/patients/add" element={<AddPatientPage />} />
                        <Route path="/patients/:id" element={<PatientDetailPage />} />
                        <Route path="/referrals/create" element={<CreateReferral />} />
                        <Route path="/organization/profile" element={<ProfilePage />} />
                        <Route path="/assignments/:id" element={<CareAssignmentDetailPage />} />
                        <Route path="/sspo/dashboard" element={<SspoDashboardPage />} />
                        <Route path="/sspo/workforce" element={<SspoWorkforcePage />} />

                        {/* Care Bundle Routes - redirect list to patients, keep wizard */}
                        <Route path="/care-bundles" element={<Navigate to="/patients" replace />} />
                        <Route path="/care-bundles/create/:patientId" element={<CareBundleWizard />} />
                        <Route path="/workforce" element={<WorkforceManagementPage />} />
                        <Route path="/workforce/capacity" element={<WorkforceCapacityPage />} />
                        <Route path="/staff/:id" element={<StaffProfilePage />} />
                        <Route path="/sspo-marketplace" element={<SspoMarketplacePage />} />
                        <Route path="/sspo-marketplace/:id" element={<SspoProfilePage />} />
                        <Route path="/sspo-capabilities" element={<SspoCapabilityPage />} />
                        <Route path="/weekly-huddle" element={<WeeklyHuddlePage />} />
                        {/* Scheduler 2.0 - AI-First Control Center */}
                        <Route path="/spo/scheduling" element={<SchedulingShell isSspoMode={false} />} />
                        <Route path="/sspo/scheduling" element={<SchedulingShell isSspoMode={true} />} />
                        <Route path="/scheduling" element={<SchedulingShell isSspoMode={false} />} />
                        {/* Legacy route for backwards compatibility */}
                        <Route path="/scheduling-legacy" element={<SchedulingPage isSspoMode={false} />} />

                        {/* Compliance & Reporting */}
                        <Route path="/qin" element={<QinManagerPage />} />
                        <Route path="/qip/create/:qinId" element={<QipFormPage />} />
                        <Route path="/qip" element={<UnderConstruction title="QIP Manager" />} />
                        <Route path="/billing" element={<ShadowBillingPage />} />
                        <Route path="/supplies" element={<SuppliesPage />} />

                        {/* InterRAI Assessment Routes (IR-006) */}
                        <Route path="/interrai/complete/:patientId" element={<InterraiCompletionWizard />} />
                        <Route path="/interrai/assess/:patientId" element={<InterraiAssessmentForm />} />
                        <Route path="/interrai/assess/:patientId/:assessmentId" element={<InterraiAssessmentForm />} />
                        <Route path="/interrai/dashboard" element={<InterraiDashboardPage />} />
                        <Route path="/admin/assessments" element={<InterraiDashboardPage />} />

                        {/* Service Rate Card Admin (SPO/SSPO Admin) */}
                        <Route path="/admin/service-rates" element={<ServiceRatesPage />} />
                    </Route>

                    <Route element={<RoleRoute roles={['FIELD_STAFF', 'SPO_ADMIN']} />}>
                        <Route path="/worklist" element={<FieldStaffWorklistPage />} />
                        <Route path="/assignments/:id" element={<CareAssignmentDetailPage />} />
                    </Route>

                    {/* Catch-all for authenticated layout */}
                    <Route path="*" element={<NotFoundPage />} />
                </Route>
            </Route>
        </Routes>
    );
};

const App = () => {
    return (
        <ErrorBoundary>
            <AuthProvider>
                <AppRoutes />
            </AuthProvider>
        </ErrorBoundary>
    );
};

export default App;