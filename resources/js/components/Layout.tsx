// resources/js/components/Layout.tsx
import React from 'react';
import Header from './Header';
import Footer from './Footer';

const Layout: React.FC<React.PropsWithChildren> = ({children}) => {
    return (
        <div className="flex flex-col min-h-screen bg-gray-50 text-gray-900 relative">
            <Header />
            <main className="flex-1">{children}</main>
            <Footer />
        </div>
    );
};

export default Layout;
