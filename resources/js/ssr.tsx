import { createInertiaApp } from '@inertiajs/react';
import { pageTitle, resolveLayout, withApp } from './inertia-app';

void createInertiaApp({
    title: pageTitle,
    layout: resolveLayout,
    withApp,
    pages: {
        path: './pages',
        lazy: false,
    },
});
