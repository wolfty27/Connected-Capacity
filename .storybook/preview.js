import '../resources/css/app.css';

/** @type { import('@storybook/react').Preview } */
const preview = {
    parameters: {
        actions: { argTypesRegex: '^on[A-Z].*' },
        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },
        backgrounds: {
            default: 'light',
            values: [
                { name: 'light', value: '#f8fafc' },
                { name: 'dark', value: '#1e293b' },
                { name: 'white', value: '#ffffff' },
            ],
        },
        layout: 'centered',
    },
    decorators: [
        (Story) => (
            <div className="font-sans antialiased">
                <Story />
            </div>
        ),
    ],
};

export default preview;
