import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import axios from 'axios';
import { useAuth } from '../../contexts/AuthContext';

const Sidebar = ({ isOpen, setIsOpen }) => {
    const { user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = async () => {
        try {
            await logout();
            navigate('/');
        } catch (error) {
            console.error('Logout failed', error);
        }
    };

    const linkClasses = ({ isActive }) =>
        `flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-all group ${isActive
            ? 'bg-teal-50 text-teal-900 font-semibold'
            : 'text-slate-600 hover:bg-slate-50 hover:text-teal-900'
        }`;

    const iconClasses = ({ isActive }) =>
        `w-5 h-5 transition-colors ${isActive ? 'text-teal-600' : 'text-slate-400 group-hover:text-teal-600'}`;

    // Admin/Manager Roles
    const isAdmin = ['SPO_ADMIN', 'SSPO_ADMIN', 'ORG_ADMIN', 'SPO_COORDINATOR', 'MASTER', 'admin'].includes(user?.role);
    // Field Staff Roles
    const isStaff = ['FIELD_STAFF', 'SPO_ADMIN'].includes(user?.role);

    return (
        <aside className={`
            fixed top-0 left-0 z-30 w-64 h-full transition-transform duration-300 bg-white border-r border-slate-200 flex flex-col
            ${isOpen ? 'translate-x-0' : '-translate-x-full'} 
            md:translate-x-0
        `}>
            {/* Logo Area */}
            <div className="h-16 flex items-center justify-between px-6 border-b border-slate-100">
                <div className="flex items-center gap-2">
                    <div className="h-8 w-8 bg-teal-900 rounded-lg flex items-center justify-center text-white shadow-sm">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                    <span className="font-bold text-lg tracking-tight text-teal-900">Connected Capacity</span>
                </div>
                <button
                    onClick={() => setIsOpen(false)}
                    className="md:hidden text-slate-400 hover:text-slate-600"
                >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div className="flex-1 px-4 py-6 overflow-y-auto space-y-8">
                
                {/* --- CARE OPERATIONS --- */}
                {isAdmin && (
                    <div>
                        <div className="px-2 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Care Operations</div>
                        <div className="space-y-1">
                            <NavLink to="/care-dashboard" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                        <span>Dashboard</span>
                                    </>
                                )}
                            </NavLink>
                            
                            {/* Intake Group */}
                            <div className="pt-2 pb-1 px-2 text-[10px] font-bold text-slate-400 uppercase">Intake & Planning</div>
                            <NavLink to="/tnp" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                        <span>Transition Reviews</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/care-bundles" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                        <span>Care Bundles / CDP</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/patients" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        <span>Active Patients</span>
                                    </>
                                )}
                            </NavLink>

                             {/* Network & Staff */}
                            <div className="pt-2 pb-1 px-2 text-[10px] font-bold text-slate-400 uppercase">Network</div>
                            <NavLink to="/staff" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        <span>SPO Staff</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/sspo-marketplace" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>SSPO Marketplace</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/weekly-huddle" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                                        <span>Weekly Huddle</span>
                                    </>
                                )}
                            </NavLink>
                        </div>
                    </div>
                )}

                {/* --- REPORTING & COMPLIANCE --- */}
                {isAdmin && (
                    <div>
                        <div className="px-2 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Reporting & Compliance</div>
                        <div className="space-y-1">
                            <NavLink to="/qin" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        <span>QIN Manager</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/qip" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>QIP Manager</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/billing" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>Shadow Billing</span>
                                    </>
                                )}
                            </NavLink>
                            <NavLink to="/supplies" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                        <span>Supplies & Eq.</span>
                                    </>
                                )}
                            </NavLink>
                        </div>
                    </div>
                )}

                {/* --- MY WORK (Field Staff) --- */}
                {isStaff && (
                    <div>
                        <div className="px-2 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Field Operations</div>
                        <div className="space-y-1">
                            <NavLink to="/worklist" className={linkClasses}>
                                {({ isActive }) => (
                                    <>
                                        <svg className={iconClasses({ isActive })} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                        <span>My Worklist</span>
                                    </>
                                )}
                            </NavLink>
                        </div>
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="p-4 border-t border-slate-100 bg-slate-50/50">
                <button
                    onClick={handleLogout}
                    className="w-full flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Sign Out
                </button>
            </div>
        </aside>
    );
};

export default Sidebar;
