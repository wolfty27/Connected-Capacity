import React, { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import TopNav from '../components/common/TopNav';
import HeroSection from '../components/home/HeroSection';
import WorkflowSection from '../components/home/WorkflowSection';
import FeatureSection from '../components/home/FeatureSection';
import Footer from '../components/common/Footer';
import LoginModal from '../components/auth/LoginModal';

const Homepage = () => {
    const navigate = useNavigate();
    const { user } = useAuth();
    const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);

    if (user) {
        return <Navigate to="/dashboard" replace />;
    }

    const handleLoginClick = () => {
        setIsLoginModalOpen(true);
    };

    return (
        <div className="bg-white text-slate-600 antialiased overflow-x-hidden">
            <TopNav onLoginClick={handleLoginClick} />

            <HeroSection onLoginClick={handleLoginClick} />

            <WorkflowSection />

            <FeatureSection />

            <Footer />

            <LoginModal
                isOpen={isLoginModalOpen}
                onClose={() => setIsLoginModalOpen(false)}
            />
        </div>
    );
};

export default Homepage;
