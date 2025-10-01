// resources/js/pages/IndexPage.tsx
import React, {useEffect, useMemo, useState} from 'react';
import axios from 'axios';
import {DayPicker} from 'react-day-picker';
import {format} from 'date-fns';
import { uk } from 'date-fns/locale';
import 'react-day-picker/dist/style.css';
import {useNavigate} from 'react-router-dom';
import InstagramCarousel from '../components/InstagramCarousel';

const smoothScrollToId = (id: string) => {
    const el = document.querySelector(id);
    if (!el) return;
    const y = (el as HTMLElement).getBoundingClientRect().top + window.scrollY - 12;
    window.scrollTo({ top: y, behavior: 'smooth' });
};

interface Route {
    id: number;
    start_point: string;
    end_point: string;
}

/* ===================== Booking form ===================== */
export const BookingForm: React.FC = () => {
    const navigate = useNavigate();
    const [routes, setRoutes] = useState<Route[]>([]);
    const [departureCities, setDepartureCities] = useState<string[]>([]);
    const [arrivalCities, setArrivalCities] = useState<string[]>([]);
    const [selectedDeparture, setSelectedDeparture] = useState<string>('');
    const [selectedArrival, setSelectedArrival] = useState<string>('');
    const [availableDates, setAvailableDates] = useState<string[]>([]);
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(undefined);
    const [showCalendar, setShowCalendar] = useState(false);
    const [loadingRoutes, setLoadingRoutes] = useState(true);
    const [loadingDates, setLoadingDates] = useState(false);
    const [fFrom, setFFrom] = useState(false);
    const [fTo, setFTo] = useState(false);
    const raisedFrom = fFrom || (selectedDeparture ?? '').trim().length > 0;
    const raisedTo   = fTo   || (selectedArrival ?? '').trim().length > 0;
    const raisedDate = showCalendar || !!selectedDate;

    // 1) всі маршрути
    useEffect(() => {
        axios.get<Route[]>('/api/routes').then(({ data }) => {
            setRoutes(data);
            setDepartureCities(Array.from(new Set(data.map(r => r.start_point))));
            setArrivalCities(Array.from(new Set(data.map(r => r.end_point))));
            setLoadingRoutes(false);
        });
    }, []);

    // 2) доступні дати по напрямку
    useEffect(() => {
        const route = routes.find(
            r => r.start_point === selectedDeparture && r.end_point === selectedArrival,
        );
        if (!route) {
            setAvailableDates([]);
            setSelectedDate(undefined);
            return;
        }
        setLoadingDates(true);
        axios.get<string[]>(`/api/routes/${route.id}/available-dates`).then(({ data }) => {
            setAvailableDates(data);
            if (selectedDate) {
                const ds = format(selectedDate, 'yyyy-MM-dd');
                if (!data.includes(ds)) setSelectedDate(undefined);
            }
            setLoadingDates(false);
        });
    }, [selectedDeparture, selectedArrival, routes]);

    const swapCities = () => {
        if (!selectedDeparture && !selectedArrival) return;
        setSelectedDeparture(selectedArrival);
        setSelectedArrival(selectedDeparture);
        setSelectedDate(undefined);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const route = routes.find(
            r => r.start_point === selectedDeparture && r.end_point === selectedArrival,
        );
        if (!route || !selectedDate) return;
        const dateStr = format(selectedDate, 'yyyy-MM-dd');
        navigate(`/search?routeId=${route.id}&date=${dateStr}`);
    };

    const filteredArrivals = arrivalCities.filter(city =>
        routes.some(r => r.start_point === selectedDeparture && r.end_point === city),
    );

    // helper: плавний скрол по якорях
    const [isMobile, setIsMobile] = useState<boolean>(false);

    useEffect(() => {
        const onResize = () => setIsMobile(window.innerWidth <= 768);
        onResize();
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        // блокувати скрол під модалкою календаря на мобілках
        if (isMobile && showCalendar) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }, [isMobile, showCalendar]);


    return (
        <form onSubmit={handleSubmit} className="relative">
            {/* Широка пошукова панель */}
            <div className="main-booking card shadow w-full rounded-2xl bg-white/95 ring-1 ring-black/5 px-10 py-10 md:px-10 md:py-10">
                <div className="
          grid gap-3 items-end
          md:grid-cols-[1fr_auto_1fr_auto]  /* from | swap | to | date */
          lg:grid-cols-[1fr_auto_1fr_auto_auto] /* + button */
        ">
                    {/* FROM */}
                    <div className={`fgroup ${raisedFrom ? 'raised' : ''}`}>
                        <label htmlFor="fromCity" className="flabel">Звідки</label>
                        <select
                            id="fromCity"
                            className="control w-full text-base focus:outline-none"
                            value={selectedDeparture}
                            onFocus={() => setFFrom(true)}
                            onBlur={() => setFFrom(false)}
                            onChange={e => {
                                setSelectedDeparture(e.target.value);
                                setSelectedArrival('');
                                setSelectedDate(undefined);
                            }}
                            disabled={loadingRoutes}
                        >
                            <option value="">{loadingRoutes ? 'Завантаження…' : 'Оберіть місто виїзду'}</option>
                            {departureCities.map(city => (
                                <option key={city} value={city}>{city}</option>
                            ))}
                        </select>
                    </div>

                    {/* SWAP */}
                    <div className="flex items-center justify-center pt-0 md:pb-0">
                        <button
                            type="button"
                            onClick={swapCities}
                            title="Поміняти місцями"
                            className="swap-btn"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M4 7h11M10 3l4 4-4 4"/>
                                <path d="M20 17H9M13 21l-4-4 4-4"/>
                            </svg>
                        </button>
                    </div>

                    {/* TO */}
                    <div className={`fgroup ${raisedTo ? 'raised' : ''}`}>
                        <label htmlFor="toCity" className="flabel">Куди</label>
                        <select
                            id="toCity"
                            className="control w-full text-base focus:outline-none disabled:bg-gray-100/20"
                            value={selectedArrival}
                            onFocus={() => setFTo(true)}
                            onBlur={() => setFTo(false)}
                            onChange={e => {
                                setSelectedArrival(e.target.value);
                                setSelectedDate(undefined);
                            }}
                            disabled={!selectedDeparture || loadingRoutes}
                        >
                            <option value="">
                                {selectedDeparture ? 'Оберіть місто прибуття' : 'Спочатку оберіть звідки'}
                            </option>
                            {filteredArrivals.map(city => (
                                <option key={city} value={city}>{city}</option>
                            ))}
                        </select>
                    </div>


                    {/* DATE (кнопка + поповер з DayPicker) */}
                    <div className={`fgroup relative ${raisedDate ? 'raised' : ''}`}>
                        <label className="text-md font-medium text-gray-600 mb-1">Дата</label>
                        <button
                            type="button"
                            onClick={() => setShowCalendar(s => !s)}
                            disabled={!selectedDeparture || !selectedArrival || loadingDates}
                            className="h-14 w-full rounded-xl border border-gray-300 px-4 text-left text-base focus:outline-none focus:ring-2 focus:ring-brand/60 disabled:bg-gray-100"
                        >
                            {selectedDate ? format(selectedDate, 'dd MMM yyyy', {locale: uk}) :
                                loadingDates ? 'Завантаження дат…' : 'Оберіть дату'}
                        </button>

                        {showCalendar && (
                            isMobile ? (
                                <div className="dpick mobile-fullscreen z-50">
                                    <div className="modal-actions">
                                        <button
                                            type="button"
                                            onClick={() => setShowCalendar(false)}
                                            className="border px-4"
                                        >
                                            Закрити
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowCalendar(false)}
                                            className="bg-brand text-white px-4"
                                            disabled={!selectedDate}
                                        >
                                            Обрати
                                        </button>
                                    </div>
                                    <DayPicker
                                        locale={uk}
                                        mode="single"
                                        selected={selectedDate}
                                        onSelect={(d) => setSelectedDate(d)}
                                        disabled={[
                                            {before: new Date()},
                                            (date) => !availableDates.includes(format(date, 'yyyy-MM-dd')),
                                        ]}
                                    />
                                </div>
                            ) : (
                                <div
                                    className="dpick absolute z-50 top-full mt-2 right-0 md:left-0 bg-white rounded-xl shadow-2xl ring-1 ring-black/5 p-2">
                                    <DayPicker
                                        locale={uk}
                                        mode="single"
                                        selected={selectedDate}
                                        onSelect={(d) => {
                                            setSelectedDate(d);
                                            if (d) setShowCalendar(false);
                                        }}
                                        disabled={[
                                            {before: new Date()},
                                            (date) => !availableDates.includes(format(date, 'yyyy-MM-dd')),
                                        ]}
                                    />
                                </div>
                            )
                        )}

                    </div>

                    {/* ACTION */}
                    <div className="lg:pl-2">
                        <button
                            type="submit"
                            disabled={!selectedDate}
                            className="btn-submit h-14 lg:w-auto px-8 rounded-xl bg-brand text-white font-semibold shadow hover:bg-brand-dark transition disabled:opacity-60"
                        >
                            Пошук автобусів
                        </button>
                    </div>
                    {/* підказки/помилки */}
                    {!selectedDate && (selectedDeparture && selectedArrival) && !loadingDates && (
                        <div className="info-pop mt-3 text-sm text-amber-700">
                            Оберіть доступну дату в календарі (сірою позначено недоступні).
                        </div>
                    )}
                </div>


            </div>
        </form>
    );
};

