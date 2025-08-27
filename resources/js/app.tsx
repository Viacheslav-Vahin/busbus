// resources/js/app.tsx
import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import IndexPage from './Pages/IndexPage';
import SearchResultsPage from './Pages/SearchResultsPage';
import BookingPage from './Pages/BookingPage';


// const container = document.getElementById('root')!;
const root = createRoot(document.getElementById('app')!);
root.render(
    <BrowserRouter>
        <Routes>
            <Route path="/" element={<IndexPage />} />
            <Route path="/search" element={<SearchResultsPage />} />
            <Route path="/book" element={<BookingPage />} />
        </Routes>
    </BrowserRouter>
);

// import '../css/app.css';
// import React from 'react';
// import { createRoot } from 'react-dom/client';
// import IndexPage from './Pages/IndexPage';
//
// // Якщо у вас є інші сторінки — можете динамічно їх рендерити за window.location.pathname
// const container = document.getElementById('root')!;
// const root = createRoot(container);
// root.render(<IndexPage />);
