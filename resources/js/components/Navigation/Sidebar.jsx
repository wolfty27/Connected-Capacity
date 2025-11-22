import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

const Sidebar = () => {
    const { user } = useAuth();

    return (
        <aside className="w-64 h-screen pt-20 transition-transform -translate-x-full bg-white border-r border-gray-200 sm:translate-x-0 fixed left-0 top-0 z-40" aria-label="Sidebar">
            <div className="h-full px-3 pb-4 overflow-y-auto bg-white">
                <ul className="space-y-2 font-medium">
                    <li>
                        <Link to="/" className="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                            <span className="ml-3">Dashboard</span>
                        </Link>
                    </li>
                    
                    {/* Care Ops Links */}
                    {['SPO_ADMIN', 'SSPO_ADMIN', 'hospital', 'retirement-home', 'admin'].includes(user?.role) && (
                        <li>
                            <Link to="/care-dashboard" className="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                                <span className="ml-3">Care Dashboard</span>
                            </Link>
                        </li>
                    )}

                    {['FIELD_STAFF', 'SPO_ADMIN'].includes(user?.role) && (
                        <li>
                            <Link to="/worklist" className="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                                <span className="ml-3">My Worklist</span>
                            </Link>
                        </li>
                    )}

                    {/* Placeholder for role-based links */}
                    {user?.role === 'hospital' && (
                        <li>
                            <span className="flex items-center p-2 text-gray-500 rounded-lg cursor-default">
                                <span className="ml-3">Patients (Legacy)</span>
                            </span>
                        </li>
                    )}
                </ul>
            </div>
        </aside>
    );
};

export default Sidebar;
