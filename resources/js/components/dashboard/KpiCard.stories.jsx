import KpiCard from './KpiCard';

/**
 * KpiCard component for displaying key performance indicators.
 * Used primarily in dashboards to show metrics with status indicators.
 */
export default {
    title: 'Dashboard/KpiCard',
    component: KpiCard,
    tags: ['autodocs'],
    argTypes: {
        status: {
            control: 'select',
            options: ['neutral', 'success', 'warning', 'critical'],
            description: 'Status affects colors and indicators',
        },
        onAction: {
            action: 'clicked',
            description: 'Click handler for the card or action button',
        },
    },
    decorators: [
        (Story) => (
            <div className="w-72">
                <Story />
            </div>
        ),
    ],
};

const UserIcon = () => (
    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
);

const ClockIcon = () => (
    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

const AlertIcon = () => (
    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>
);

const CheckIcon = () => (
    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

export const Neutral = {
    args: {
        label: 'Active Patients',
        value: '247',
        icon: <UserIcon />,
        status: 'neutral',
        trend: '+12',
        trendLabel: 'this month',
    },
};

export const Success = {
    args: {
        label: 'SLA Compliance',
        value: '98.5%',
        icon: <CheckIcon />,
        status: 'success',
        trend: '+2.1%',
        trendLabel: 'vs target',
    },
};

export const Warning = {
    args: {
        label: 'Pending Triage',
        value: '8',
        icon: <ClockIcon />,
        status: 'warning',
        trend: '3 urgent',
        trendLabel: '',
        actionLabel: 'Review Now',
    },
};

export const Critical = {
    args: {
        label: 'Missed Care Events',
        value: '3',
        icon: <AlertIcon />,
        status: 'critical',
        actionLabel: 'View Details',
    },
};

export const WithAction = {
    args: {
        label: 'Upcoming Visits',
        value: '24',
        icon: <ClockIcon />,
        status: 'neutral',
        actionLabel: 'View Schedule',
    },
};

export const AllStatuses = {
    render: () => (
        <div className="grid grid-cols-2 gap-4 w-[600px]">
            <KpiCard
                label="Neutral"
                value="100"
                icon={<UserIcon />}
                status="neutral"
                trend="+5%"
                trendLabel="growth"
            />
            <KpiCard
                label="Success"
                value="98%"
                icon={<CheckIcon />}
                status="success"
                trend="On target"
            />
            <KpiCard
                label="Warning"
                value="12"
                icon={<ClockIcon />}
                status="warning"
                actionLabel="Review"
            />
            <KpiCard
                label="Critical"
                value="3"
                icon={<AlertIcon />}
                status="critical"
                actionLabel="Action Required"
            />
        </div>
    ),
};
