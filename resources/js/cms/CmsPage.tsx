// resources/js/cms/CmsPage.tsx
import React, { useEffect, useState } from 'react';
import { getPage, CmsBlock } from './api';
import { BookingForm } from '../components/BookingForm';

export default function CmsPage({ slug }: { slug: string }) {
    const [page, setPage] = useState<{ title:string; blocks:CmsBlock[] } | null>(null);

    useEffect(() => { getPage(slug).then(setPage).catch(console.error); }, [slug]);
    if (!page) return null;

    return (
        <>
            {page.blocks?.map((b, i) => {
                switch (b.type) {
                    case 'hero':
                        return (
                            <header key={i} className="main-header bg-gradient-to-r from-brand to-brand-dark text-white">
                                <div className="container mx-auto px-6 py-24">
                                    <h1 className="text-4xl md:text-5xl font-extrabold mb-4 heading">{b.data.title}</h1>
                                    {b.data.subtitle && <p className="text-lg mb-6 max-w-xl">{b.data.subtitle}</p>}
                                    {b.data.cta_text && <a href={b.data.cta_href || '#'} className="inline-block bg-white text-brand-dark font-semibold px-8 py-3 rounded-lg shadow">{b.data.cta_text}</a>}
                                </div>
                            </header>
                        );
                    case 'booking_form':
                        return (
                            <section key={i} id="booking-form" className="container mx-auto px-6 py-12">
                                <BookingForm />
                            </section>
                        );
                    case 'benefits':
                        return (
                            <section key={i} className="container mx-auto px-6 py-16" id="benefits">
                                <h2 className="text-3xl font-bold text-center mb-10 text-white heading">Наші переваги</h2>
                                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                    {b.data.items?.map((it, k) => (
                                        <div key={k} className="bg-white rounded-2xl shadow p-6">
                                            <div className="text-lg font-semibold mb-1">{it.title}</div>
                                            {it.text && <p className="text-gray-600">{it.text}</p>}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        );
                    case 'how_it_works':
                        return (
                            <section key={i} className="bg-[#0f1f33] text-white">
                                <div className="container mx-auto px-6 py-16">
                                    <h2 className="text-3xl font-bold text-center mb-10 heading">Як придбати квиток?</h2>
                                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                        {b.data.steps?.map((s, k) => (
                                            <div key={k} className="bg-white/5 rounded-2xl p-6">
                                                <div className="text-xl font-semibold mb-1">{s.title}</div>
                                                {s.text && <p className="text-white/80">{s.text}</p>}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </section>
                        );
                    case 'faq':
                        return (
                            <section key={i} className="bg-gray-100" id="faq">
                                <div className="container mx-auto px-6 py-16">
                                    <h2 className="text-3xl font-bold text-center mb-8 text-white heading">Питання та відповіді</h2>
                                    <div className="max-w-3xl mx-auto space-y-3">
                                        {b.data.items?.map((f, k) => (
                                            <details key={k} className="bg-white rounded-lg p-4 shadow">
                                                <summary className="font-semibold cursor-pointer">{f.q}</summary>
                                                <p className="mt-2 text-gray-600">{f.a}</p>
                                            </details>
                                        ))}
                                    </div>
                                </div>
                            </section>
                        );
                    case 'trust_bar':
                        return (
                            <section key={i} className="container mx-auto px-6 py-10">
                                <div className="rounded-2xl border bg-white p-4 flex flex-wrap items-center justify-center gap-3 text-sm">
                                    <span className="px-3 py-1 rounded bg-gray-100">VISA</span>
                                    <span className="px-3 py-1 rounded bg-gray-100">Mastercard</span>
                                    <span className="px-3 py-1 rounded bg-gray-100">Apple&nbsp;Pay</span>
                                    <span className="px-3 py-1 rounded bg-gray-100">Google&nbsp;Pay</span>
                                    <span className="px-3 py-1 rounded bg-gray-100">WayForPay</span>
                                </div>
                            </section>
                        );
                    case 'help_cta':
                        return (
                            <section key={i} className="bg-brand text-white">
                                <div className="container mx-auto px-6 py-8 flex flex-col md:flex-row items-center justify-between gap-4">
                                    <div className="text-lg font-semibold">{b?.data?.text || 'Потрібна допомога з бронюванням?'}</div>
                                    <div className="opacity-90">Пишіть у чат або телефонуйте 24/7: <a className="underline" href="tel:+380930510795">+38093 051 0795</a></div>
                                </div>
                            </section>
                        );
                    default:
                        return null;
                }
            })}
        </>
    );
}
