import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { setI18n, type I18n } from './lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => {
                    // The language's dictionary must be in place before the page calls t().
                    setI18n(pageProps.i18n as I18n | undefined);
                    return <Component key={key} {...pageProps} />;
                }}
            </App>,
        );
    },
    progress: {
        color: 'var(--color-primary-600)',
    },
});
