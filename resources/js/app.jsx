import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './components/App';

console.log('Connected Capacity: JS Entry Point Loaded');

// Global error handler to catch errors before React mounts
window.onerror = function (message, source, lineno, colno, error) {
    console.error("Global Error Caught:", message);
    const errorDiv = document.getElementById('app');
    if (errorDiv) {
        errorDiv.innerHTML = `
            <div style="color: red; padding: 20px; font-family: monospace;">
                <h3>Application Error</h3>
                <p>${message}</p>
                <p>Source: ${source}:${lineno}:${colno}</p>
                <pre>${error ? error.stack : ''}</pre>
            </div>
        `;
    }
};

const container = document.getElementById('app');
console.log('Container found:', container);

if (container) {
    try {
        console.log('Creating root...');
        const root = createRoot(container);
        console.log('Rendering app...');
        root.render(
            <BrowserRouter>
                <App />
            </BrowserRouter>
        );
        console.log('Render called.');
    } catch (e) {
        console.error("React Mount Error:", e);
        container.innerHTML = `<div style="color: red; padding: 20px;"><h3>React Mount Error</h3><pre>${e.message}\n${e.stack}</pre></div>`;
    }
} else {
    console.error('Target container "app" not found!');
}