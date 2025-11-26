import React, { createContext, useState, useEffect, useContext } from 'react';
import axios from 'axios';
import api from '../services/api';

const AuthContext = createContext(null);

// Separate axios instance for auth routes (no /api prefix)
const authApi = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
});

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchUser = async () => {
            try {
                const response = await api.get('/user');
                setUser(response.data);
            } catch (error) {
                // If 401, user is not authenticated, which is expected for guests
                if (error.response && error.response.status === 401) {
                    setUser(null);
                } else {
                    console.error('Failed to fetch user:', error);
                }
            } finally {
                setLoading(false);
            }
        };

        fetchUser();
    }, []);

    const login = async (credentials) => {
        // Get CSRF cookie first (at root, not under /api)
        await authApi.get('/sanctum/csrf-cookie');
        // Login is at root
        await authApi.post('/login', credentials);
        // Fetch user from API
        const response = await api.get('/user');
        setUser(response.data);
    };

    const logout = async () => {
        await authApi.post('/logout');
        setUser(null);
    };

    return (
        <AuthContext.Provider value={{ user, loading, login, logout }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);
