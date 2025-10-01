// // resources/js/index.tsx
// import React from 'react';
// import ReactDOM from 'react-dom/client';
// import { AppRouter } from './routes';
//
// ReactDOM.createRoot(document.getElementById('app')!).render(
//     <React.StrictMode>
//         <AppRouter />
//     </React.StrictMode>,
// );
import React from 'react';
import ReactDOM from 'react-dom/client';
import { AppRouter } from './routes';
import { Helmet } from "react-helmet-async";

export default function AppLayout({ children }) {
    return (
        <>
            <Helmet>
                <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96"/>
                <link rel="icon" type="image/svg+xml" href="/favicon.svg"/>
                <link rel="shortcut icon" href="/favicon.ico"/>
                <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png"/>
                <meta name="apple-mobile-web-app-title" content="MaxBus"/>
                <meta name="facebook-domain-verification" content="4crrzrx4soy0yj26ii4yqg13qzyyed"/>
                <link rel="manifest" href="/site.webmanifest"/>
            </Helmet>
            {children}
        </>
    );
}

function mountReact() {
    const el = document.getElementById('app');
    if (!el) return;                  // контейнер відсутній → нічого не робимо
    if ((el as any)._mounted) return; // захист від повторного монту
    (el as any)._mounted = true;

    ReactDOM.createRoot(el).render(
        <React.StrictMode>
        <AppRouter />
        </React.StrictMode>,
    );
}

document.addEventListener('DOMContentLoaded', mountReact);
document.addEventListener('livewire:navigated', mountReact);
document.addEventListener('turbo:load', mountReact);
