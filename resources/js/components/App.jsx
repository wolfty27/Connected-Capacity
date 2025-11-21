import React, { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import axios from 'axios';
import Login from '../pages/Login';
import DashboardLayout from '../layouts/DashboardLayout';
import Dashboard from '../pages/Dashboard';
import PatientsList from '../pages/Patients/PatientsList';

const App = () => {
    const [isAuthenticated, setIsAuthenticated] = useState(null); // null = loading

    useEffect(() => {
        const checkAuth = async () => {
            try {
                await axios.get('/api/user');
                setIsAuthenticated(true);
            } catch (err) {
                setIsAuthenticated(false);
            }
        };
        checkAuth();
    }, []);

    if (isAuthenticated === null) {
        return <div className="min-h-screen bg-gray-900 flex items-center justify-center text-white">Loading...</div>;
    }

    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={!isAuthenticated ? <Login /> : <Navigate to="/" />} />

                <Route path="/" element={isAuthenticated ? <DashboardLayout /> : <Navigate to="/login" />}>
                    <Route index element={<Dashboard />} />
                    <Route path="patients" element={<PatientsList />} />
                </Route>
            </Routes>
        </BrowserRouter>
    );
};

export default App;
