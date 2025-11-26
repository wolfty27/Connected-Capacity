import React from 'react';
import { useAuth } from '../../contexts/AuthContext';

import { useNavigate } from 'react-router-dom';

const GlobalNavBar = () => {
    const { user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/');
    };

    return (
        <nav className="bg-white shadow-md border-b border-gray-200 px-4 py-2.5 fixed left-0 right-0 top-0 z-50">
            <div className="flex flex-wrap justify-between items-center">
                <div className="flex justify-start items-center">
                    <span className="self-center text-xl font-semibold whitespace-nowrap text-gray-800">
                        Connected Capacity
                    </span>
                </div>
                <div className="flex items-center lg:order-2">
                    {user && (
                        <button
                            onClick={handleLogout}
                            className="text-gray-800 hover:bg-gray-100 focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-4 py-2 lg:px-5 lg:py-2.5 mr-2 focus:outline-none"
                        >
                            Logout
                        </button>
                    )}
                </div>
            </div>
        </nav>
    );
};

export default GlobalNavBar;
