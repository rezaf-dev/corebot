import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { createElement, useEffect } from 'react';
import { setLocalization } from './localization';

const localizedPages = new Map();

const appName = import.meta.env.VITE_APP_NAME || 'corebot';

createInertiaApp({
    title: (title) => (title === appName ? appName : `${title} - ${appName}`),
    resolve: async (name) => {
        if (localizedPages.has(name)) return localizedPages.get(name);

        const page = await resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        );

        function LocalizedPage(props) {
            setLocalization(props.localization);

            useEffect(() => {
                document.documentElement.lang = props.localization?.locale || 'en';
                document.documentElement.dir = props.localization?.direction || 'ltr';
            }, [props.localization?.locale, props.localization?.direction]);

            return createElement(page.default, props);
        }

        localizedPages.set(name, LocalizedPage);
        return LocalizedPage;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
