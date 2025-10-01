import React, {useEffect, useMemo, useRef, useState} from 'react';
import axios from 'axios';
import { Link, useSearchParams } from 'react-router-dom';
const smoothScrollToId = (id: string) => {
    const el = document.querySelector(id);
    if (!el) return;
    const y = (el as HTMLElement).getBoundingClientRect().top + window.scrollY - 12;
    window.scrollTo({ top: y, behavior: 'smooth' });
};
type Photo = {
    id: number;
    url: string;          // /storage/gallery/xxx.jpg
    w: number;            // width
    h: number;            // height
    title?: string | null;
    tags: string[];       // ["bus","team"]
    created_at: string;
    placeholder?: string | null; // base64 tiny preview (optional)
};

type PageResp = {
    items: Photo[];
    nextCursor?: string | null;
    tagsCloud: { tag: string; count: number }[];
};

const ratioPadding = (w:number, h:number) => `${(h / w) * 100}%`;

const GalleryPage: React.FC = () => {
    const [search, setSearch] = useSearchParams();
    const activeTag = search.get('tag') || '';
    const activeSort = search.get('sort') || 'newest'; // newest|oldest
    const [data, setData] = useState<Photo[]>([]);
    const [tags, setTags] = useState<PageResp['tagsCloud']>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [lightbox, setLightbox] = useState<{open:boolean; index:number}>({open:false,index:0});
    const [storiesOpen, setStoriesOpen] = useState(false);
    const loaderRef = useRef<HTMLDivElement | null>(null);


    const fetchPage = async (reset=false) => {
        if (loading) return;
        setLoading(true);
        try {
            const { data:resp } = await axios.get<PageResp>('/api/gallery', {
                params: { cursor: reset ? null : cursor, tag: activeTag || undefined, limit: 24, sort: activeSort }
            });
            setData(d => reset ? resp.items : [...d, ...resp.items]);
            setTags(resp.tagsCloud || []);
            setCursor(resp.nextCursor ?? null);
        } finally { setLoading(false); }
    };

    // initial & tag change
    useEffect(() => { setCursor(null); fetchPage(true); }, [activeTag, activeSort]);

    // infinite scroll
    useEffect(() => {
        if (!loaderRef.current) return;
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting && cursor && !loading) fetchPage(false);
            });
        }, { rootMargin: '400px 0px' });
        io.observe(loaderRef.current);
        return () => io.disconnect();
    }, [cursor, loading]);

    const open = (idx:number) => setLightbox({open:true,index:idx});
    const close = () => setLightbox({open:false,index:0});
    const next = () => setLightbox(s => ({...s, index: (s.index + 1) % data.length}));
    const prev = () => setLightbox(s => ({...s, index: (s.index - 1 + data.length) % data.length}));

    const allTags = useMemo(() => tags.sort((a,b)=>b.count-a.count), [tags]);

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
        <div className="min-h-screen bg-gray-50">
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
                        <li><a href="/#booking-form" className="hover:text-brand-light transition">Головна</a></li>
                        <li><a href="/#benefits" className="hover:text-brand-light transition">Переваги</a></li>
                        <li><a href="/#popular" className="hover:text-brand-light transition">Напрямки</a></li>
                        <li><a href="/#faq" className="hover:text-brand-light transition">FAQ</a></li>
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
            <header className="bg-gradient-to-r from-brand to-brand-dark text-white py-16">
                <div className="container mx-auto px-6">
                    <div className="flex items-end justify-between flex-wrap gap-4">
                        <div>
                            <h1 className="text-4xl font-extrabold heading">Галерея</h1>
                            <p className="opacity-90 mt-2">Живі фото наших поїздок та закулісся сервісу</p>
                            <div className="flex flex-wrap gap-2 mt-6">
                                <TagButton label="Всі" active={!activeTag} onClick={() => {
                                    setSearch({sort: activeSort});
                                }}/>
                                {tags.sort((a, b) => b.count - a.count).map(t =>
                                    <TagButton key={t.tag} label={`${t.tag} (${t.count})`} active={activeTag === t.tag}
                                               onClick={() => {
                                                   setSearch({tag: t.tag, sort: activeSort});
                                               }}/>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <select
                                className="bg-white/10 text-white rounded-lg px-3 py-2"
                                value={activeSort}
                                onChange={e => {
                                    const v = e.target.value;
                                    const next: any = {};
                                    if (activeTag) next.tag = activeTag;
                                    next.sort = v;
                                    setSearch(next);
                                }}>
                                <option value="newest">Новіші спочатку</option>
                                <option value="oldest">Старіші спочатку</option>
                            </select>
                            <button className="bg-white text-brand-dark px-3 py-2 rounded-lg shadow"
                                    onClick={() => setStoriesOpen(true)}>Відкрити історії ▶︎
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            {/* Masonry via CSS columns */}
            <section className="container mx-auto px-3 sm:px-6 py-10">
                <div className="columns-1 sm:columns-2 lg:columns-3 2xl:columns-4 gap-3 [column-fill:_balance]">
                    {data.map((ph, idx) => (
                        <figure key={ph.id} className="mb-3 break-inside-avoid group cursor-zoom-in"
                                onClick={() => open(idx)} aria-label={ph.title || 'photo'}>
                            <div className="relative overflow-hidden rounded-xl shadow ring-1 ring-black/5">
                                <div style={{paddingBottom: ratioPadding(ph.w, ph.h)}}/>
                                <img
                                    loading="lazy"
                                    src={ph.url}
                                    alt={ph.title || ''}
                                    className="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                    style={{
                                        backgroundImage: ph.placeholder ? `url(${ph.placeholder})` : undefined,
                                        backgroundSize: 'cover', backgroundPosition: 'center'
                                    }}
                                />
                                {ph.title && (
                                    <figcaption
                                        className="absolute bottom-0 inset-x-0 p-2 text-sm bg-gradient-to-t from-black/60 to-transparent text-white">
                                        {ph.title}
                                    </figcaption>
                                )}
                            </div>
                        </figure>
                    ))}
                </div>

                <div ref={loaderRef} className="py-8 text-center text-white text-gray-500">
                    {loading ? 'Завантаження…' : cursor ? 'Прокрутіть для ще фото' : 'Це все'}
                </div>
            </section>

            {/* Lightbox */}
            {storiesOpen && <Stories photos={data} onClose={() => setStoriesOpen(false)}/>}
            {lightbox.open && data[lightbox.index] && (
                <Lightbox photo={data[lightbox.index]} onClose={() => setLightbox({open: false, index: 0})}
                          onPrev={() => setLightbox(s => ({...s, index: (s.index - 1 + data.length) % data.length}))}
                          onNext={() => setLightbox(s => ({...s, index: (s.index + 1) % data.length}))}/>
            )}

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

