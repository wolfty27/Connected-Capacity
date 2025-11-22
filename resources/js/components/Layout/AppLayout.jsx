import React from 'react';
import { Outlet } from 'react-router-dom';
import GlobalNavBar from '../Navigation/GlobalNavBar';
import Sidebar from '../Navigation/Sidebar';

const AppLayout = () => {
    return (
        <div className="antialiased bg-gray-50">
            <GlobalNavBar />
            <Sidebar />
            <main className="p-4 sm:ml-64 h-auto pt-20">
                <Outlet />
            </main>
        </div>
    );
};

export default AppLayout;
