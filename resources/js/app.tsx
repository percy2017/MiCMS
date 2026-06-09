import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ChatBotWidget } from '@/components/chatbot/ChatBotWidget';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { appName } from '@/lib/app-name';
import '../css/puck-dark.css';
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});

const modulePages = import.meta.glob('/Modules/*/resources/js/Pages/**/*.{tsx,jsx}');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const parts = name.split('::');
        if (parts.length === 2) {
            const [moduleName, pageName] = parts;
            const modulePage = `/Modules/${moduleName}/resources/js/Pages/${pageName}.tsx`;
            if (modulePages[modulePage]) {
                return modulePages[modulePage]();
            }
        }

        return resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx'));
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name === 'paginas/show':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
                <ChatBotWidget />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();