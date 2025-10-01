import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';

type IgItem = {
    id: string;
    type: 'IMAGE' | 'VIDEO' | 'CAROUSEL_ALBUM';
    preview: string;      // що показуємо у слайді
    media_url?: string;   // може знадобитись, якщо захочеш відкрити модалку
    permalink: string;
    caption?: string | null;
    timestamp?: string | null;
};

const clamp = (n: number, min: number, max: number) => Math.max(min, Math.min(max, n));

const InstagramCarousel: React.FC<{ limit?: number; className?: string }> = ({ limit = 12, className = '' }) => {
    const [items, setItems] = useState<IgItem[]>([]);
    const [loading, setLoading] = useState(true);
    const scrollerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        (async () => {
            try {
                const { data } = await axios.get('/api/instagram-feed', { params: { limit } });
                setItems(data.items || []);
            } catch (e) {
                console.error('IG feed error', e);
            } finally {
                setLoading(false);
            }
        })();
    }, [limit]);

    const scrollByCards = (dir: 1 | -1) => {
        const el = scrollerRef.current;
        if (!el) return;
        const card = el.querySelector<HTMLElement>('[data-card]');
        const step = card ? card.offsetWidth + 16 : 300;
        el.scrollBy({ left: dir * step * 2, behavior: 'smooth' }); // по 2 картки
    };

    if (loading) {
        return (
            <section className={`container mx-auto px-6 py-12 ${className}`}>
                <h2 className="text-2xl font-bold mb-4 heading">Ми в Instagram</h2>
                <div className="h-40 grid place-items-center text-gray-500">Завантаження…</div>
            </section>
        );
    }
    if (!items.length) return null;

    return (
        <section className={`container mx-auto px-6 py-12 ${className}`}>
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-2xl md:text-3xl font-bold heading">Ми в Instagram</h2>
                <div className="flex gap-2">
                    <button className="h-10 w-10 rounded-full bg-white shadow ring-1 ring-black/10 hover:bg-gray-50"
                            aria-label="Prev" onClick={() => scrollByCards(-1)}>‹</button>
                    <button className="h-10 w-10 rounded-full bg-white shadow ring-1 ring-black/10 hover:bg-gray-50"
                            aria-label="Next" onClick={() => scrollByCards(1)}>›</button>
                </div>
            </div>

            <div
                ref={scrollerRef}
                className="overflow-x-auto scrollbar-hide scroll-smooth"
                style={{ scrollSnapType: 'x mandatory' }}
            >
                <div className="flex gap-4">
                    {items.map((m) => (
                        <a
                            key={m.id}
                            href={m.permalink}
                            target="_blank"
                            rel="noreferrer"
                            data-card
                            className="relative block w-64 sm:w-72 md:w-80 shrink-0 rounded-xl overflow-hidden bg-white ring-1 ring-black/5 shadow hover:shadow-md transition"
                            style={{ scrollSnapAlign: 'start' }}
                            aria-label={m.caption || 'Instagram post'}
                        >
                            <div className="aspect-[4/5] bg-gray-100 relative">
                                <img src={m.preview} alt={m.caption || ''} className="absolute inset-0 w-full h-full object-cover" />
                                {/* бейдж типу поста */}
                                <div className="absolute top-2 left-2 px-2 py-1 rounded-full text-xs bg-black/60 text-white">
                                    {m.type === 'VIDEO' ? 'Video' : m.type === 'CAROUSEL_ALBUM' ? 'Carousel' : 'Photo'}
                                </div>
                            </div>
                            <div className="p-3">
                                {m.caption && (
                                    <p className="text-sm text-gray-700 line-clamp-2">{m.caption}</p>
                                )}
                                {m.timestamp && (
                                    <div className="mt-2 text-xs text-gray-400">
                                        {new Date(m.timestamp).toLocaleDateString()}
                                    </div>
                                )}
                            </div>
                        </a>
                    ))}
                </div>
            </div>
        </section>
    );
};

export default InstagramCarousel;
