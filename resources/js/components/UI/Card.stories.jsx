import Card from './Card';
import Button from './Button';

/**
 * Card component for containing content in a styled container.
 * Supports multiple variants for different use cases.
 */
export default {
    title: 'UI/Card',
    component: Card,
    tags: ['autodocs'],
    argTypes: {
        variant: {
            control: 'select',
            options: ['standard', 'kpi', 'interactive', 'flat'],
            description: 'Visual style variant',
        },
        title: {
            control: 'text',
            description: 'Card title',
        },
        subtitle: {
            control: 'text',
            description: 'Card subtitle',
        },
    },
};

export const Standard = {
    args: {
        title: 'Patient Overview',
        subtitle: 'Last updated 5 minutes ago',
        children: (
            <p className="text-slate-600">
                This is a standard card with a title, subtitle, and content area.
                Perfect for displaying grouped information.
            </p>
        ),
    },
};

export const KPI = {
    args: {
        variant: 'kpi',
        title: 'Active Patients',
        children: (
            <div>
                <p className="text-4xl font-bold text-slate-900">247</p>
                <p className="text-sm text-emerald-600 mt-2">+12% from last month</p>
            </div>
        ),
    },
};

export const Interactive = {
    args: {
        variant: 'interactive',
        title: 'Care Bundle',
        subtitle: 'Click to view details',
        children: (
            <div className="flex justify-between items-center">
                <span className="text-slate-600">High Intensity Home Care</span>
                <span className="text-teal-600 font-medium">Active</span>
            </div>
        ),
    },
};

export const Flat = {
    args: {
        variant: 'flat',
        children: (
            <p className="text-slate-600">
                A flat card with no border, suitable for nested content areas.
            </p>
        ),
    },
};

export const WithAction = {
    args: {
        title: 'Recent Activity',
        action: <Button variant="link">View All</Button>,
        children: (
            <ul className="space-y-2">
                <li className="text-slate-600">Patient admitted to care</li>
                <li className="text-slate-600">Assessment completed</li>
                <li className="text-slate-600">Care plan approved</li>
            </ul>
        ),
    },
};

export const NoHeader = {
    args: {
        children: (
            <div className="text-center py-8">
                <svg className="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p className="text-slate-500">No data available</p>
            </div>
        ),
    },
};

export const AllVariants = {
    render: () => (
        <div className="space-y-4 w-96">
            <Card variant="standard" title="Standard">
                Standard card variant
            </Card>
            <Card variant="kpi" title="KPI">
                <p className="text-3xl font-bold">123</p>
            </Card>
            <Card variant="interactive" title="Interactive">
                Hover to see effect
            </Card>
            <Card variant="flat">
                Flat card variant
            </Card>
        </div>
    ),
};
