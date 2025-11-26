import React, { useState, useEffect } from 'react';

const ReferralTimer = ({ receivedAt }) => {
    const [timeLeft, setTimeLeft] = useState(null);
    const [isBreached, setIsBreached] = useState(false);

    useEffect(() => {
        const calculateTime = () => {
            const received = new Date(receivedAt).getTime();
            const now = new Date().getTime();
            const limit = received + (15 * 60 * 1000); // 15 minutes in ms
            const diff = limit - now;

            if (diff < 0) {
                setIsBreached(true);
                setTimeLeft(Math.abs(diff)); // Time over
            } else {
                setIsBreached(false);
                setTimeLeft(diff);
            }
        };

        calculateTime();
        const timer = setInterval(calculateTime, 1000);

        return () => clearInterval(timer);
    }, [receivedAt]);

    if (timeLeft === null) return <span className="text-slate-400">Calculating...</span>;

    const minutes = Math.floor((timeLeft / 1000 / 60));
    const seconds = Math.floor((timeLeft / 1000) % 60);
    const formattedTime = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;

    if (isBreached) {
        return (
            <div className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                <svg className="mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                Breached (+{formattedTime})
            </div>
        );
    }

    return (
        <div className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 animate-pulse">
            <svg className="mr-1.5 h-2 w-2 text-emerald-400" fill="currentColor" viewBox="0 0 8 8">
                <circle cx="4" cy="4" r="3" />
            </svg>
            {formattedTime} Remaining
        </div>
    );
};

export default ReferralTimer;