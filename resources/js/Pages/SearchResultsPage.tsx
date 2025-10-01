import React, {useEffect, useState} from 'react';
import axios from 'axios';
import {useLocation, Link} from 'react-router-dom';
import queryString from 'query-string';

const smoothScrollToId = (id: string) => {
    const el = document.querySelector(id);
    if (!el) return;
    const y = (el as HTMLElement).getBoundingClientRect().top + window.scrollY - 12;
    window.scrollTo({ top: y, behavior: 'smooth' });
};

interface Trip {
    trip_id: number | null;
    bus_id: number;
    bus_name: string;
    start_location: string;
    end_location: string;
    departure_time: string;
    arrival_time: string;
    price: number;
    free_seats: number;
}

const SearchResultsPage: React.FC = () => {
    const [mobileOpen, setMobileOpen] = useState(false);
    const { search } = useLocation();
    const { routeId, date } = queryString.parse(search);
    const [trips, setTrips] = useState<Trip[]>([]);
    const [loading, setLoading] = useState(true);

    const dateStr = String(date ?? '');
    const routeIdNum = Number(routeId);

    // 1) Завантаження рейсів
    useEffect(() => {
        if (!routeIdNum || !dateStr) {
            setTrips([]);
            setLoading(false);
            return;
        }

        setLoading(true);
        axios.post('/get-buses-by-date', { route_id: routeIdNum, date: dateStr })
            .then(({ data }) => {
                const arr = Array.isArray(data) ? data : Array.isArray(data?.trips) ? data.trips : [];
                const mapped = arr.map((item: any) => ({
                    trip_id: item.trip_id,
                    bus_id: item.bus_id,
                    bus_name: item.bus_name,
                    start_location: item.start_location,
                    end_location: item.end_location,
                    departure_time: item.departure_time,
                    arrival_time: item.arrival_time,
                    price: item.price,
                    free_seats: item.free_seats,
                }));
                setTrips(mapped);
            })
            .catch((err) => {
                console.error('Помилка при завантаженні рейсів:', err);
                setTrips([]);
            })
            .finally(() => setLoading(false));
    }, [routeIdNum, dateStr]);

    // 2) ESC закриває мобільне меню — ХУК МАЄ БУТИ ДО BUDЬ-ЯКИХ return
    useEffect(() => {
        const closeOnEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setMobileOpen(false);
        };
        window.addEventListener('keydown', closeOnEsc);
        return () => window.removeEventListener('keydown', closeOnEsc);
    }, []);

    // 3) Блокування скролу, коли відкрите меню
    useEffect(() => {
        document.body.classList.toggle('mobile-nav-open', mobileOpen);
        document.body.style.overflow = mobileOpen ? 'hidden' : '';
        return () => {
            document.body.classList.remove('mobile-nav-open');
            document.body.style.overflow = '';
        };
    }, [mobileOpen]);

    const handleAnchor = (e: React.MouseEvent<HTMLAnchorElement, MouseEvent>, hash: string) => {
        e.preventDefault();
        setMobileOpen(false);
        smoothScrollToId(hash);
    };

    // РЕНДЕР (умовно виводимо контент, але не перериваємо виклик хуків)
    return (
        <div className="page-wrapper">

            {/* Navigation */}
            <nav className="bg-white text-white shadow-md relative z-40">
                <div className="container mx-auto px-6 py-4 flex justify-between items-center">
                    <a href="/" className="header-logo" onClick={e => {
                        e.preventDefault();
                        window.location.href = '/';
                    }}>
                        <img src="../../images/Asset-21.svg" alt=""/>
                    </a>

                    {/* Desktop links */}
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

                    {/* Burger (tablet/mobile) */}
                    <button
                        aria-label="Відкрити меню"
                        aria-expanded={mobileOpen}
                        className="burger md:hidden flex h-11 w-11 rounded-lg border border-gray-200 text-gray-800 items-center justify-center"
                        onClick={() => setMobileOpen(true)}
                    >
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" color="#000000"
                             stroke="currentColor">
                            <path d="M3 6h18M3 12h18M3 18h18"/>
                        </svg>
                    </button>
                </div>

                {/* Offcanvas + backdrop */}
                <div className="mobile-nav-backdrop md:hidden" onClick={() => setMobileOpen(false)}/>
                <aside className="mobile-nav-panel md:hidden">
                    <div className="flex items-center justify-between p-4 border-b">
                        <span className="font-semibold text-gray-800">Меню</span>
                        <button aria-label="Закрити меню" className="h-10 w-10 grid place-items-center"
                                onClick={() => setMobileOpen(false)}>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" color="#000000"
                                 stroke="currentColor">
                                <path d="M6 6l12 12M18 6l-12 12"/>
                            </svg>
                        </button>
                    </div>
                    <nav className="flex-1 overflow-y-auto">
                        <a href="#booking-form" className="link"
                           onClick={(e) => handleAnchor(e, '#booking-form')}>Головна</a>
                        <a href="#benefits" className="link" onClick={(e) => handleAnchor(e, '#benefits')}>Переваги</a>
                        <a href="#popular" className="link" onClick={(e) => handleAnchor(e, '#popular')}>Напрямки</a>
                        <a href="#faq" className="link" onClick={(e) => handleAnchor(e, '#faq')}>FAQ</a>
                        <a href="tel:+380930510795" className="link">+38093&nbsp;051&nbsp;0795</a>
                        <a href="mailto:info@maxbus.com" className="link">info@maxbus.com</a>
                    </nav>
                </aside>
            </nav>


            {/* Hero Section */}
            <header className="bg-gradient-to-r hero-bg from-brand to-brand-dark text-white flex-1 flex items-center">
                <div className="container mx-auto px-6 py-20 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                    <div className="hero-sm">
                        <div className="container">
                            <div className="fw-row">
                                <h1 className="text-4xl font-bold mb-6 heading">Результати пошуку</h1>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div className="srwrapp container mx-auto py-8 pt-40 pb-40 pmin">
                <ul className="space-y-4">
                    {trips.map((trip) => (
                        <li key={`${trip.trip_id}-${trip.bus_id}`}
                            className="card-search card bg-white p-6 rounded-lg shadow flex justify-between">
                            <div>
                                <p>Звідки: <span className="font-semibold">{trip.start_location}</span> → Куди: <span
                                    className="font-semibold">{trip.end_location}</span>
                                </p>
                                <p>Час відправлення: <span className="font-semibold">{trip.departure_time}</span> – Час
                                    прибуття: <span className="font-semibold">{trip.arrival_time}</span>
                                </p>
                                <p>Назва автобуса: <span className="font-semibold">{trip.bus_name}</span></p>
                            </div>
                            <div className="text-right">
                                <p className="text-xl font-bold">Від {trip.price} UAH</p>
                                <p>{trip.free_seats} вільних місць</p>
                                <Link
                                    to={`/book?tripId=${trip.trip_id}&busId=${trip.bus_id}&date=${encodeURIComponent(dateStr)}${/* DEV: */ ''}${(import.meta as any)?.env?.VITE_MOCK_PAYMENT === '1' ? '&mockPay=1' : ''}`}
                                    className="mt-2 inline-block bg-brand px-4 py-2 rounded text-white hover:bg-brand-dark transition"
                                >
                                    Бронювати
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>

            {/* Footer */}
            <footer className="bg-[#0f1f33] text-white">
                <div className="container mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-4 gap-10">
                    <div>
                        <a href="/" className="footer-logo block mb-3">
                            <img src="../../images/logomin.png" alt=""/>
                        </a>
                        <p className="text-white/80 mb-4">© 2025 MaxBus. Всі права захищені.</p>
                        <p className="text-white/60 text-sm">
                            Ми допомагаємо швидко знайти і купити автобусні квитки онлайн. Підтримуємо безпечну оплату і
                            прозорі правила повернення.
                        </p>
                    </div>

                    <div>
                        <h4 className="font-semibold mb-3 heading">Сторінки</h4>
                        <ul className="space-y-2 text-white/80">
                            <li><a href="#booking-form" className="hover:text-white transition">Пошук квитків</a></li>
                            <li><a href="#benefits" className="hover:text-white transition">Переваги сервісу</a></li>
                            <li><a href="#popular" className="hover:text-white transition">Популярні напрямки</a></li>
                            <li><a href="#faq" className="hover:text-white transition">FAQ</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 className="font-semibold mb-3 heading">Допомога</h4>
                        <ul className="space-y-2 text-white/80">
                            <li><a href="mailto:info@maxbus.com" className="hover:text-white transition">Підтримка:
                                info@maxbus.com</a></li>
                            <li><a href="tel:+380930510795" className="hover:text-white transition">+380 93 051 0795</a>
                            </li>
                            <li className="text-white/60 text-sm">Графік: 24/7</li>
                            <li className="text-white/60 text-sm">Повернення та обмін — за правилами перевізника</li>
                        </ul>
                    </div>

                    <div>
                        <h4 className="font-semibold mb-3 heading">Юридична інформація</h4>
                        <ul className="space-y-2 text-white/80">
                            <li><a href="/terms" className="hover:text-white transition">Умови використання</a></li>
                            <li><a href="#" className="hover:text-white transition">Політика конфіденційності</a></li>
                            <li><a href="/info" className="hover:text-white transition">Публічний договір</a></li>
                        </ul>
                        <div className="mt-4 flex gap-3 text-xl">
                            <a href="#" aria-label="Facebook" className="hover:text-brand-light transition"><i
                                className="fa fa-facebook"/></a>
                            <a href="#" aria-label="Instagram" className="hover:text-brand-light transition"><i
                                className="fa fa-instagram"/></a>
                            <a href="#" aria-label="Telegram" className="hover:text-brand-light transition"><i
                                className="fa fa-telegram"/></a>
                        </div>
                    </div>
                </div>
                <div className="border-t border-white/10">
                    <div
                        className="container mx-auto px-6 py-4 text-white/60 text-sm flex flex-wrap items-center gap-3">
                        <span>Платіжні системи:</span>
                        <span className="px-2 py-1 rounded bg-white/10">VISA</span>
                        <span className="px-2 py-1 rounded bg-white/10">Mastercard</span>
                        <span className="px-2 py-1 rounded bg-white/10">Apple Pay</span>
                        <span className="px-2 py-1 rounded bg-white/10">Google Pay</span>
                        {/*<span className="px-2 py-1 rounded bg-white/10">LiqPay</span>*/}
                        <span className="px-2 py-1 rounded bg-white/10">WayForPay</span>
                    </div>
                </div>
            </footer>
</div>
)
    ;
};
export default SearchResultsPage;
