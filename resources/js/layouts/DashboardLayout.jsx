import React, { useState, useEffect } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from '../components/Navigation/Sidebar';
import TopBar from '../components/Navigation/TopBar';

const DashboardLayout = () => {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    // Close sidebar on route change
    useEffect(() => {
        setIsSidebarOpen(false);
    }, [location.pathname]);

    // Close sidebar on escape key
    useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                setIsSidebarOpen(false);
            }
        };
        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, []);

    return (
        <div className="min-h-screen bg-slate-50 text-slate-600 flex flex-col font-sans">
            {/* Top Navigation - Always visible */}
            <TopBar 
                onMenuClick={() => setIsSidebarOpen(!isSidebarOpen)} 
                isSidebarOpen={isSidebarOpen}
            />

            {/* Main Content - Full width now */}
            <main className="flex-1 p-4 md:p-8 overflow-auto">
                <div className="max-w-7xl mx-auto">
                    <Outlet />
                </div>
            </main>

            {/* Sidebar Drawer - Overlay style */}
            <Sidebar 
                isOpen={isSidebarOpen} 
                setIsOpen={setIsSidebarOpen} 
            />

            {/* Backdrop Overlay */}
            <div
                className={`fixed inset-0 bg-slate-900/50 z-40 backdrop-blur-sm transition-opacity duration-300 ${
                    isSidebarOpen 
                        ? 'opacity-100 pointer-events-auto' 
                        : 'opacity-0 pointer-events-none'
                }`}
                onClick={() => setIsSidebarOpen(false)}
                aria-hidden="true"
            />
        </div>
    );
};

export default DashboardLayout;
