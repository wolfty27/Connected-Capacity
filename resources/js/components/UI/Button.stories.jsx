import Button from './Button';

/**
 * Button component for user interactions.
 * Supports multiple variants for different contexts.
 */
export default {
    title: 'UI/Button',
    component: Button,
    tags: ['autodocs'],
    argTypes: {
        variant: {
            control: 'select',
            options: ['primary', 'secondary', 'danger', 'link'],
            description: 'Visual style variant',
        },
        disabled: {
            control: 'boolean',
            description: 'Disable the button',
        },
        onClick: {
            action: 'clicked',
            description: 'Click handler',
        },
    },
};

export const Primary = {
    args: {
        children: 'Primary Button',
        variant: 'primary',
    },
};

export const Secondary = {
    args: {
        children: 'Secondary Button',
        variant: 'secondary',
    },
};

export const Danger = {
    args: {
        children: 'Delete',
        variant: 'danger',
    },
};

export const Link = {
    args: {
        children: 'Learn More',
        variant: 'link',
    },
};

export const Disabled = {
    args: {
        children: 'Disabled Button',
        variant: 'primary',
        disabled: true,
    },
};

export const WithIcon = {
    args: {
        children: (
            <>
                <svg className="w-4 h-4 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                Add New
            </>
        ),
        variant: 'primary',
    },
};

export const AllVariants = {
    render: () => (
        <div className="flex flex-wrap gap-4">
            <Button variant="primary">Primary</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="danger">Danger</Button>
            <Button variant="link">Link</Button>
        </div>
    ),
};
