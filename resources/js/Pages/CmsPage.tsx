// resources/js/Pages/CmsPage.tsx
import React from "react";
import axios from "axios";

type Block =
    | { type: "rich_text"; data: { html: string } }
    | Record<string, any>;

type PageDto = {
    slug: string;
    title: string;
    blocks: Block[];
    meta?: { title?: string; description?: string };
};

const BlockRenderer: React.FC<{ block: Block }> = ({ block }) => {
    if (block.type === "rich_text") {
        return (
            <div
                className="prose prose-lg max-w-none"
                dangerouslySetInnerHTML={{ __html: (block as any).data?.html ?? "" }}
            />
        );
    }
    return null;
};

const CmsPage: React.FC<{ slug: string }> = ({ slug }) => {
    const [page, setPage] = React.useState<PageDto | null>(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        let alive = true;
        setLoading(true);
        axios
            .get<PageDto>(`/api/cms/pages/${slug}`)
            .then(({ data }) => {
                if (!alive) return;
                setPage(data);
                setError(null);

                // оновлюємо <title> / description без Helmet
                document.title = data.meta?.title || data.title;
                const desc = data.meta?.description ?? "";
                let meta = document.querySelector('meta[name="description"]') as HTMLMetaElement | null;
                if (!meta) {
                    meta = document.createElement("meta");
                    meta.name = "description";
                    document.head.appendChild(meta);
                }
                meta.content = desc;
            })
            .catch((e) => {
                if (!alive) return;
                setError(e?.response?.status === 404 ? "Сторінку не знайдено" : "Помилка завантаження");
            })
            .finally(() => alive && setLoading(false));

        return () => {
            alive = false;
        };
    }, [slug]);

    if (loading) return <div className="container mx-auto px-6 py-16">Завантаження…</div>;
    if (error) return <div className="container mx-auto px-6 py-16 text-red-600">{error}</div>;
    if (!page) return null;

    return (
        <div className="page-wrapper">
            {/* Top bar */}
            <nav className="bg-white text-white shadow-md">
                <div className="container mx-auto px-6 py-4 flex justify-between items-center">
                    <a href="/" className="header-logo">
                        <img src="../../images/Asset-21.svg" alt=""/>
                    </a>
                    <ul className="flex space-x-8">
                        <li><a href="#" className="hover:text-brand-light transition">Головна</a></li>
                        <li><a href="#" className="hover:text-brand-light transition">Про нас</a></li>
                        <li><a href="#" className="hover:text-brand-light transition">Контакти</a></li>
                    </ul>
                </div>
            </nav>

            {/* Hero */}
            <div className="container mx-auto px-6 py-16 bg-white mt-20">
                <h1 className="text-3xl md:text-4xl font-bold mb-6 heading">{page.title}</h1>
                <div className="space-y-8">
                    {page.blocks?.map((b, i) => (
                        <BlockRenderer key={i} block={b}/>
                    ))}
                </div>
            </div>

            {/* Footer */}
            <footer className="bg-[#0f1f33] text-white">
                <div className="container mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-4 gap-10">
                    <div>
                        <a href="/" className="footer-logo block mb-3">
                            <img src="../../images/Asset-21.svg" alt=""/>
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

            <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css" rel="stylesheet"/>
        </div>
    );
};

export default CmsPage;
