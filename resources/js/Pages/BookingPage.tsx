// resources/js/pages/BookingPage.tsx
import React, {useEffect, useMemo, useRef, useState} from "react";
import axios from "axios";
import {useSearchParams} from "react-router-dom";
import BusLayout from "./BusLayout";

declare global {
    interface Window {
        gtag?: (...args: any[]) => void;
        __beginCheckoutSent?: boolean;
    }
}

function TicketPreview({ticket}: { ticket: any }) {
    return (
        <div
            className="ticket-preview"
            style={{
                background: "#fffbe7",
                border: "2px solid orange",
                borderRadius: 20,
                marginBottom: 24,
                padding: 24,
                maxWidth: 400,
                margin: "0 auto",
            }}
        >
            <h3 style={{marginBottom: 12, fontSize: 22, fontWeight: 600}}>
                Квиток на автобус
            </h3>
            <div><b>Маршрут:</b> {ticket.route}</div>
            <div><b>Автобус:</b> {ticket.bus}</div>
            <div><b>Дата:</b> {ticket.date}</div>
            <div><b>Місця:</b> {ticket.seats.join(", ")}</div>
            <div><b>ПІБ:</b> {ticket.name} {ticket.surname}</div>
            <div style={{fontSize: 18, fontWeight: 600, color: "crimson", marginTop: 10}}>
                До сплати: {ticket.price} {ticket.currency}
                {ticket.currency !== 'UAH' && <span style={{opacity: .7}}> (~{ticket.price_uah} UAH)</span>}
            </div>
        </div>
    );
}

type Seat = {
    row: string | number;
    column: string | number;
    type: string;
    number?: string | null;
    price?: string | number;
    seat_type?: string;
    w?: number | string;
    h?: number | string;
};

type PassengerOpts = {
    category: "adult" | "child";
    extras: string[];
    firstName: string;
    lastName: string;
    docNumber?: string;
};

const EXTRAS: Record<string, { label: string; price: number }> = {
    coffee:  { label: "Кава",    price: 30 },
    blanket: { label: "Плед",    price: 50 },
    service: { label: "Сервіс",  price: 20 },
};
const CHILD_DISCOUNT_PCT = 10; // -10% на дитячий

