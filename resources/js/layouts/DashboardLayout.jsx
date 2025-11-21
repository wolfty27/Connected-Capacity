import React from 'react';
import { Outlet, Link, useNavigate } from 'react-router-dom';
import axios from 'axios';

const DashboardLayout = () => {
    const navigate = useNavigate();

    const handleLogout = async () => {
        try {
            await axios.post('/logout');
            navigate('/login');
        } catch (error) {
            console.error('Logout failed', error);
        }
    };

    return (
        <div className="min-h-screen bg-gray-900 text-white flex">
            {/* Sidebar */}
            <aside className="w-64 bg-gray-800/50 backdrop-blur-md border-r border-gray-700 flex flex-col">
                <div className="p-6 border-b border-gray-700">
                    <h1 className="text-xl font-bold tracking-wider">CC V2</h1>
                </div>
                <nav className="flex-1 p-4 space-y-2">
                    <Link to="/" className="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-white/10 px-4 py-3 rounded-lg transition-all">
                        <span>📊</span>
                        <span>Dashboard</span>
                    </Link>
                    <Link to="/patients" className="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-white/10 px-4 py-3 rounded-lg transition-all">
                        <span>👥</span>
                        <span>Patients</span>
                    </Link>
                    <Link to="/settings" className="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-white/10 px-4 py-3 rounded-lg transition-all">
                        <span>⚙️</span>
                        <span>Settings</span>
                    </Link>
                </nav>
                <div className="p-4 border-t border-gray-700">
                    <button
                        onClick={handleLogout}
                        className="w-full text-left px-4 py-2 text-red-400 hover:bg-gray-700 rounded transition"
                    >
                        Logout
                    </button>
                </div>
            </aside>

            {/* Main Content */}
            <main className="flex-1 flex flex-col">
                <header className="h-16 bg-gray-800/30 backdrop-blur-sm border-b border-gray-700 flex items-center px-8 justify-between">
                    <h2 className="text-lg font-medium">Overview</h2>
                    <div className="flex items-center space-x-4">
                        <div className="w-8 h-8 rounded-full bg-gray-600"></div>
                    </div>
                </header>
                <div className="flex-1 p-8 overflow-auto">
                    <Outlet />
                </div>
            </main>
        </div>
    );
};

export default DashboardLayout;
