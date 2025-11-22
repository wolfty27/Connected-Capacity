import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import AppLayout from './Layout/AppLayout';
import DashboardPlaceholder from './DashboardPlaceholder';

const App = () => {
    return (
        <AuthProvider>
            <Routes>
                <Route element={<AppLayout />}>
                    <Route path="/" element={<DashboardPlaceholder />} />
                    {/* Future authenticated routes will go here */}
                </Route>
            </Routes>
        </AuthProvider>
    );
};

export default App;
