// resources/js/components/Header.tsx
import React, {useEffect, useState} from 'react';

const smoothScrollToId = (id: string) => {
    const el = document.querySelector(id);
    if (!el) return;
    const y = (el as HTMLElement).getBoundingClientRect().top + window.scrollY - 12;
    window.scrollTo({ top: y, behavior: 'smooth' });
};

export const Header: React.FC = () => {
    const [mobileOpen, setMobileOpen] = useState(false);

    // esc to close
    useEffect(() => {
        const onEsc = (e: KeyboardEvent) => e.key === 'Escape' && setMobileOpen(false);
        window.addEventListener('keydown', onEsc);
        return () => window.removeEventListener('keydown', onEsc);
    }, []);

    // lock body scroll when open
    useEffect(() => {
        document.body.classList.toggle('mobile-nav-open', mobileOpen);
        document.body.style.overflow = mobileOpen ? 'hidden' : '';
    }, [mobileOpen]);

    const handleAnchor = (e: React.MouseEvent<HTMLAnchorElement>, hash: string) => {
        e.preventDefault();
        setMobileOpen(false);
        smoothScrollToId(hash);
    };

    return (
        <nav className="bg-white text-white shadow-md relative z-40">
            <div className="container mx-auto px-6 py-4 flex justify-between items-center">
                <a href="/" className="header-logo" onClick={(e) => {
                    e.preventDefault();
                    window.location.href = '/';
                }}>
                    <img src="../../images/Asset-21.svg" alt=""/>
                </a>

                {/* Desktop */}
                <ul className="nav-links hidden md:flex gap-8 items-center">
                    <li><a href="#booking-form" onClick={(e) => handleAnchor(e, '#booking-form')}
                           className="hover:text-brand-light transition">Головна</a></li>
                    <li><a href="#benefits" onClick={(e) => handleAnchor(e, '#benefits')}
                           className="hover:text-brand-light transition">Переваги</a></li>
                    <li><a href="#popular" onClick={(e) => handleAnchor(e, '#popular')}
                           className="hover:text-brand-light transition">Напрямки</a></li>
                    <li><a href="#faq" onClick={(e) => handleAnchor(e, '#faq')}
                           className="hover:text-brand-light transition">FAQ</a></li>
                </ul>

                {/* Burger */}
                <button
                    aria-label="Відкрити меню"
                    aria-expanded={mobileOpen}
                    className="burger md:hidden flex h-11 w-11 rounded-lg border border-gray-200 text-gray-800 items-center justify-center"
                    onClick={() => setMobileOpen(true)}
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 6h18M3 12h18M3 18h18"/>
                    </svg>
                </button>
            </div>

            {/* Backdrop + panel */}
            <div className={`mobile-nav-backdrop md:hidden ${mobileOpen ? 'opacity-100 pointer-events-auto' : ''}`}
                 onClick={() => setMobileOpen(false)}/>
            <aside className={`mobile-nav-panel md:hidden ${mobileOpen ? 'translate-x-0' : ''}`}>
                <div className="flex items-center justify-between p-4 border-b">
                    <span className="font-semibold text-gray-800">Меню</span>
                    <button aria-label="Закрити меню" className="h-10 w-10 grid place-items-center"
                            onClick={() => setMobileOpen(false)}>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M6 6l12 12M18 6l-12 12"/>
                        </svg>
                    </button>
                </div>
                <nav className="flex-1 overflow-y-auto">
                    <a href="#booking-form" className="link" onClick={(e) => handleAnchor(e, '#booking-form')}>Головна</a>
                    <a href="#benefits" className="link" onClick={(e) => handleAnchor(e, '#benefits')}>Переваги</a>
                    <a href="#popular" className="link" onClick={(e) => handleAnchor(e, '#popular')}>Напрямки</a>
                    <a href="#faq" className="link" onClick={(e) => handleAnchor(e, '#faq')}>FAQ</a>
                    <a href="tel:+380930510795" className="link">+38093&nbsp;051&nbsp;0795</a>
                    <a href="mailto:info@maxbus.com" className="link">info@maxbus.com</a>
                </nav>
            </aside>
        </nav>
    );
};

export default Header;