const Stories: React.FC<{ photos: Photo[]; onClose: () => void }> = ({photos, onClose}) => {
    const [idx, setIdx] = useState(0);
    const [progress, setProgress] = useState(0); // 0..100
    const rafRef = useRef<number | null>(null);
    const startRef = useRef<number>(0);

    const DURATION = 3500; // 3.5s на одну історію

    const start = () => {
        cancel();                     // скидаємо попередній цикл
        startRef.current = performance.now();
        tick(performance.now());
    };

    const cancel = () => {
        if (rafRef.current != null) {
            cancelAnimationFrame(rafRef.current);
            rafRef.current = null;
        }
    };

    const tick = (now: number) => {
        const elapsed = now - startRef.current;
        const p = Math.min(100, (elapsed / DURATION) * 100);
        setProgress(p);

        if (p >= 100) {
            if (idx < photos.length - 1) {
                setIdx(i => i + 1);       // наступна історія
            } else {
                onClose();                // закінчили
            }
            return;                     // новий цикл запустить useEffect нижче
        }
        rafRef.current = requestAnimationFrame(tick);
    };

    useEffect(() => {
        if (!photos.length) return;
        start();
        return cancel; // чистимося при зміні idx та анмаунті
    }, [idx, photos.length]);

    const go = (n: number) => {
        if (n === idx) return;
        setIdx(Math.max(0, Math.min(photos.length - 1, n)));
        setProgress(0);
    };

    if (!photos.length) return null;

    return (
        <div className="fixed inset-0 z-50 bg-black/95 text-white p-4 sm:p-8 flex flex-col">
            {/* Прогрес-бар */}
            <div className="flex gap-1 mb-3">
                {photos.map((_, i) => (
                    <div key={i} className="h-1 bg-white/20 rounded flex-1 overflow-hidden">
                        <div className="h-full bg-white"
                             style={{width: `${i < idx ? 100 : i === idx ? progress : 0}%`}}/>
                    </div>
                ))}
            </div>

            {/* Канва зображення */}
            <div className="relative flex-1 flex items-center justify-center">
                <img src={photos[idx].url} alt="" className="max-h-full max-w-full object-contain"/>
                <button className="absolute top-3 right-3 text-2xl" onClick={onClose}>✕</button>

                {/* Зони навігації */}
                <button className="absolute left-0 top-0 bottom-0 w-1/2"
                        onClick={() => go(idx - 1)} aria-label="Prev"/>
                <button className="absolute right-0 top-0 bottom-0 w-1/2"
                        onClick={() => go(idx + 1)} aria-label="Next"/>

                {photos[idx].title && <div className="absolute bottom-4 opacity-80">{photos[idx].title}</div>}
            </div>
        </div>
    );
};


const TagButton: React.FC<{ label: string; active: boolean; onClick: () => void }> = ({label, active, onClick}) => (
    <button onClick={onClick}
            className={`px-3 py-1 rounded-full text-sm ${active ? 'bg-white text-brand-dark shadow' : 'bg-white/10 text-white hover:bg-white/20'}`}>
        {label}
    </button>
);

const Lightbox: React.FC<{ photo: Photo; onClose: () => void; onPrev: () => void; onNext: () => void }> =
    ({photo, onClose, onPrev, onNext}) => {
        useEffect(() => {
            const onKey = (e: KeyboardEvent) => {
                if (e.key === 'Escape') onClose();
                if (e.key === 'ArrowRight') onNext();
                if (e.key === 'ArrowLeft') onPrev();
            };
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', onKey);
            return () => {
                document.body.style.overflow = '';
                window.removeEventListener('keydown', onKey);
            };
        }, []);
        return (
            <div className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
                 onClick={onClose}>
                <button className="absolute top-4 right-4 text-white/80 text-2xl" aria-label="Закрити">✕</button>
                <button className="absolute left-4 md:left-8 text-white/80 text-3xl" onClick={(e) => {
                    e.stopPropagation();
                    onPrev();
                }} aria-label="Попереднє">‹
                </button>
                <button className="absolute right-4 md:right-8 text-white/80 text-3xl" onClick={(e) => {
                    e.stopPropagation(); onNext();}} aria-label="Наступне">›</button>
                <img src={photo.url} alt={photo.title || ''} className="max-h-[90vh] max-w-[92vw] object-contain"
                     onClick={(e)=>e.stopPropagation()} />
                {photo.title && <div className="absolute bottom-6 text-white/80">{photo.title}</div>}
            </div>
        );
    };

export default GalleryPage;