/* ===================== Декоративні іконки (inline) ===================== */

const CardIcon: React.FC<{kind: 'online' | 'secure' | 'transport' | 'bonus' | 'clock' | 'ticket' | 'check' | 'bus'}> =
    ({kind}) => {
        const common = 'w-10 h-10';
        switch (kind) {
            case 'online':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="4" width="18" height="12" rx="2" />
                        <path d="M8 20h8" />
                    </svg>
                );
            case 'secure':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 22s8-4 8-10V6l-8-4-8 4v6c0 6 8 10 8 10z" />
                        <path d="M9 12l2 2 4-4" />
                    </svg>
                );
            case 'transport':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="18" height="13" rx="2" />
                        <path d="M7 16v2M17 16v2M5 21h14" />
                    </svg>
                );
            case 'bonus':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M8 12h8M12 8v8" />
                    </svg>
                );
            case 'clock':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 3" />
                    </svg>
                );
            case 'ticket':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 9a3 3 0 0 1 3-3h12l3 3-3 3 3 3-3 3H6a3 3 0 0 1-3-3" />
                        <path d="M8 7v10" />
                    </svg>
                );
            case 'check':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                );
            case 'bus':
                return (
                    <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="4" y="3" width="16" height="13" rx="2" />
                        <path d="M6 16v2m12-2v2M6 11h12" />
                    </svg>
                );
            default:
                return null;
        }
    };

/* ===================== Допоміжні секції ===================== */

const BenefitsSection: React.FC = () => (
    <section className="container mx-auto px-6 py-16" id="benefits">
        <h2 className="text-3xl font-bold text-center mb-10 text-white heading">Наші переваги</h2>
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            {[
                {
                    icon: 'online',
                    title: 'Всі квитки доступні онлайн',
                    text:
                        'Без черг: виберіть рейс і купуйте за 2 хвилини з телефону чи комп’ютера.',
                },
                {
                    icon: 'secure',
                    title: 'Безпечна оплата',
                    text:
                        'Платіжні шлюзи банків. Дані картки шифруються і не зберігаються на нашому сайті.',
                },
                {
                    icon: 'transport',
                    title: 'Багато напрямків',
                    text:
                        'Квитки на автобуси по Україні та ЄС. Додаємо нові рейси щотижня.',
                },
                {
                    icon: 'bonus',
                    title: 'Бонуси за покупки',
                    text:
                        'Зареєструйтесь — накопичуйте бонуси та використовуйте їх при наступних замовленнях.',
                },
            ].map((c, i) => (
                <div key={i} className="bg-white rounded-2xl shadow p-6">
                    <div className="text-brand-dark mb-3"><CardIcon kind={c.icon as any} /></div>
                    <div className="text-lg font-semibold mb-1">{c.title}</div>
                    <p className="text-gray-600">{c.text}</p>
                </div>
            ))}
        </div>
    </section>
);

const HowItWorks: React.FC = () => (
    <section className="bg-[#0f1f33] text-white">
        <div className="container mx-auto px-6 py-16">
            <h2 className="text-3xl font-bold text-center mb-10 heading">Як придбати квиток?</h2>
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                {[
                    {icon: 'clock', title: '1. Подивіться розклад', text: 'Оберіть пункт відправлення/прибуття та дату поїздки.'},
                    {icon: 'ticket', title: '2. Виберіть рейс', text: 'Порівняйте час, ціну та оберіть зручні місця.'},
                    {icon: 'check', title: '3. Оформіть замовлення', text: 'Заповніть дані пасажирів та оплатіть квиток.'},
                    {icon: 'bus', title: '4. Готово!', text: 'Отримайте електронний квиток на email і в особистому кабінеті.'},
                ].map((s, i) => (
                    <div key={i} className="bg-white/5 rounded-2xl p-6 backdrop-blur">
                        <div className="text-brand-light mb-3"><CardIcon kind={s.icon as any} /></div>
                        <div className="text-xl font-semibold mb-1">{s.title}</div>
                        <p className="text-white/80">{s.text}</p>
                    </div>
                ))}
            </div>
        </div>
    </section>
);

