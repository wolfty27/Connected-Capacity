import React from 'react';

const Dashboard = () => {
    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-gray-800/50 backdrop-blur border border-gray-700 p-6 rounded-xl shadow-lg">
                <h3 className="text-gray-400 text-sm font-medium uppercase mb-2">Total Capacity</h3>
                <p className="text-3xl font-bold">85%</p>
            </div>
            <div className="bg-gray-800/50 backdrop-blur border border-gray-700 p-6 rounded-xl shadow-lg">
                <h3 className="text-gray-400 text-sm font-medium uppercase mb-2">Active Providers</h3>
                <p className="text-3xl font-bold">124</p>
            </div>
            <div className="bg-gray-800/50 backdrop-blur border border-gray-700 p-6 rounded-xl shadow-lg">
                <h3 className="text-gray-400 text-sm font-medium uppercase mb-2">Pending Requests</h3>
                <p className="text-3xl font-bold">12</p>
            </div>

            <div className="col-span-full bg-gray-800/30 border border-gray-700 rounded-xl p-6 h-96 flex items-center justify-center text-gray-500">
                Chart Placeholder
            </div>
        </div>
    );
};

export default Dashboard;