export default function BookingPage() {
    const [searchParams] = useSearchParams();

    // dev-режим: ?mockPay=1 або VITE_MOCK_PAYMENT=1
    const DEV_MOCK_PAYMENT =
        searchParams.get('mockPay') === '1' ||
        (typeof import.meta !== 'undefined' &&
            (import.meta as any).env &&
            (import.meta as any).env.VITE_MOCK_PAYMENT === '1');

    const tripId = searchParams.get("tripId");
    const date   = searchParams.get("date");
    const busId  = searchParams.get("busId");  // buses.id

    const [bus, setBus] = useState<{ seat_layout: Seat[] } | null>(null);
    const [bookedSeats, setBookedSeats] = useState<string[]>([]);
    const [heldSeats, setHeldSeats]     = useState<string[]>([]);
    const [selectedSeats, setSelectedSeats] = useState<string[]>([]);
    const [solo, setSolo] = useState<boolean>(false);

    // пер-пасажир по кожному місцю
    const [passengers, setPassengers] = useState<Record<string, PassengerOpts>>({});

    // контактні дані платника
    const [form, setForm] = useState({
        name: "", surname: "", email: "", phone: "", phone_alt: "", password: "",
    });

    const [paymentForm, setPaymentForm]   = useState<string | null>(null);
    const [ticketPreview, setTicketPreview] = useState<any>(null);

    // HOLD state
    const [holdToken, setHoldToken] = useState<string | null>(null);
    const [expiresAt, setExpiresAt] = useState<string | null>(null); // ISO
    const heartbeatRef = useRef<number | null>(null);

    // helpers
    const layout = bus?.seat_layout || [];
    const [nowTs, setNowTs] = useState<number>(Date.now());
    const ttlSecondsRef     = useRef<number>(600);
    const heartbeatMsRef    = useRef<number>(20000);
    const standbyFormRef = useRef<HTMLFormElement | null>(null);


    const [currencies, setCurrencies] = useState<{code:string,rate:number,symbol?:string}[]>([]);
    const [currency, setCurrency] = useState<'UAH'|'PLN'|'EUR'>('UAH');
    const [promo, setPromo] = useState<string>('');

    // ------- STANDBY (sold-out preauth) -------
    const [standbyFormHtml, setStandbyFormHtml] = useState<string|null>(null);
    const [standbySeats, setStandbySeats] = useState<number>(1);
    const [allowPartial, setAllowPartial] = useState<boolean>(false);

    // 1) Завантаження схеми + зайнятості
    useEffect(() => {
        if (!busId || !date) return;

        axios.get(`/api/trips/${busId}/bus-info`, { params: { date } })
            .then(({ data }) => {
                setBus(data.bus);
                if (data.booked_seats) setBookedSeats(data.booked_seats.map(String));
            });

        // axios.get(`/api/trips/${tripId}/seats`, { params: { date } })
        axios.get(`/api/trips/${busId}/seats`, { params: { date } })
            .then(({ data }) => {
                if (data.held_seats) setHeldSeats(data.held_seats.map(String));
                else if (data.held)  setHeldSeats(data.held.map(String));
                if (data.booked_seats) setBookedSeats(data.booked_seats.map(String));
                else if (data.booked)  setBookedSeats(data.booked.map(String));
            })
            .catch(() => {});
    }, [tripId, busId, date]);

    // 2) Пошук сусіднього (для «без сусіда»)
    const seatBasePrice = (seatNum: string) => {
        const s = layout.find(x => x.type === "seat" && String(x.number) === String(seatNum));
        return Number(s?.price ?? 0);
    };

    const findNeighbor = (seatNum: string): string | null => {
        const current = layout.find(s => s.type === "seat" && s.number === seatNum);
        if (!current) return null;

        const row = Number(current.row);
        const col = Number(current.column);

        const candidates = [{ row, column: col - 1 }, { row, column: col + 1 }];
        for (const c of candidates) {
            const neighbor = layout.find(s =>
                s.type === "seat" &&
                Number(s.row) === c.row &&
                Number(s.column) === c.column
            );
            if (!neighbor || !neighbor.number) continue;
            const n = String(neighbor.number);
            const blocked = bookedSeats.includes(n) || heldSeats.includes(n) || selectedSeats.includes(n);
            if (!blocked) return n;
        }
        return null;
    };

    const handleSeatClick = (number: string) => {
        if (bookedSeats.includes(number) || heldSeats.includes(number)) return;

        setSelectedSeats(prev => {
            const exists = prev.includes(number);
            let next = exists ? prev.filter(n => n !== number) : [...prev, number];

            if (!exists && solo) {
                const neighbor = findNeighbor(number);
                if (neighbor) next = [...next, neighbor];
            }
            return next;
        });
    };

    // 3) HOLD: створення/оновлення при зміні вибраних місць
    useEffect(() => {
        // if (!tripId || !date) return;
        if (!busId || !date) return;

        const run = async () => {
            if (selectedSeats.length === 0) {
                if (holdToken) {
                    try { await axios.post("/api/hold/release", { token: holdToken }); } catch {}
                    setHoldToken(null);
                    setExpiresAt(null);
                }
                return;
            }

            try {
                // const { data } = await axios.post(`/api/trips/${tripId}/hold`, {
                const { data } = await axios.post(`/api/trips/${busId}/hold`, {
                    date, seats: selectedSeats, token: holdToken || undefined, solo,
                });
                if (typeof data.ttl_seconds === "number") {
                    ttlSecondsRef.current = data.ttl_seconds;
                    heartbeatMsRef.current = Math.min(60_000, Math.max(15_000, (ttlSecondsRef.current - 30) * 1000));
                }
                if (Array.isArray(data.held_seats)) setHeldSeats(data.held_seats.map(String));

                setHoldToken(data.token);
                setExpiresAt(data.expires_at);
            } catch (e: any) {
                if (e?.response?.status === 409 && Array.isArray(e.response.data.held_seats)) {
                    setHeldSeats(e.response.data.held_seats);
                }
            }
        };
        run();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    // }, [selectedSeats, solo, tripId, date]);
    }, [selectedSeats, solo, busId, date]);

    // 4) HOLD: heartbeat
    useEffect(() => {
        if (!holdToken) return;

        let cancelled = false;
        const tokenSnapshot = holdToken;

        const beat = async () => {
            if (cancelled) return;
            try {
                const { data } = await axios.post("/api/hold/prolong", { token: tokenSnapshot });
                if (cancelled) return;
                if (data?.expires_at) setExpiresAt(data.expires_at);
                if (typeof data?.ttl_seconds === "number") {
                    ttlSecondsRef.current = data.ttl_seconds;
                    heartbeatMsRef.current = Math.min(60_000, Math.max(15_000, (ttlSecondsRef.current - 30) * 1000));
                }
            } catch {}
        };

        const first = window.setTimeout(beat, 500);
        const id    = window.setInterval(beat, heartbeatMsRef.current);
        heartbeatRef.current = id as unknown as number;

        return () => {
            cancelled = true;
            window.clearTimeout(first);
            window.clearInterval(id);
        };
    }, [holdToken]);

    // 5) passengers <-> selectedSeats
    useEffect(() => {
        setPassengers(prev => {
            const next: Record<string, PassengerOpts> = {};
            selectedSeats.forEach(n => {
                next[n] = prev[n] ?? {
                    category: "adult",
                    extras: [],
                    firstName: "",
                    lastName: "",
                    docNumber: "",
                };
            });
            return next;
        });
    }, [selectedSeats]);

    // 6) Таймер
    useEffect(() => {
        const id = window.setInterval(() => setNowTs(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, []);

    useEffect(() => {
        axios.get('/api/currencies')
            .then(({data}) => setCurrencies(data))
            .catch(() => setCurrencies([{code:'UAH', rate:1, symbol:'₴'}]));
    }, []);

    const rate = useMemo(()=>{
        const r = currencies.find(c=>c.code===currency)?.rate ?? 1;
        return Number(r) || 1;
    }, [currency, currencies]);

    const secondsLeft = useMemo(() => {
        if (!expiresAt) return null;
        const ms = new Date(expiresAt).getTime() - nowTs;
        const s  = Math.ceil(ms / 1000);
        return s > 0 ? s : 0;
    }, [expiresAt, nowTs]);

    // 7) сума на фронті
    const frontTotals = useMemo(() => {
        const perSeatAfterCategory = selectedSeats.map(n => {
            const base = seatBasePrice(n);
            const cat  = passengers[n]?.category ?? "adult";
            const afterCat = cat === "child"
                ? Math.round(base * (1 - CHILD_DISCOUNT_PCT / 100) * 100) / 100
                : base;
            return { seat: n, base, afterCat };
        });

        // solo знижка на дешевше
        let soloAdj: Record<string, number> = {};
        if (solo && perSeatAfterCategory.length === 2) {
            const iCheaper = perSeatAfterCategory[0].afterCat <= perSeatAfterCategory[1].afterCat ? 0 : 1;
            const cheaperSeat = perSeatAfterCategory[iCheaper].seat;
            soloAdj[cheaperSeat] = Math.round(perSeatAfterCategory[iCheaper].afterCat * 0.2 * 100) / 100;
        }

        const extrasTotals: Record<string, number> = {};
        selectedSeats.forEach(n => {
            const ext = passengers[n]?.extras ?? [];
            extrasTotals[n] = ext.reduce((sum, k) => sum + (EXTRAS[k]?.price ?? 0), 0);
        });

        let total = 0;
        const lines = selectedSeats.map(n => {
            const afterCat  = perSeatAfterCategory.find(x => x.seat === n)?.afterCat ?? 0;
            const soloMinus = soloAdj[n] ?? 0;
            const seatPart  = Math.max(afterCat - soloMinus, 0);
            const extrasPart = extrasTotals[n] ?? 0;
            const lineTotal = seatPart + extrasPart;
            total += lineTotal;
            return { seat: n, seatPart, extrasPart, lineTotal };
        });

        return { lines, total };
    }, [selectedSeats, passengers, solo, layout]);

    // валідація пасажирів: ПІБ обовʼязкові
    const invalidSeats = useMemo(() => {
        return selectedSeats.filter(n => {
            const p = passengers[n];
            return !p || !p.firstName?.trim() || !p.lastName?.trim();
        });
    }, [selectedSeats, passengers]);

    const currencyOptions = currencies.length ? currencies : [{code:'UAH', rate:1}];

    const [promoPreview, setPromoPreview] = useState(0);

    // коли змінюється промокод або базова сума
    useEffect(() => {
        if (!promo?.trim()) { setPromoPreview(0); return; }
        axios.get('/api/promo/check', { params: { code: promo.trim(), subtotal_uah: frontTotals.total } })
            .then(({data}) => setPromoPreview(data.ok ? Number(data.discount_uah || 0) : 0))
            .catch(() => setPromoPreview(0));
    }, [promo, frontTotals.total]);

    const totalAfterPromoUAH = Math.max(frontTotals.total - promoPreview, 0);

    const totalAfterPromoConv = currency === 'UAH' ? null : Math.round((totalAfterPromoUAH / rate) * 100) / 100;


    // --- GA4: begin_checkout один раз після вибору місць ---
    useEffect(() => {
        if (!window.gtag) return;
        if (window.__beginCheckoutSent) return;
        if (selectedSeats.length === 0) return;

        window.__beginCheckoutSent = true;

        window.gtag('event', 'begin_checkout', {
            currency: 'UAH',
            value: Number(totalAfterPromoUAH) || 0,
            items: selectedSeats.map(n => ({
                item_id: String(n),
                item_name: 'Seat ' + n,
                price: seatBasePrice(n),
                quantity: 1,
            })),
        });
    }, [selectedSeats.length, totalAfterPromoUAH]);

    useEffect(() => {
        if (selectedSeats.length === 0) window.__beginCheckoutSent = false;
    }, [selectedSeats.length]);

    // масове копіювання ПІБ із контактних
    const fillAllFromContact = () => {
        setPassengers(prev => {
            const next = { ...prev };
            selectedSeats.forEach(n => {
                next[n] = {
                    ...(next[n] ?? { category: "adult", extras: [] as string[] }),
                    firstName: form.name,
                    lastName: form.surname,
                    docNumber: next[n]?.docNumber ?? "",
                };
            });
            return next;
        });
    };

    // 8) Сабміт (звичайне бронювання з вибором місць)
    // ...верх файлу без змін

// 8) Сабміт (звичайне бронювання з вибором місць)
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (invalidSeats.length) return;

        const payload = {
            // trip_id: Number(tripId),
            trip_id: Number(busId),
            date,
            seats: selectedSeats.map(Number),
            solo,
            hold_token: holdToken || null,
            passengers: selectedSeats.map(n => ({
                seat: Number(n),
                category: passengers[n]?.category ?? "adult",
                extras: passengers[n]?.extras ?? [],
                first_name: passengers[n]?.firstName ?? "",
                last_name:  passengers[n]?.lastName ?? "",
                doc_number: passengers[n]?.docNumber ?? "",
            })),
            ...form,
            currency_code: currency,
            promo_code: promo?.trim() || undefined,
        };

        // >>> DEV: повний байпас платежу
        if (DEV_MOCK_PAYMENT) {
            try {
                const { data } = await axios.post('/api/dev/mock-paid', {
                    order_reference: null,
                    // trip_id: Number(tripId),
                    trip_id: Number(busId),
                    date,
                    seats: selectedSeats.map(Number),

                    // ⬇️ передаємо деталі по кожному місцю
                    passengers: selectedSeats.map(n => ({
                        seat: Number(n),
                        first_name: passengers[n]?.firstName || form.name,
                        last_name:  passengers[n]?.lastName  || form.surname,
                        doc_number: passengers[n]?.docNumber || null,
                        extras:     passengers[n]?.extras     || [],  // кави/пледи тощо (якщо є)
                        category:   passengers[n]?.category   || 'adult',
                    })),

                    promo_code:  (promo?.trim() || null),
                    currency_code: currency,   // можна лишати 'UAH' — бек все одно запише UAH

                    user: {
                        name: form.name,
                        surname: form.surname,
                        email: form.email,
                        phone: form.phone,
                        password: form.password,
                    },
                });

                // беремо готове прев’ю з бека (містить маршрут/автобус/дату/місця/ціну)
                setTicketPreview(data.ticket_preview);
                setPaymentForm('<div class="p-3 rounded border border-green-300 bg-green-50">DEV: Оплату пропущено. Вважаємо успішною ✅</div>');
                return;
            } catch (e:any) {
                const msg = e?.response?.data?.message
                    || Object.values(e?.response?.data?.errors || {})?.flat()?.[0]
                    || 'Помилка DEV-оплати';
                alert(String(msg));
                console.error(e?.response?.data || e);
                return;
            }
        }

        // >>> PROD: реальний флоу
        const { data } = await axios.post('/api/book-seat', payload);
        setTicketPreview(data.ticket_preview);
        setPaymentForm(data.payment_form);
        if ((window as any).gtag) {
            (window as any).gtag('event', 'add_payment_info', {
                currency: 'UAH',
                value: totalAfterPromoUAH,
                payment_type: 'wayforpay',
            });
        }
        setTimeout(() => {
            (document.querySelector('form[action*="liqpay"], form[action*="wayforpay"]') as HTMLFormElement | null)?.submit();
        }, 500);
    };


    // const handleSubmit = (e: React.FormEvent) => {
    //     e.preventDefault();
    //     if (invalidSeats.length) return;
    //
    //     const payload = {
    //         trip_id: Number(tripId),
    //         date,
    //         seats: selectedSeats.map(Number),
    //         solo,
    //         hold_token: holdToken || null,
    //         passengers: selectedSeats.map(n => ({
    //             seat: Number(n),
    //             category: passengers[n]?.category ?? "adult",
    //             extras: passengers[n]?.extras ?? [],
    //             first_name: passengers[n]?.firstName ?? "",
    //             last_name:  passengers[n]?.lastName ?? "",
    //             doc_number: passengers[n]?.docNumber ?? "",
    //         })),
    //         ...form,
    //         currency_code: currency,
    //         promo_code: promo?.trim() || undefined,
    //     };
    //
    //     // axios.post("/api/book-seat", payload).then(({ data }) => {
    //     //     setTicketPreview(data.ticket_preview);
    //     //     setPaymentForm(data.payment_form);
    //     //     if ((window as any).gtag) {
    //     //         (window as any).gtag('event', 'add_payment_info', {
    //     //             currency: 'UAH',
    //     //             // value: frontTotals.total,
    //     //             value: totalAfterPromoUAH,
    //     //             payment_type: 'wayforpay',
    //     //         });
    //     //     }
    //     //     setTimeout(() => {
    //     //         (document.querySelector('form[action*="liqpay"], form[action*="wayforpay"]') as HTMLFormElement | null)?.submit();
    //     //     }, 500);
    //     // });
    //
    //     axios.post("/api/book-seat", payload).then(async ({ data }) => {
    //         setTicketPreview(data.ticket_preview);
    //
    //         // === DEV: симулюємо успішну оплату ===
    //         if (DEV_MOCK_PAYMENT) {
    //             try {
    //                 // Стукаємо в dev-ендпойнт, який робить те саме, що вебхук успішного платежу
    //                 await axios.post("/api/dev/mock-paid", {
    //                     // якщо з /api/book-seat повертається order_reference — передай
    //                     order_reference: data.order_reference ?? null,
    //                     // те, що треба для створення юзера та підтвердження бронювання:
    //                     trip_id: Number(tripId),
    //                     date,
    //                     seats: selectedSeats.map(Number),
    //                     user: {
    //                         name: form.name,
    //                         surname: form.surname,
    //                         email: form.email,
    //                         phone: form.phone,
    //                         password: form.password,
    //                     },
    //                 });
    //             }  catch (e:any) {
    //                 const msg =
    //                     e?.response?.data?.message ||
    //                     Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
    //                     'Помилка бронювання';
    //                 alert(String(msg));
    //                 console.error(e?.response?.data || e);
    //             }
    //
    //             // Показуємо, що все “оплачено” й не відправляємо на платіжку
    //             setPaymentForm('<div class="p-3 rounded border border-green-300 bg-green-50">DEV: Оплату пропущено. Вважаємо успішною ✅</div>');
    //             return; // важливо: НЕ сабмітити платіжну форму
    //         }
    //
    //         // === ПРОД: звичайний шлях із реальним платіжним шлюзом ===
    //         setPaymentForm(data.payment_form);
    //         if ((window as any).gtag) {
    //             (window as any).gtag('event', 'add_payment_info', {
    //                 currency: 'UAH',
    //                 value: totalAfterPromoUAH,
    //                 payment_type: 'wayforpay',
    //             });
    //         }
    //         setTimeout(() => {
    //             (document.querySelector('form[action*="liqpay"], form[action*="wayforpay"]') as HTMLFormElement | null)?.submit();
    //         }, 500);
    //     });
    // };

    // ---- STANDBY: визначити sold-out та старт пред-авторизації ----
    const allSeatNumbers = useMemo(() => (
        layout.filter(s => s.type === 'seat' && s.number != null).map(s => String(s.number))
    ), [layout]);

    const soldOut = useMemo(() => {
        if (allSeatNumbers.length === 0) return false;
        return allSeatNumbers.every(n => bookedSeats.includes(n) || heldSeats.includes(n));
    }, [allSeatNumbers, bookedSeats, heldSeats]);

    const startStandby = async () => {
        // if (!tripId || !date) return;
        if (!busId || !date) return;

        // нативна перевірка полів форми
        if (standbyFormRef.current && !standbyFormRef.current.reportValidity()) return;

        const qty = Math.max(1, Math.min(6, Number(standbySeats) || 1));

        const payload = {
            // trip_id: Number(tripId),
            trip_id: Number(busId),
            date,
            seats: qty,
            allow_partial: !!allowPartial,
            name: form.name.trim(),
            surname: form.surname.trim(),
            email: form.email.trim(),
            phone: form.phone.trim(),
            currency_code: currency,
        };

        try {
            const { data } = await axios.post('/api/standby/start', payload);
            setStandbyFormHtml(data.payment_form);
            setTimeout(() => (document.getElementById('w4p-preauth') as HTMLFormElement|null)?.submit(), 200);
        } catch (e:any) {
            const msg = e?.response?.data?.message
                || Object.values(e?.response?.data?.errors || {})?.flat()?.[0]
                || 'Помилка при створенні preauth';
            alert(String(msg));
            console.error(e?.response?.data);
        }
    };


    // Якщо вже маємо форму оплати (звичайної або standby) — віддаємо її
    if (paymentForm) {
        return (
            <div className="t-prev">
                <div className="container">
                    <TicketPreview ticket={ticketPreview} />
                    <div dangerouslySetInnerHTML={{ __html: paymentForm }} />
                </div>
            </div>
        );
    }
    if (standbyFormHtml) {
        return <div dangerouslySetInnerHTML={{ __html: standbyFormHtml }} />;
    }

    if (!bus) return <div>Завантаження...</div>;

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
            <header className="bg-gradient-to-r from-brand to-brand-dark text-white">
                <div className="container mx-auto px-6 py-10">
                    <h1 className="text-4xl font-bold">Вибір місць</h1>
                </div>
            </header>

            <div className="container-svg">
                <div className="plane">
                    <svg xmlns="http://www.w3.org/2000/svg" xmlnsXlink="http://www.w3.org/1999/xlink" x="0px"
                         y="0px" viewBox="0 0 1024 1024">
                        <path id="followPath"
                              d="M394.1-214.9c-49.7,89.4,114.4,192.8,175.5,475.1c13,60.1,85.4,424-98.1,552.6 c-95.7,67-267.2,74.5-346.3-22.1c-70.8-86.5-49-233.9,19.2-305.2c102.4-107,353.9-89.1,593.2,96.5c139.6,107,294.1,258.4,415,468.6 c19.2,33.5,36.6,66.6,52.3,99.3c13,8.6,34,19.5,53.3,13.2c148-48.6,165.1-1094.5-338.5-1374.8C723.7-320.8,449-313.8,394.1-214.9z"></path>
                        <path id="dashedPath"
                              d="M394.1-214.9c-49.7,89.4,114.4,192.8,175.5,475.1c13,60.1,85.4,424-98.1,552.6 c-95.7,67-267.2,74.5-346.3-22.1c-70.8-86.5-49-233.9,19.2-305.2c102.4-107,353.9-89.1,593.2,96.5c139.6,107,294.1,258.4,415,468.6 c19.2,33.5,36.6,66.6,52.3,99.3c13,8.6,34,19.5,53.3,13.2c148-48.6,165.1-1094.5-338.5-1374.8C723.7-320.8,449-313.8,394.1-214.9z"></path>
                        <path id="airplain"
                              d="M32,18.9l-0.8-6.2c-0.2-1.5-1.5-2.6-3-2.6H1c-0.6,0-1,0.4-1,1v13c0,0.6,0.4,1,1,1h3.2c0.4,1.2,1.5,2,2.8,2  c1,0,2-0.5,2.5-1.3C10,26.5,11,27,12,27c1.3,0,2.4-0.8,2.8-2h7.4c0.4,1.2,1.5,2,2.8,2s2.4-0.8,2.8-2H31c0.6,0,1-0.4,1-1v-5  C32,19,32,18.9,32,18.9z M28.2,12c0.5,0,0.9,0.4,1,0.9l0.6,5.1h-1c-1.6,0-3-0.6-4.1-1.7C24.5,16.1,24.3,16,24,16H2v-4H28.2z M7,25  c-0.6,0-1-0.4-1-1c0,0,0,0,0,0s0,0,0,0c0-0.6,0.4-1,1-1c0.6,0,1,0.4,1,1S7.6,25,7,25z M12,25c-0.6,0-1-0.4-1-1s0.4-1,1-1s1,0.4,1,1  S12.6,25,12,25z M25,25c-0.6,0-1-0.4-1-1s0.4-1,1-1s1,0.4,1,1S25.6,25,25,25z">
                            <animateMotion xlinkHref="#airplain" dur="10s" fill="freeze" repeatCount="indefinite"
                                           rotate="auto">
                                <mpath xlinkHref="#followPath"></mpath>
                            </animateMotion>
                        </path>
                    </svg>
                </div>
            </div>

            <div className="max-w-6xl mx-auto bg-white p-12 rounded mt-40 mb-40 container">

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                    {/* LEFT */}
                    <div className="bk-wrapper wrapper-left space-y-6">
                        {!soldOut && (
                            <>
                                <label className="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" checked={solo} onChange={(e) => setSolo(e.target.checked)}/>
                                    Подорож без сусіда (−20% на друге місце)
                                </label>
                                {solo && selectedSeats.length === 1 && (
                                    <div className="text-xs text-orange-600">
                                        Поручнє місце не знайдено — знижка «без сусіда» не застосована.
                                    </div>
                                )}

                                {secondsLeft !== null && holdToken && (
                                    <div className="text-sm px-3 py-2 rounded bg-yellow-50 border border-yellow-300">
                                        Утримуємо ваші місця ще <b>{secondsLeft}s</b>. Підтвердьте замовлення.
                                    </div>
                                )}

                                <div className="public-bus-layout rounded-md border">
                                    <BusLayout
                                        seatLayout={layout}
                                        bookedSeats={bookedSeats}
                                        selectedSeats={selectedSeats}
                                        heldSeats={heldSeats}
                                        onSeatClick={handleSeatClick}
                                    />
                                </div>
                            </>
                        )}
                    </div>

                    {/* RIGHT */}
                    <div className="bk-wrapper wrapper-right space-y-6 lg:sticky lg:top-6">
                        {!soldOut && (
                            <>
                                {/* Масові дії */}
                                {!!selectedSeats.length && (
                                    <div className="flex items-center justify-between">
                                        <div className="text-lg font-semibold">Пасажири</div>
                                        <button
                                            type="button"
                                            className="text-sm underline hover:no-underline"
                                            onClick={fillAllFromContact}
                                        >
                                            Заповнити всіх як у контактних
                                        </button>
                                    </div>
                                )}

                                {/* Персональні налаштування та ПІБ по кожному місцю */}
                                {!!selectedSeats.length && (
                                    <div className="grid gap-3">
                                        {selectedSeats.map((n) => {
                                            const p = passengers[n] ?? {
                                                category: "adult",
                                                extras: [],
                                                firstName: "",
                                                lastName: ""
                                            };
                                            const line = frontTotals.lines.find((x) => x.seat === n);
                                            const isInvalid = !p.firstName.trim() || !p.lastName.trim();

                                            return (
                                                <div key={n}
                                                     className={`border rounded p-3 ${isInvalid ? "border-red-400" : ""}`}>
                                                    <div className="flex items-center justify-between">
                                                        <div className="font-semibold">Місце {n}</div>
                                                        <div
                                                            className="text-sm opacity-70">База: {seatBasePrice(n)} грн
                                                        </div>
                                                    </div>

                                                    {/* ПІБ + Документ */}
                                                    <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                        <input
                                                            className="p-2 border rounded"
                                                            placeholder="Прізвище пасажира"
                                                            value={p.lastName}
                                                            onChange={(e) => setPassengers(prev => ({
                                                                ...prev,
                                                                [n]: {...p, lastName: e.target.value}
                                                            }))}
                                                            required
                                                        />
                                                        <input
                                                            className="p-2 border rounded"
                                                            placeholder="Ім'я пасажира"
                                                            value={p.firstName}
                                                            onChange={(e) => setPassengers(prev => ({
                                                                ...prev,
                                                                [n]: {...p, firstName: e.target.value}
                                                            }))}
                                                            required
                                                        />
                                                        <input
                                                            className="p-2 border rounded sm:col-span-2"
                                                            placeholder="Документ (опційно): паспорт/ID"
                                                            value={p.docNumber ?? ""}
                                                            onChange={(e) => setPassengers(prev => ({
                                                                ...prev,
                                                                [n]: {...p, docNumber: e.target.value}
                                                            }))}
                                                        />
                                                    </div>

                                                    {/* Кнопка швидкого копіювання з контактних */}
                                                    <div className="mt-2">
                                                        <button
                                                            type="button"
                                                            className="text-xs underline hover:no-underline"
                                                            onClick={() =>
                                                                setPassengers(prev => ({
                                                                    ...prev,
                                                                    [n]: {
                                                                        ...p,
                                                                        firstName: form.name,
                                                                        lastName: form.surname
                                                                    }
                                                                }))
                                                            }
                                                        >
                                                            Заповнити з контактних
                                                        </button>
                                                    </div>

                                                    {/* Категорія */}
                                                    <div className="mt-3">
                                                        <label className="text-sm block mb-1">Категорія квитка</label>
                                                        <select
                                                            className="border rounded px-2 py-1"
                                                            value={p.category}
                                                            onChange={(e) => {
                                                                const v = e.target.value as "adult" | "child";
                                                                setPassengers(prev => ({
                                                                    ...prev,
                                                                    [n]: {...p, category: v}
                                                                }));
                                                            }}
                                                        >
                                                            <option value="adult">Дорослий</option>
                                                            <option value="child">Дитячий (-10%)</option>
                                                        </select>
                                                    </div>

                                                    {/* Додаткові послуги */}
                                                    <div className="mt-3">
                                                        <label className="text-sm block mb-1">Додаткові послуги</label>
                                                        <div className="flex flex-wrap gap-3">
                                                            {Object.entries(EXTRAS).map(([key, meta]) => {
                                                                const checked = (p.extras ?? []).includes(key);
                                                                return (
                                                                    <label key={key}
                                                                           className="inline-flex items-center gap-2">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={checked}
                                                                            onChange={(e) => {
                                                                                setPassengers(prev => {
                                                                                    const cur = p.extras ?? [];
                                                                                    const next = e.target.checked ? [...cur, key] : cur.filter(k => k !== key);
                                                                                    return {
                                                                                        ...prev,
                                                                                        [n]: {...p, extras: next}
                                                                                    };
                                                                                });
                                                                            }}
                                                                        />
                                                                        {meta.label} (+{meta.price} грн)
                                                                    </label>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>

                                                    {/* Підсумок по місцю */}
                                                    {line && (
                                                        <div className="mt-2 text-sm opacity-80">
                                                            Сидіння: {line.seatPart} грн ·
                                                            Послуги: {line.extrasPart} грн
                                                            <b className="ml-2">Разом: {line.lineTotal} грн</b>
                                                        </div>
                                                    )}

                                                    {isInvalid && (
                                                        <div className="text-xs text-red-600 mt-1">
                                                            Заповніть Ім’я та Прізвище пасажира.
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}

                                {/* Контакти + сабміт */}
                                <form onSubmit={handleSubmit} className="grid gap-4">
                                    <div className="text-xl font-semibold">Контактні дані</div>

                                    <input
                                        className="p-2 border rounded"
                                        required
                                        placeholder="Ім'я"
                                        value={form.name}
                                        onChange={(e) => setForm((f) => ({...f, name: e.target.value}))}
                                    />
                                    <input
                                        className="p-2 border rounded"
                                        required
                                        placeholder="Прізвище"
                                        value={form.surname}
                                        onChange={(e) => setForm((f) => ({...f, surname: e.target.value}))}
                                    />
                                    <input
                                        className="p-2 border rounded"
                                        required
                                        placeholder="Email"
                                        type="email"
                                        value={form.email}
                                        onChange={(e) => setForm((f) => ({...f, email: e.target.value}))}
                                    />
                                    <input
                                        type="tel"
                                        className="p-2 border rounded"
                                        required
                                        placeholder="Телефон"
                                        value={form.phone}
                                        onChange={(e) => setForm((f) => ({...f, phone: e.target.value}))}
                                        pattern="[\d\s()+-]{8,20}"
                                        title="Будь ласка, вкажіть коректний номер"
                                    />

                                    <input
                                        type="tel"
                                        className="p-2 border rounded"
                                        placeholder="Додатковий телефон (опційно)"
                                        value={form.phone_alt}
                                        onChange={(e) => setForm((f) => ({...f, phone_alt: e.target.value}))}
                                        pattern="[\d\s()+-]{0,20}"
                                        title="Будь ласка, вкажіть коректний номер"
                                    />
                                    <input
                                        className="p-2 border rounded"
                                        required
                                        placeholder="Пароль"
                                        type="password"
                                        value={form.password}
                                        onChange={(e) => setForm((f) => ({...f, password: e.target.value}))}
                                    />

                                    <div className="flex gap-3 items-center">
                                        <select value={currency} onChange={e => setCurrency(e.target.value as any)}
                                                className="border rounded px-2 py-1">
                                            {currencyOptions.map(c => <option key={c.code}
                                                                              value={c.code}>{c.code}</option>)}
                                        </select>
                                        <input className="border rounded px-2 py-1" placeholder="Промокод" value={promo}
                                               onChange={e => setPromo(e.target.value)}/>
                                    </div>

                                    <div className="text-right font-semibold">
                                        {promoPreview > 0 && (
                                            <div className="text-sm text-green-700 mb-1">Промокод:
                                                −{promoPreview} UAH</div>
                                        )}
                                        До сплати: {totalAfterPromoUAH} UAH
                                        {currency !== 'UAH' && <> • ~{totalAfterPromoConv} {currency}</>}
                                    </div>

                                    <button
                                        className="bg-brand text-white font-medium p-3 rounded hover:bg-brand-dark transition"
                                        type="submit"
                                        disabled={selectedSeats.length === 0 || invalidSeats.length > 0}
                                        title={invalidSeats.length ? "Заповніть ПІБ для всіх пасажирів" : undefined}
                                    >
                                        Перейти до оплати ({selectedSeats.length} місць)
                                    </button>
                                </form>
                            </>
                        )}

                        {soldOut && (
                            <form
                                ref={standbyFormRef}
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    startStandby();
                                }}
                                className="space-y-6"
                            >
                                {/* Контактні дані */}
                                <div className="border rounded p-4">
                                    <div className="text-xl font-semibold mb-3">Контактні дані</div>
                                    <div className="grid gap-3">
                                        <input className="p-2 border rounded" placeholder="Ім'я" required
                                               value={form.name}
                                               onChange={e => setForm(f => ({...f, name: e.target.value}))}/>
                                        <input className="p-2 border rounded" placeholder="Прізвище" required
                                               value={form.surname}
                                               onChange={e => setForm(f => ({...f, surname: e.target.value}))}/>
                                        <input className="p-2 border rounded" placeholder="Email" type="email" required
                                               value={form.email}
                                               onChange={e => setForm(f => ({...f, email: e.target.value}))}/>
                                        <input type="tel" className="p-2 border rounded" placeholder="Телефон" required
                                               pattern="[+\d\s\-()]{8,20}"
                                               value={form.phone}
                                               onChange={e => setForm(f => ({...f, phone: e.target.value}))}/>
                                        <div className="flex gap-3 items-center">
                                            <select value={currency} onChange={e => setCurrency(e.target.value as any)}
                                                    className="border rounded px-2 py-1">
                                                {currencyOptions.map(c => <option key={c.code}
                                                                                  value={c.code}>{c.code}</option>)}
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* Блок standby */}
                                <div className="border rounded p-4">
                                    <div className="font-semibold mb-2">Немає вільних місць</div>
                                    <div className="text-sm mb-3">
                                        Заблокуємо оплату… Якщо за 12 годин до поїздки місце не знайдеться — холд
                                        знімемо.
                                    </div>
                                    <div className="flex gap-2 items-center mb-2">
                                        <label>Кількість</label>
                                        <input type="number" min={1} max={6} value={standbySeats}
                                               onChange={e => setStandbySeats(Number(e.target.value))}
                                               className="border rounded px-2 py-1 w-20"/>
                                        <label className="text-sm inline-flex items-center gap-2">
                                            <input type="checkbox" checked={allowPartial}
                                                   onChange={e => setAllowPartial(e.target.checked)}/>
                                            Дозволити частково
                                        </label>
                                    </div>
                                    <button type="submit" className="bg-brand text-white font-medium px-3 py-2 rounded">
                                        Стати у чергу
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>

            <footer className="bg-white text-gray-300">
                <div className="container mx-auto px-6 py-8 grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <a href="/" className="footer-logo">
                            <img src="../../images/Asset-21.svg" alt=""/>
                        </a>
                        <p>© 2025 MaxBus. Всі права захищені.</p>
                    </div>
                    <div>
                        <h4 className="font-semibold mb-2 heading">Посилання</h4>
                        <ul className="space-y-1">
                            <li><a href="#" className="hover:text-white transition">Головна</a></li>
                            <li><a href="#" className="hover:text-white transition">Про нас</a></li>
                            <li><a href="#" className="hover:text-white transition">Контакти</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="font-semibold mb-2 heading">Контакти</h4>
                        <p>info@maxbus.com</p>
                        <p>+380 44 123 4567</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
