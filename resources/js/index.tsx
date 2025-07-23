// resources/js/index.tsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { AppRouter } from './routes';

ReactDOM.createRoot(document.getElementById('app')!).render(
    <React.StrictMode>
        <AppRouter />
    </React.StrictMode>,
);