/** Топ-напрямки: обчислюємо з /api/routes за найчастішими парами */
const PopularDirections: React.FC = () => {
    const [routes, setRoutes] = useState<Route[]>([]);
    useEffect(() => {
        axios.get<Route[]>('/api/routes').then(({data}) => setRoutes(data || []));
    }, []);

    const topPairs = useMemo(() => {
        const counts = new Map<string, number>();
        routes.forEach(r => {
            const key = `${r.start_point} → ${r.end_point}`;
            counts.set(key, (counts.get(key) || 0) + 1);
        });
        return Array.from(counts.entries())
            .sort((a, b) => b[1] - a[1])
            .slice(0, 12)
            .map(([k]) => k);
    }, [routes]);

    if (!topPairs.length) return null;

    return (
        <section className="container mx-auto px-6 py-16" id="popular">
            <h2 className="text-3xl font-bold text-center mb-8 text-white heading">Популярні напрямки</h2>
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {topPairs.map((pair, i) => (
                    <a
                        key={i}
                        href="#booking-form"
                        className="px-4 py-3 rounded-lg bg-white shadow hover:shadow-md transition flex items-center justify-between"
                    >
                        <span>{pair}</span>
                        <span className="text-brand-dark">→</span>
                    </a>
                ))}
            </div>
        </section>
    );
};

const FaqSection: React.FC = () => (
    <section className="bg-gray-100">
        <div className="container mx-auto px-6 py-16" id="faq">
            <h2 className="text-3xl font-bold text-center mb-8 text-white heading">Питання та відповіді</h2>
            <div className="max-w-3xl mx-auto space-y-3">
                {[
                    {
                        q: 'Чи потрібно друкувати квиток?',
                        a: 'Ні. Достатньо електронного квитка у телефоні та документа, що посвідчує особу.',
                    },
                    {
                        q: 'Як повернути/обміняти квиток?',
                        a: 'У більшості рейсів доступне повернення за умовами перевізника. Зайдіть у “Мої замовлення” або напишіть у підтримку.',
                    },
                    {
                        q: 'Що робити, якщо оплата не пройшла?',
                        a: 'Спробуйте іншу картку/спосіб оплати. Якщо кошти списались, але квиток не надійшов — зверніться до нашої підтримки, ми перевіримо транзакцію.',
                    },
                    {
                        q: 'Які способи оплати підтримуються?',
                        a: 'Банківські картки Visa/Mastercard, Apple Pay/Google Pay, іноді LiqPay/WayForPay — залежно від налаштувань рейсу.',
                    },
                ].map((item, i) => (
                    <details key={i} className="bg-white rounded-lg p-4 shadow">
                        <summary className="font-semibold cursor-pointer">{item.q}</summary>
                        <p className="mt-2 text-gray-600">{item.a}</p>
                    </details>
                ))}
            </div>
        </div>
    </section>
);

const TrustBar: React.FC = () => (
    <section className="container mx-auto px-6 py-10">
        <div className="rounded-2xl border bg-white p-4 flex flex-wrap items-center justify-center gap-3 text-sm">
            <span className="px-3 py-1 rounded bg-gray-100">VISA</span>
            <span className="px-3 py-1 rounded bg-gray-100">Mastercard</span>
            <span className="px-3 py-1 rounded bg-gray-100">Apple&nbsp;Pay</span>
            <span className="px-3 py-1 rounded bg-gray-100">Google&nbsp;Pay</span>
            {/*<span className="px-3 py-1 rounded bg-gray-100">LiqPay</span>*/}
            <span className="px-3 py-1 rounded bg-gray-100">WayForPay</span>
            <span className="ml-3 text-gray-500">Оплата відбувається через захищені платіжні шлюзи</span>
        </div>
    </section>
);

const HelpCTA: React.FC = () => (
    <section className="cta-section bg-brand text-white">
        <div className="container mx-auto px-6 py-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <div className="text-lg font-semibold">Потрібна допомога з бронюванням?</div>
            <div className="opacity-90">Пишіть у чат або телефонуйте 24/7:</div>
            <div className="flex items-center gap-4">
                <a href="tel:+380930510795">+38093&nbsp;051&nbsp;0795</a>
                <a href="mailto:info@maxbus.com">info@maxbus.com</a>
            </div>
        </div>
    </section>
);

/* ===================== Головна сторінка ===================== */

