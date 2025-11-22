import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import DashboardPlaceholder from './DashboardPlaceholder';

const App = () => {
    return (
        <AuthProvider>
            <div className="min-h-screen bg-gray-100">
                <Routes>
                    <Route path="/" element={<DashboardPlaceholder />} />
                    {/* Future routes will be added here */}
                </Routes>
            </div>
        </AuthProvider>
    );
};

export default App;