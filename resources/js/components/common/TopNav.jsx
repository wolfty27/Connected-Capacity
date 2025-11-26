import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const TopNav = ({ onLoginClick }) => {
    const navigate = useNavigate();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <nav className="fixed w-full z-50 glass-nav border-b border-slate-100 transition-all duration-300" id="navbar">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-20">
                    {/* Logo */}
                    <div className="flex items-center gap-3 cursor-pointer" onClick={() => navigate('/')}>
                        <div className="h-9 w-9 bg-teal-900 rounded-lg flex items-center justify-center text-teal-50 shadow-lg">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <span className="font-bold text-xl text-teal-900 tracking-tight">Connected Capacity</span>
                    </div>

                    {/* Links (Desktop) */}
                    <div className="hidden md:flex items-center space-x-8">
                        <a href="#how-it-works" className="text-sm font-medium text-slate-500 hover:text-teal-900 transition-colors">How it Works</a>
                        <a href="#features" className="text-sm font-medium text-slate-500 hover:text-teal-900 transition-colors">For SPOs</a>
                        <a href="#" className="text-sm font-medium text-slate-500 hover:text-teal-900 transition-colors">Partners</a>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-4">
                        <a href="#" className="hidden md:block text-sm font-medium text-teal-900 hover:text-teal-700">Apply to Join</a>
                        <button
                            onClick={onLoginClick}
                            className="bg-teal-900 hover:bg-teal-800 text-white px-6 py-2.5 rounded-full text-sm font-bold shadow-lg shadow-teal-900/20 transition-all transform hover:-translate-y-0.5"
                        >
                            Partner Login
                        </button>

                        {/* Mobile Menu Toggle */}
                        <button
                            className="md:hidden text-slate-500 hover:text-slate-700"
                            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {isMobileMenuOpen ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile Menu Dropdown */}
            {isMobileMenuOpen && (
                <div className="md:hidden bg-white border-t border-slate-100 px-4 py-4 shadow-xl">
                    <div className="flex flex-col space-y-4">
                        <a href="#how-it-works" className="text-sm font-medium text-slate-600 hover:text-teal-900" onClick={() => setIsMobileMenuOpen(false)}>How it Works</a>
                        <a href="#features" className="text-sm font-medium text-slate-600 hover:text-teal-900" onClick={() => setIsMobileMenuOpen(false)}>For SPOs</a>
                        <a href="#" className="text-sm font-medium text-slate-600 hover:text-teal-900" onClick={() => setIsMobileMenuOpen(false)}>Partners</a>
                        <a href="#" className="text-sm font-medium text-teal-900 font-bold" onClick={() => setIsMobileMenuOpen(false)}>Apply to Join</a>
                    </div>
                </div>
            )}
        </nav>
    );
};

export default TopNav;
