import React from 'react';
import { useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

const TopBar = ({ onMenuClick }) => {
    const location = useLocation();
    const { user } = useAuth();

    // Simple breadcrumb logic based on path
    const getPageTitle = () => {
        const path = location.pathname;
        if (path === '/' || path === '/care-dashboard') return 'Care Operations Dashboard';
        if (path.includes('/patients')) return 'Patient Management';
        if (path.includes('/tnp')) return 'Transition Needs';
        if (path.includes('/referrals')) return 'Referral Intake';
        if (path.includes('/settings')) return 'System Settings';
        if (path.includes('/worklist')) return 'My Worklist';
        return 'Overview';
    };

    return (
        <header className="h-16 glass-nav sticky top-0 z-20 px-4 md:px-8 flex items-center justify-between">
            <div className="flex items-center gap-4">
                <button
                    onClick={onMenuClick}
                    className="md:hidden text-slate-500 hover:text-slate-700 focus:outline-none"
                >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div className="flex items-center gap-2">
                    <h2 className="text-lg font-bold text-slate-800 tracking-tight truncate max-w-[200px] md:max-w-none">{getPageTitle()}</h2>
                    <span className="hidden md:inline-block px-2 py-0.5 rounded bg-teal-50 text-xs font-bold text-teal-700 uppercase tracking-wider border border-teal-100">
                        {user?.role?.replace('_', ' ') || 'Beta'}
                    </span>
                </div>
            </div>

            <div className="flex items-center space-x-6">
                {/* Global Search Placeholder */}
                <div className="relative hidden md:block">
                    <input
                        type="text"
                        placeholder="Search patients..."
                        className="pl-9 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent w-64 transition-all"
                    />
                    <svg className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                {/* Notifications */}
                <button className="relative text-slate-400 hover:text-teal-600 transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span className="absolute -top-1 -right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                </button>

                {/* User Profile */}
                <div className="flex items-center gap-3 pl-6 border-l border-slate-200">
                    <div className="text-right hidden md:block">
                        <p className="text-sm font-bold text-slate-700">{user?.name || 'User'}</p>
                        <p className="text-xs text-slate-500">{user?.email}</p>
                    </div>
                    <div className="h-9 w-9 rounded-full bg-gradient-to-br from-teal-500 to-teal-700 text-white flex items-center justify-center font-bold text-sm shadow-sm ring-2 ring-white">
                        {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                    </div>
                </div>
            </div>
        </header>
    );
};

export default TopBar;
