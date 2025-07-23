// resources/js/routes.tsx
import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import IndexPage from './pages/IndexPage';
import SearchResultsPage from './pages/SearchResultsPage';
import BookingPage from './pages/BookingPage';

export const AppRouter = () => (
    <BrowserRouter>
        <Routes>
            <Route path="/" element={<IndexPage />} />
            <Route path="/search" element={<SearchResultsPage />} />
            <Route path="/book" element={<BookingPage />} />
        </Routes>
    </BrowserRouter>
);
