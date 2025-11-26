import React from 'react';
import FloatingCard from './FloatingCard';

const HeroSection = ({ onLoginClick }) => {
    return (
        <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            {/* Background Blobs */}
            <div className="hero-blob bg-teal-200 w-96 h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
            <div className="hero-blob bg-indigo-200 w-[500px] h-[500px] rounded-full bottom-0 right-0 translate-x-1/3 translate-y-1/3"></div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

                    {/* Left Content */}
                    <div className="max-w-2xl animate-fade-in-up">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 border border-teal-100 text-teal-800 text-xs font-bold uppercase tracking-wider mb-6">
                            <span className="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                            Now Live in Ontario West
                        </div>
                        <h1 className="text-4xl lg:text-6xl font-extrabold text-slate-900 leading-tight mb-6">
                            Orchestrating <br />
                            <span className="text-teal-900 relative">
                                High-Intensity Care
                                <svg className="absolute w-full h-3 -bottom-1 left-0 text-teal-200 -z-10" viewBox="0 0 200 9" fill="currentColor"><path d="M2.00023 6.75C2.00023 6.75 113.001 1.75 200 6.75" stroke="currentColor" strokeWidth="3" strokeLinecap="round" /></svg>
                            </span>
                            at Home.
                        </h1>
                        <p className="text-lg text-slate-600 mb-8 leading-relaxed">
                            Seamlessly connect Hospitals, SPOs, and specialized partners to deliver hospital-level care in the community. Zero missed care. 24/7 visibility.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4">
                            <button
                                onClick={onLoginClick}
                                className="px-8 py-4 bg-teal-900 text-white rounded-xl font-bold text-lg shadow-xl shadow-teal-900/20 hover:bg-teal-800 transition-all transform hover:-translate-y-1"
                            >
                                Get Started
                            </button>
                            <button className="px-8 py-4 bg-white text-slate-700 border border-slate-200 rounded-xl font-bold text-lg hover:bg-slate-50 hover:border-slate-300 transition-all flex items-center justify-center gap-2">
                                <svg className="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Watch Demo
                            </button>
                        </div>

                        <div className="mt-10 flex items-center gap-4 text-sm text-slate-500">
                            <span>Trusted by:</span>
                            <div className="flex items-center gap-4 grayscale opacity-60">
                                <span className="font-bold text-slate-800 text-lg">Ontario Health</span>
                                <span className="font-bold text-slate-800 text-lg">UHN</span>
                                <span className="font-bold text-slate-800 text-lg">SE Health</span>
                            </div>
                        </div>
                    </div>

                    {/* Right Visual (Abstract UI Composition) */}
                    <div className="relative hidden lg:block h-[600px]">
                        {/* Background Circle */}
                        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-gradient-to-br from-teal-50 to-indigo-50 rounded-full border border-white shadow-2xl"></div>

                        {/* Floating Cards Animation */}
                        <FloatingCard className="top-20 left-10 w-64" delay="0s">
                            <div className="flex items-center gap-3 mb-3">
                                <div className="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold">JD</div>
                                <div>
                                    <div className="h-2 w-24 bg-slate-200 rounded full mb-1"></div>
                                    <div className="h-2 w-16 bg-slate-100 rounded full"></div>
                                </div>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-xs font-bold text-rose-500 bg-rose-50 px-2 py-1 rounded">High Priority</span>
                                <span className="text-xs text-slate-400">Just now</span>
                            </div>
                        </FloatingCard>

                        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white p-6 rounded-2xl shadow-2xl border border-slate-100 w-80 z-10">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="font-bold text-slate-800">Weekly Capacity</h3>
                                <span className="text-teal-600 text-xs font-bold bg-teal-50 px-2 py-1 rounded">+12%</span>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <div className="flex justify-between text-xs mb-1">
                                        <span className="text-slate-500">Nursing (RN)</span>
                                        <span className="text-slate-800 font-bold">85%</span>
                                    </div>
                                    <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden"><div className="bg-teal-500 h-full w-[85%]"></div></div>
                                </div>
                                <div>
                                    <div className="flex justify-between text-xs mb-1">
                                        <span className="text-slate-500">Personal Support</span>
                                        <span className="text-slate-800 font-bold">62%</span>
                                    </div>
                                    <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden"><div className="bg-indigo-500 h-full w-[62%]"></div></div>
                                </div>
                            </div>
                            <button className="w-full mt-6 bg-slate-900 text-white text-sm font-bold py-3 rounded-lg hover:bg-slate-800 transition-colors">Assign Resources</button>
                        </div>

                        <FloatingCard className="bottom-20 right-10 w-64" delay="1.5s">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <div>
                                    <div className="text-sm font-bold text-slate-800">Referral Accepted</div>
                                    <div className="text-xs text-slate-500">Reconnect Health</div>
                                </div>
                            </div>
                        </FloatingCard>
                    </div>
                </div>
            </div>
        </section>
    );
};

export default HeroSection;
