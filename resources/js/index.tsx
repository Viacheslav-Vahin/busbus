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