const IndexPage: React.FC = () => {
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        const closeOnEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setMobileOpen(false);
        };
        window.addEventListener('keydown', closeOnEsc);
        return () => window.removeEventListener('keydown', closeOnEsc);
    }, []);

    useEffect(() => {
        document.body.classList.toggle('mobile-nav-open', mobileOpen);
        document.body.style.overflow = mobileOpen ? 'hidden' : '';
    }, [mobileOpen]);

    const handleAnchor = (e: React.MouseEvent<HTMLAnchorElement, MouseEvent>, hash: string) => {
        e.preventDefault();
        setMobileOpen(false);
        smoothScrollToId(hash);
    };
    return (
        <div className="flex flex-col min-h-screen bg-gray-50 text-gray-900 relative">
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

            {/* Hero */}
            <header
                className="main-header bg-gradient-to-r from-brand to-brand-dark text-white flex-1 flex items-center">
                <div
                    className="header-container container mx-auto px-6 py-40 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 className="text-4xl md:text-5xl font-extrabold mb-4 leading-tight heading">Забронюйте
                            поїздку зараз</h1>
                        <p className="text-lg mb-6 max-w-md">Швидка та зручна система бронювання автобусів.</p>
                        <a href="#booking-form" onClick={(e) => {
                            e.preventDefault();
                            smoothScrollToId('#booking-form');
                        }}
                           className="btn-mobile inline-block bg-white text-brand-dark font-semibold px-8 py-3 rounded-lg shadow hover:shadow-lg transition">
                            Розпочати бронювання
                        </a>

                    </div>
                    <div className="flex justify-center cta-phone">
                        <div className="hcard">
              <span className="icon">
                <i className="fa fa-phone"/>
              </span>
                            <div className="content-wrap">
                                <span className="item-title">Зв'яжіться з нами</span>
                                <p className="text">
                                    <a href="tel:+380930510795">+38093 051 0795</a>
                                    <a href="tel:+48223906203">+4822 390 62 03</a>
                                    <a href="tel:+380972211099">+38093 051 0795</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <svg className="hero-line" id="visual" viewBox="0 450 900 150" width="900" height="150"
                 xmlns="http://www.w3.org/2000/svg" version="1.1">
                <path d="M0 524L129 537L257 511L386 544L514 536L643 527L771 550L900 528"
                      fill="none" strokeLinecap="square" strokeLinejoin="bevel" stroke="#faa51a" strokeWidth="40"/>
            </svg>

            {/* Booking Form */}
            <section id="booking-form" className="container mx-auto px-6 py-16 relative z-20">
                <h2 className="text-3xl font-bold text-center mb-8 text-brand-dark heading">Форма бронювання</h2>
                <BookingForm/>
            </section>

            {/* Нові інформативні секції */}
            <BenefitsSection/>
            <HowItWorks/>
            <PopularDirections/>
            <InstagramCarousel className="bg-white rounded-2xl"/>
            {/*<div className='sk-instagram-feed' data-embed-id='25604490'></div>*/}
            {/*<script src='https://widgets.sociablekit.com/instagram-feed/widget.js' defer></script>*/}
            {/*<iframe src='https://widgets.sociablekit.com/instagram-feed/iframe/25604490' frameBorder='0' width='100%'*/}
            {/*        height='1000'></iframe>*/}
            <TrustBar/>
            <HelpCTA/>
            <FaqSection/>
            {/* Декорації (як було) */}
            <div className="main-section">
                <div id="scene">
                    <div id="background-wrap">
                        <div className="x1">
                            <div className="cloud"/>
                        </div>
                        <div className="x2">
                            <div className="cloud"/>
                        </div>
                        <div className="x3">
                            <div className="cloud"/>
                        </div>
                    </div>

                    <div className="cloud_3" data-depth="0.2">
                        <img src="../../images/ic_cloud-3_ygyuja.svg" alt=""/>
                    </div>
                    <div className="cloud_1" data-depth="0.6">
                        <img src="../../images/ic_cloud-1_y4gj1j.svg" alt=""/>
                    </div>
                    <div className="cloud_2" data-depth="0.4">
                        <img src="../../images/ic_cloud-2_uw3c2v.svg" alt=""/>
                    </div>

                    <div className="hills-background-wrap">
                        <div className="inner slide-right-img"/>
                    </div>
                    <div className="rope-line">
                        <div className="inner slide-right-rope"/>
                    </div>
                    <div className="buildings-bg">
                        <div className="inner"/>
                    </div>
                    <div className="bus-wrap">
                        <div className="inner">
                            <svg width="621px" height="182px" viewBox="0 0 621 182" version="1.1"
                                 xmlns="http://www.w3.org/2000/svg" xmlnsXlink="http://www.w3.org/1999/xlink">
                                <title>bus Clipped</title>
                                <desc>Created with Sketch.</desc>
                                <defs>
                                    <rect id="path-1" x="0" y="0" width="4860" height="996"></rect>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-3"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-5"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-7"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-9"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-11"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-13"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-15"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-17"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-19"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-21"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-23"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-25"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-27"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-29"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-31"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-33"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-35"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-37"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-39"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-41"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-43"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-45"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-47"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-49"></path>
                                    <path
                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                        id="path-51"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-53"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-55"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-57"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-59"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-61"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-63"></path>
                                    <path
                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                        id="path-65"></path>
                                </defs>
                                <g id="Page-1" stroke="none" strokeWidth="1" fill="none" fillRule="evenodd">
                                    <g id="illustration" transform="translate(-4131.000000, -795.000000)">
                                        <g id="Group-8">
                                            <g id="bus-Clipped">
                                                <mask id="mask-2" fill="white">
                                                    <use xlinkHref="#path-1"></use>
                                                </mask>
                                                <g id="path-1"></g>
                                                <g id="bus" mask="url(#mask-2)">
                                                    <g transform="translate(4132.000000, 795.000000)" id="Group">
                                                        <g>
                                                            <rect id="bus-shadow" fill="#000000" fillRule="nonzero"
                                                                  opacity="0.100000001" x="27" y="172" width="567"
                                                                  height="10" rx="5"></rect>
                                                            <g id="bus_body" transform="translate(14.000000, 0.000000)">
                                                                <rect id="top-light_red" fill="#E85442"
                                                                      fillRule="nonzero" x="567" y="0" width="18"
                                                                      height="9" rx="4"></rect>
                                                                <rect id="top-light_white" fill="#FFFFFF"
                                                                      fillRule="nonzero" x="42" y="0" width="18"
                                                                      height="9" rx="4"></rect>
                                                                <g id="Mask" transform="translate(0.000000, 4.000000)"
                                                                   fill="#faa51a" fillRule="nonzero">
                                                                    <path
                                                                        d="M594.470881,148.950741 L591.294014,151.571433 C587.638852,154.588205 580.922868,157 576.180772,157 L13.4417459,157 C8.30740914,157 3.75203894,152.860525 3.26480368,147.750913 L0.165709314,115.206205 C-0.267398067,110.664229 0.177608079,103.392385 1.16124897,98.94008 L21.0361416,8.928585 C22.1292344,3.98086207 27.0866126,0 32.1558247,0 L596.577702,0 C601.7631,0 606,4.23362221 606,9.4167241 L606,90.7248068 C606,95.193892 605.602422,102.451447 605.114202,106.886198 L602.037339,134.938957 C601.517669,139.686671 598.157724,145.907821 594.470881,148.950741 Z"
                                                                        id="path-7"></path>
                                                                </g>
                                                                <g id="headlight-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-4" fill="white">
                                                                        <use xlinkHref="#path-3"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <path
                                                                        d="M-3,121 L7.16188365,116.3099 C8.16478789,115.847021 9.35303989,116.284798 9.81591877,117.287702 C9.93719422,117.550466 10,117.836419 10,118.125819 L10,142.34854 C10,143.45311 9.1045695,144.34854 8,144.34854 C7.81416695,144.34854 7.62924068,144.32264 7.45055774,144.271588 L-4,141 L-3,121 Z"
                                                                        id="headlight" fill="#FAEE5A"
                                                                        fillRule="nonzero" mask="url(#mask-4)"></path>
                                                                </g>
                                                                <g id="Stroke-3-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-6" fill="white">
                                                                        <use xlinkHref="#path-5"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <path
                                                                        d="M1.16124897,98.94008 L21.0361416,8.928585 C21.5362725,6.6648164 27.0782373,7.7824808 27.0782373,9.4167241 L7.31745291,97.378459 C7.11236557,98.29137 6.30175224,98.94008 5.3660881,98.94008 L1.16124897,98.94008 Z"
                                                                        id="Stroke-3" fill="#A6C3FF" fillRule="nonzero"
                                                                        mask="url(#mask-6)"></path>
                                                                </g>
                                                                <g id="bus_sidelights-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-8" fill="white">
                                                                        <use xlinkHref="#path-7"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="bus_sidelights" fill="#FAEE5A"
                                                                          fillRule="nonzero" mask="url(#mask-8)" x="98"
                                                                          y="128" width="7" height="4" rx="2"></rect>
                                                                </g>
                                                                <g id="bus_sidelights-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-10" fill="white">
                                                                        <use xlinkHref="#path-9"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="bus_sidelights" fill="#FAEE5A"
                                                                          fillRule="nonzero" mask="url(#mask-10)"
                                                                          x="310" y="128" width="7" height="4"
                                                                          rx="2"></rect>
                                                                </g>
                                                                <g id="bus_sidelights-copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-12" fill="white">
                                                                        <use xlinkHref="#path-11"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="bus_sidelights-copy" fill="#FAEE5A"
                                                                          fillRule="nonzero" mask="url(#mask-12)"
                                                                          x="203" y="128" width="7" height="4"
                                                                          rx="2"></rect>
                                                                </g>
                                                                <g id="bus_sidelights-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-14" fill="white">
                                                                        <use xlinkHref="#path-13"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="bus_sidelights" fill="#FAEE5A"
                                                                          fillRule="nonzero" mask="url(#mask-14)"
                                                                          x="451" y="128" width="7" height="4"
                                                                          rx="2"></rect>
                                                                </g>
                                                                <g id="bus_sidelights-copy-2-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-16" fill="white">
                                                                        <use xlinkHref="#path-15"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="bus_sidelights-copy-2" fill="#FAEE5A"
                                                                          fillRule="nonzero" mask="url(#mask-16)"
                                                                          x="556" y="128" width="7" height="4"
                                                                          rx="2"></rect>
                                                                </g>
                                                                <g id="Rectangle-8-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-18" fill="white">
                                                                        <use xlinkHref="#path-17"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="Rectangle-8" fill="#E3E4F6"
                                                                          fillRule="nonzero" mask="url(#mask-18)"
                                                                          x="13" y="103" width="581" height="9"></rect>
                                                                </g>
                                                                <g id="Rectangle-8-Copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-20" fill="white">
                                                                        <use xlinkHref="#path-19"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="Rectangle-8-Copy" fill="#FAEE5A"
                                                                          fillRule="nonzero" mask="url(#mask-20)"
                                                                          x="13" y="115" width="581" height="3"></rect>
                                                                </g>
                                                                <g id="Group-26-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-22" fill="white">
                                                                        <use xlinkHref="#path-21"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="Group-26" mask="url(#mask-22)">
                                                                        <g transform="translate(32.000000, 26.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <path
                                                                                    d="M2,0 L55,0 C56.1045695,-2.02906125e-16 57,0.8954305 57,2 L57,131 L0,131 L0,2 C-1.3527075e-16,0.8954305 0.8954305,2.02906125e-16 2,0 Z"
                                                                                    id="door" fill="#faa51a"
                                                                                    fillRule="nonzero"></path>
                                                                                <path
                                                                                    d="M0.5,130.5 L56.5,130.5 L56.5,2 C56.5,1.17157288 55.8284271,0.5 55,0.5 L2,0.5 C1.17157288,0.5 0.5,1.17157288 0.5,2 L0.5,130.5 Z"
                                                                                    id="door" stroke="#373064"></path>
                                                                                <rect id="Rectangle-22" fill="#faa51a"
                                                                                      fillRule="nonzero" x="28" y="1"
                                                                                      width="1" height="129"></rect>
                                                                                <rect id="glass" fill="#A6C3FF"
                                                                                      fillRule="nonzero" x="7" y="7"
                                                                                      width="14" height="100"
                                                                                      rx="2"></rect>
                                                                                <rect id="glass" fill="#A6C3FF"
                                                                                      fillRule="nonzero" x="36" y="7"
                                                                                      width="14" height="100"
                                                                                      rx="2"></rect>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="Group-26-Copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-24" fill="white">
                                                                        <use xlinkHref="#path-23"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="Group-26-Copy" mask="url(#mask-24)">
                                                                        <g transform="translate(471.000000, 26.000000)"
                                                                           id="Group-28">
                                                                            <g>
                                                                                <g id="Group">
                                                                                    <path
                                                                                        d="M2,0 L55,0 C56.1045695,-2.02906125e-16 57,0.8954305 57,2 L57,131 L0,131 L0,2 C-1.3527075e-16,0.8954305 0.8954305,2.02906125e-16 2,0 Z"
                                                                                        id="door" fill="#faa51a"
                                                                                        fillRule="nonzero"></path>
                                                                                    <path
                                                                                        d="M0.5,130.5 L56.5,130.5 L56.5,2 C56.5,1.17157288 55.8284271,0.5 55,0.5 L2,0.5 C1.17157288,0.5 0.5,1.17157288 0.5,2 L0.5,130.5 Z"
                                                                                        id="door"
                                                                                        stroke="#373064"></path>
                                                                                    <rect id="Rectangle-22"
                                                                                          fill="#faa51a"
                                                                                          fillRule="nonzero" x="28"
                                                                                          y="1" width="1"
                                                                                          height="129"></rect>
                                                                                    <rect id="glass" fill="#A6C3FF"
                                                                                          fillRule="nonzero" x="7"
                                                                                          y="7" width="14" height="100"
                                                                                          rx="2"></rect>
                                                                                    <rect id="glass" fill="#A6C3FF"
                                                                                          fillRule="nonzero" x="36"
                                                                                          y="7" width="14" height="100"
                                                                                          rx="2"></rect>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-5-copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-26" fill="white">
                                                                        <use xlinkHref="#path-25"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-5-copy" mask="url(#mask-26)">
                                                                        <g transform="translate(99.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-9"></path>
                                                                                </g>
                                                                                <g id="window-5-copy-Clipped">
                                                                                    <mask id="mask-28" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-27"></use>
                                                                                    </mask>
                                                                                    <g id="path-9"></g>
                                                                                    <path
                                                                                        d="M56.6631927,43 C57.9540529,43 59,42.2406221 59,41.3028059 L59,14.6991426 C59,13.7606329 57.9530975,13 56.6631927,13 L9.33412445,13 C8.04517536,13 7,13.7599391 7,14.6991426 L7,41.3028059 C7,42.2413159 8.04421995,43 9.33412445,43 L56.6631927,43 Z"
                                                                                        id="window-5-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-28)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-3-copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-30" fill="white">
                                                                        <use xlinkHref="#path-29"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-3-copy" mask="url(#mask-30)">
                                                                        <g transform="translate(157.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-11"></path>
                                                                                </g>
                                                                                <g id="window-3-copy-Clipped">
                                                                                    <mask id="mask-32" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-31"></use>
                                                                                    </mask>
                                                                                    <g id="path-11"></g>
                                                                                    <path
                                                                                        d="M54.6631927,43 C55.9540529,43 57,42.2406221 57,41.3028059 L57,14.6991426 C57,13.7606329 55.9530975,13 54.6631927,13 L7.33412445,13 C6.04517536,13 5,13.7599391 5,14.6991426 L5,41.3028059 C5,42.2413159 6.04421995,43 7.33412445,43 L54.6631927,43 Z"
                                                                                        id="window-3-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-32)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-2-copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-34" fill="white">
                                                                        <use xlinkHref="#path-33"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-2-copy" mask="url(#mask-34)">
                                                                        <g transform="translate(223.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-13"></path>
                                                                                </g>
                                                                                <g id="window-2-copy-Clipped">
                                                                                    <mask id="mask-36" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-35"></use>
                                                                                    </mask>
                                                                                    <g id="path-13"></g>
                                                                                    <path
                                                                                        d="M52.6631927,43 C53.9540529,43 55,42.2406221 55,41.3028059 L55,14.6991426 C55,13.7606329 53.9530975,13 52.6631927,13 L5.33412445,13 C4.04517536,13 3,13.7599391 3,14.6991426 L3,41.3028059 C3,42.2413159 4.04421995,43 5.33412445,43 L52.6631927,43 Z"
                                                                                        id="window-2-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-36)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-2-copy-2-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-38" fill="white">
                                                                        <use xlinkHref="#path-37"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-2-copy-2" mask="url(#mask-38)">
                                                                        <g transform="translate(347.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-15"></path>
                                                                                </g>
                                                                                <g id="window-2-copy-Clipped">
                                                                                    <mask id="mask-40" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-39"></use>
                                                                                    </mask>
                                                                                    <g id="path-15"></g>
                                                                                    <path
                                                                                        d="M52.3486224,43 C53.8132524,43 55,42.2406221 55,41.3028059 L55,14.6991426 C55,13.7606329 53.8121683,13 52.3486224,13 L-1.35166649,13 C-2.81412796,13 -4,13.7599391 -4,14.6991426 L-4,41.3028059 C-4,42.2413159 -2.81521198,43 -1.35166649,43 L52.3486224,43 Z"
                                                                                        id="window-2-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-40)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-2-copy-3-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-42" fill="white">
                                                                        <use xlinkHref="#path-41"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-2-copy-3" mask="url(#mask-42)">
                                                                        <g transform="translate(540.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-17"></path>
                                                                                </g>
                                                                                <g id="window-2-copy-Clipped">
                                                                                    <mask id="mask-44" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-43"></use>
                                                                                    </mask>
                                                                                    <g id="path-17"></g>
                                                                                    <path
                                                                                        d="M46.3486224,43 C47.8132524,43 49,42.2406221 49,41.3028059 L49,14.6991426 C49,13.7606329 47.8121683,13 46.3486224,13 L-7.35166649,13 C-8.81412796,13 -10,13.7599391 -10,14.6991426 L-10,41.3028059 C-10,42.2413159 -8.81521198,43 -7.35166649,43 L46.3486224,43 Z"
                                                                                        id="window-2-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-44)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-1-copy-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-46" fill="white">
                                                                        <use xlinkHref="#path-45"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-1-copy" mask="url(#mask-46)">
                                                                        <g transform="translate(281.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-19"></path>
                                                                                </g>
                                                                                <g id="window-1-copy-Clipped">
                                                                                    <mask id="mask-48" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-47"></use>
                                                                                    </mask>
                                                                                    <g id="path-19"></g>
                                                                                    <path
                                                                                        d="M51.6631927,43 C52.9540529,43 54,42.2406221 54,41.3028059 L54,14.6991426 C54,13.7606329 52.9530975,13 51.6631927,13 L4.33412445,13 C3.04517536,13 2,13.7599391 2,14.6991426 L2,41.3028059 C2,42.2413159 3.04421995,43 4.33412445,43 L51.6631927,43 Z"
                                                                                        id="window-1-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-48)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="window-1-copy-2-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-50" fill="white">
                                                                        <use xlinkHref="#path-49"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <g id="window-1-copy-2" mask="url(#mask-50)">
                                                                        <g transform="translate(405.000000, 38.000000)"
                                                                           id="Group">
                                                                            <g>
                                                                                <g id="Mask" fill="#373064"
                                                                                   fillRule="nonzero">
                                                                                    <path
                                                                                        d="M51.5733154,43 C52.9138242,43 54,41.9115583 54,40.5673552 L54,2.43543778 C54,1.09024052 52.912832,0 51.5733154,0 L2.42389846,0 C1.08537441,0 0,1.08924605 0,2.43543778 L0,40.5673552 C0,41.9125528 1.08438226,43 2.42389846,43 L51.5733154,43 Z"
                                                                                        id="path-21"></path>
                                                                                </g>
                                                                                <g id="window-1-copy-Clipped">
                                                                                    <mask id="mask-52" fill="white">
                                                                                        <use
                                                                                            xlinkHref="#path-51"></use>
                                                                                    </mask>
                                                                                    <g id="path-21"></g>
                                                                                    <path
                                                                                        d="M49.3486224,43 C50.8132524,43 52,42.2406221 52,41.3028059 L52,14.6991426 C52,13.7606329 50.8121683,13 49.3486224,13 L-4.35166649,13 C-5.81412796,13 -7,13.7599391 -7,14.6991426 L-7,41.3028059 C-7,42.2413159 -5.81521198,43 -4.35166649,43 L49.3486224,43 Z"
                                                                                        id="window-1-copy"
                                                                                        fill="#453C7D"
                                                                                        fillRule="nonzero"
                                                                                        mask="url(#mask-52)"></path>
                                                                                </g>
                                                                            </g>
                                                                        </g>
                                                                    </g>
                                                                </g>
                                                                <g id="vent-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-54" fill="white">
                                                                        <use xlinkHref="#path-53"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent" fill="#373064" fillRule="nonzero"
                                                                          mask="url(#mask-54)" x="99" y="26" width="54"
                                                                          height="10" rx="2"></rect>
                                                                </g>
                                                                <g id="vent-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-56" fill="white">
                                                                        <use xlinkHref="#path-55"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent" fill="#373064" fillRule="nonzero"
                                                                          mask="url(#mask-56)" x="157" y="26" width="54"
                                                                          height="10" rx="2"></rect>
                                                                </g>
                                                                <g id="vent-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-58" fill="white">
                                                                        <use xlinkHref="#path-57"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent" fill="#373064" fillRule="nonzero"
                                                                          mask="url(#mask-58)" x="223" y="26" width="54"
                                                                          height="10" rx="2"></rect>
                                                                </g>
                                                                <g id="vent-copy-2-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-60" fill="white">
                                                                        <use xlinkHref="#path-59"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent-copy-2" fill="#373064"
                                                                          fillRule="nonzero" mask="url(#mask-60)"
                                                                          x="347" y="26" width="54" height="10"
                                                                          rx="2"></rect>
                                                                </g>
                                                                <g id="vent-copy-4-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-62" fill="white">
                                                                        <use xlinkHref="#path-61"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent-copy-4" fill="#373064"
                                                                          fillRule="nonzero" mask="url(#mask-62)"
                                                                          x="540" y="26" width="54" height="10"
                                                                          rx="2"></rect>
                                                                </g>
                                                                <g id="vent-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-64" fill="white">
                                                                        <use xlinkHref="#path-63"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent" fill="#373064" fillRule="nonzero"
                                                                          mask="url(#mask-64)" x="281" y="26" width="54"
                                                                          height="10" rx="2"></rect>
                                                                </g>
                                                                <g id="vent-copy-3-Clipped"
                                                                   transform="translate(0.000000, 4.000000)">
                                                                    <mask id="mask-66" fill="white">
                                                                        <use xlinkHref="#path-65"></use>
                                                                    </mask>
                                                                    <g id="path-7"></g>
                                                                    <rect id="vent-copy-3" fill="#373064"
                                                                          fillRule="nonzero" mask="url(#mask-66)"
                                                                          x="405" y="26" width="54" height="10"
                                                                          rx="2"></rect>
                                                                </g>
                                                            </g>
                                                            <g id="Group-4"
                                                               transform="translate(119.000000, 126.000000)"
                                                               fillRule="nonzero">
                                                                <path
                                                                    d="M0.416045371,35.0057497 L59.5844049,35.0052082 C59.8760577,33.2692722 60.0184575,31.4833774 59.9980842,29.6613666 C59.8096181,13.0955169 46.2272824,-0.183356362 29.6582384,0.00191538475 C13.0923888,0.193575812 -0.186484497,13.7727171 0.00198159079,30.3417611 C0.0200432382,31.9296554 0.161118834,33.4873023 0.416045371,35.0057497 Z"
                                                                    id="wheel_front" fill="#2D2754"></path>
                                                                <path
                                                                    d="M5.00165133,30.2848009 C5.1587064,44.0923376 16.4773194,55.1554034 30.2821941,54.9983483 C44.0897308,54.8439552 55.1527966,43.5253421 54.9984035,29.7178055 C54.8413484,15.9129308 43.5227354,4.84720303 29.7151987,5.00159615 C15.910324,5.16131318 4.84459625,16.4772643 5.00165133,30.2848009"
                                                                    id="wheel_front" fill="#333333"></path>
                                                                <path
                                                                    d="M15.0010482,31.1696222 C15.0926205,39.4542241 21.8851322,46.0959107 30.169734,45.9989518 C38.4543359,45.9019928 45.0906359,39.1148678 44.9990636,30.830266 C44.9021047,22.5456641 38.1149796,15.9093641 29.8249912,16.0009364 C21.5457759,16.0925087 14.9040893,22.8850204 15.0010482,31.1696222"
                                                                    id="Fill-31" fill="#9B9B9B"></path>
                                                                <path
                                                                    d="M27.0002096,31.0339244 C27.0185241,32.6908448 28.3770264,34.0191821 30.0339468,33.9997904 C31.6908672,33.9803986 33.0181272,32.6229736 32.9998127,30.9660532 C32.9804209,29.3091328 31.6229959,27.9818728 29.9649982,28.0001873 C28.3091552,28.0185017 26.9808179,29.3770041 27.0002096,31.0339244"
                                                                    id="Fill-31" fill="#FFFFFF"></path>
                                                                <path
                                                                    d="M28.0001398,22.0226163 C27.9872119,20.9180027 28.8727701,20.0123345 29.9766655,20.0001249 C31.0819973,19.9879152 31.9869473,20.8727552 31.9998751,21.9773688 C32.0120848,23.0819824 31.1272448,23.9869324 30.0226312,23.9998602 C28.9180176,24.0127881 28.0123494,23.1272299 28.0001398,22.0226163 Z M28.0001398,40.0226163 C27.9872119,38.9180027 28.8727701,38.0123345 29.9766655,38.0001249 C31.0819973,37.9879152 31.9869473,38.8727552 31.9998751,39.9773688 C32.0120848,41.0819824 31.1272448,41.9869324 30.0226312,41.9998602 C28.9180176,42.0127881 28.0123494,41.1272299 28.0001398,40.0226163 Z M36.7747122,24.7793784 C37.7248717,24.2158757 38.9519825,24.5299575 39.5145041,25.4798541 C40.0777438,26.4309947 39.7639249,27.6571244 38.8137654,28.2206271 C37.8632468,28.7835077 36.6371171,28.4696888 36.0736145,27.5195293 C35.5101118,26.5693698 35.8241936,25.342259 36.7747122,24.7793784 Z M21.186255,33.7793784 C22.1364145,33.2158757 23.3635253,33.5299575 23.9260468,34.4798541 C24.4892866,35.4309947 24.1754676,36.6571244 23.2253081,37.2206271 C22.2747895,37.7835077 21.0486598,37.4696888 20.4851572,36.5195293 C19.9216546,35.5693698 20.2357364,34.342259 21.186255,33.7793784 Z M38.7745725,33.7567621 C39.7376598,34.297873 40.0792124,35.517623 39.5378386,36.4797293 C38.9957465,37.4430795 37.7769776,37.7843692 36.8138903,37.2432583 C35.851162,36.7015254 35.5098723,35.4827564 36.0509833,34.5196691 C36.5920942,33.5565817 37.8118442,33.2150292 38.7745725,33.7567621 Z M23.1861152,24.7567621 C24.1492025,25.297873 24.4907551,26.517623 23.9493813,27.4797293 C23.4072893,28.4430795 22.1885203,28.7843692 21.225433,28.2432583 C20.2627047,27.7015254 19.9214151,26.4827564 20.462526,25.5196691 C21.0036369,24.5565817 22.223387,24.2150292 23.1861152,24.7567621 Z"
                                                                    id="Combined-Shape" fill="#DDDDDD"></path>
                                                            </g>
                                                            <g id="Group-4-Copy"
                                                               transform="translate(400.000000, 126.000000)"
                                                               fillRule="nonzero">
                                                                <path
                                                                    d="M0.416045371,35.0057497 L59.5844049,35.0052082 C59.8760577,33.2692722 60.0184575,31.4833774 59.9980842,29.6613666 C59.8096181,13.0955169 46.2272824,-0.183356362 29.6582384,0.00191538475 C13.0923888,0.193575812 -0.186484497,13.7727171 0.00198159079,30.3417611 C0.0200432382,31.9296554 0.161118834,33.4873023 0.416045371,35.0057497 Z"
                                                                    id="wheel_front" fill="#2D2754"></path>
                                                                <path
                                                                    d="M5.00165133,30.2848009 C5.1587064,44.0923376 16.4773194,55.1554034 30.2821941,54.9983483 C44.0897308,54.8439552 55.1527966,43.5253421 54.9984035,29.7178055 C54.8413484,15.9129308 43.5227354,4.84720303 29.7151987,5.00159615 C15.910324,5.16131318 4.84459625,16.4772643 5.00165133,30.2848009"
                                                                    id="wheel_front" fill="#333333"></path>
                                                                <path
                                                                    d="M15.0010482,31.1696222 C15.0926205,39.4542241 21.8851322,46.0959107 30.169734,45.9989518 C38.4543359,45.9019928 45.0906359,39.1148678 44.9990636,30.830266 C44.9021047,22.5456641 38.1149796,15.9093641 29.8249912,16.0009364 C21.5457759,16.0925087 14.9040893,22.8850204 15.0010482,31.1696222"
                                                                    id="Fill-31" fill="#9B9B9B"></path>
                                                                <path
                                                                    d="M27.0002096,31.0339244 C27.0185241,32.6908448 28.3770264,34.0191821 30.0339468,33.9997904 C31.6908672,33.9803986 33.0181272,32.6229736 32.9998127,30.9660532 C32.9804209,29.3091328 31.6229959,27.9818728 29.9649982,28.0001873 C28.3091552,28.0185017 26.9808179,29.3770041 27.0002096,31.0339244"
                                                                    id="Fill-31" fill="#FFFFFF"></path>
                                                                <path
                                                                    d="M28.0001398,22.0226163 C27.9872119,20.9180027 28.8727701,20.0123345 29.9766655,20.0001249 C31.0819973,19.9879152 31.9869473,20.8727552 31.9998751,21.9773688 C32.0120848,23.0819824 31.1272448,23.9869324 30.0226312,23.9998602 C28.9180176,24.0127881 28.0123494,23.1272299 28.0001398,22.0226163 Z M28.0001398,40.0226163 C27.9872119,38.9180027 28.8727701,38.0123345 29.9766655,38.0001249 C31.0819973,37.9879152 31.9869473,38.8727552 31.9998751,39.9773688 C32.0120848,41.0819824 31.1272448,41.9869324 30.0226312,41.9998602 C28.9180176,42.0127881 28.0123494,41.1272299 28.0001398,40.0226163 Z M36.7747122,24.7793784 C37.7248717,24.2158757 38.9519825,24.5299575 39.5145041,25.4798541 C40.0777438,26.4309947 39.7639249,27.6571244 38.8137654,28.2206271 C37.8632468,28.7835077 36.6371171,28.4696888 36.0736145,27.5195293 C35.5101118,26.5693698 35.8241936,25.342259 36.7747122,24.7793784 Z M21.186255,33.7793784 C22.1364145,33.2158757 23.3635253,33.5299575 23.9260468,34.4798541 C24.4892866,35.4309947 24.1754676,36.6571244 23.2253081,37.2206271 C22.2747895,37.7835077 21.0486598,37.4696888 20.4851572,36.5195293 C19.9216546,35.5693698 20.2357364,34.342259 21.186255,33.7793784 Z M38.7745725,33.7567621 C39.7376598,34.297873 40.0792124,35.517623 39.5378386,36.4797293 C38.9957465,37.4430795 37.7769776,37.7843692 36.8138903,37.2432583 C35.851162,36.7015254 35.5098723,35.4827564 36.0509833,34.5196691 C36.5920942,33.5565817 37.8118442,33.2150292 38.7745725,33.7567621 Z M23.1861152,24.7567621 C24.1492025,25.297873 24.4907551,26.517623 23.9493813,27.4797293 C23.4072893,28.4430795 22.1885203,28.7843692 21.225433,28.2432583 C20.2627047,27.7015254 19.9214151,26.4827564 20.462526,25.5196691 C21.0036369,24.5565817 22.223387,24.2150292 23.1861152,24.7567621 Z"
                                                                    id="Combined-Shape" fill="#DDDDDD"></path>
                                                            </g>
                                                            <path d="M0.0944498761,35.0438845 L43,35.0438845"
                                                                  id="Path-11" stroke="#453C7D" strokeWidth="2"
                                                                  strokeLinecap="round" strokeLinejoin="round"></path>
                                                            <polyline id="Path-10" stroke="#453C7D" strokeWidth="2"
                                                                      strokeLinecap="round" strokeLinejoin="round"
                                                                      points="43.005124 26.1965944 8.53898841 26.1965944 0.15738471 34.5781981 0.15738471 47.3612825 10.151044 47.3612825"></polyline>
                                                            <rect id="Rectangle-23" fill="#453C7D" fillRule="nonzero"
                                                                  x="9" y="40" width="4" height="24" rx="2"></rect>
                                                        </g>
                                                    </g>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <div className="tyres-wrapper">
                                <div className="tyres-content">
                                    <div className="tyres">
                                        <div className="rim-section">
                                            <div className="rim-dot"></div>
                                        </div>
                                    </div>
                                </div>
                                <div className="tyres-content">
                                    <div className="tyres">
                                        <div className="rim-section">
                                            <div className="rim-dot"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="road-wrap">
                        <div className="bar slide-right"/>
                    </div>
                </div>
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
                            <li><a href="/gallery" className="hover:text-brand-light transition">Галерея</a></li>
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

            <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css" rel="stylesheet"/>
        </div>
    );
};

export default IndexPage;
