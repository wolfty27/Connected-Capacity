/** @type { import('@storybook/react-vite').StorybookConfig } */
const config = {
    stories: [
        '../resources/js/**/*.mdx',
        '../resources/js/**/*.stories.@(js|jsx|ts|tsx)',
    ],
    addons: [
        '@storybook/addon-links',
        '@storybook/addon-essentials',
        '@storybook/addon-interactions',
        '@storybook/addon-a11y',
    ],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
    docs: {
        autodocs: 'tag',
    },
    viteFinal: async (config) => {
        // Customize Vite config for Storybook
        return {
            ...config,
            resolve: {
                ...config.resolve,
                alias: {
                    ...config.resolve?.alias,
                    '@': '/resources/js',
                },
            },
        };
    },
};

export default config;
