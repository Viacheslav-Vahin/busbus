// resources/js/routes.tsx
import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import IndexPage from './pages/IndexPage';
import SearchResultsPage from './pages/SearchResultsPage';
import BookingPage from './pages/BookingPage';
import CmsPage from "./Pages/CmsPage";
import GalleryPage from "./Pages/GalleryPage";
import AdminGalleryPage from "./Pages/admin/AdminGalleryPage";

export const AppRouter = () => (
    <BrowserRouter>
        <Routes>
            <Route path="/" element={<IndexPage />} />
            <Route path="/search" element={<SearchResultsPage />} />
            <Route path="/book" element={<BookingPage />} />
            <Route path="/terms" element={<CmsPage slug="terms" />} />
            <Route path="/info" element={<CmsPage slug="info" />} />
            <Route path="/gallery" element={<GalleryPage/>} />
            <Route path="/admin/gallery" element={<AdminGalleryPage/>} />
        </Routes>
    </BrowserRouter>
);
