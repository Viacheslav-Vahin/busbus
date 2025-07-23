// resources/js/pages/BookingPage.tsx
import React, { useEffect, useState } from "react";
import axios from "axios";
import { useSearchParams } from "react-router-dom";
import BusLayout from "./BusLayout";

function TicketPreview({ticket}) {
    return (
        <div className="ticket-preview" style={{
            background: '#fffbe7',
            border: '2px solid orange',
            borderRadius: 20,
            marginBottom: 24,
            padding: 24,
            maxWidth: 400,
            margin: '0 auto'
        }}>
            <h3 style={{marginBottom: 12, fontSize: 22, fontWeight: 600}}>Квиток на автобус</h3>
            <div><b>Маршрут:</b> {ticket.route}</div>
            <div><b>Автобус:</b> {ticket.bus}</div>
            <div><b>Дата:</b> {ticket.date}</div>
            <div><b>Місця:</b> {ticket.seats.join(', ')}</div>
            <div><b>ПІБ:</b> {ticket.name} {ticket.surname}</div>
            <div style={{fontSize: 18, fontWeight: 600, color: 'crimson', marginTop: 10}}>До сплати: {ticket.price} грн</div>
        </div>
    );
}

export default function BookingPage() {
    const [searchParams] = useSearchParams();
    const tripId = searchParams.get('tripId');
    const date = searchParams.get('date');
    const [bus, setBus] = useState<any>(null);
    const [bookedSeats, setBookedSeats] = useState<string[]>([]);
    const [selectedSeats, setSelectedSeats] = useState<string[]>([]);
    const [form, setForm] = useState({
        name: "",
        surname: "",
        email: "",
        phone: "",
        password: ""
    });
    const [paymentForm, setPaymentForm] = useState<string | null>(null);
    const [ticketPreview, setTicketPreview] = useState(null);

    // 1. Завантажуємо автобус і заброньовані місця
    useEffect(() => {
        if (!tripId || !date) return;
        axios.get(`/api/trip/${tripId}/bus-info?date=${date}`)
            .then(({data}) => {
                setBus(data.bus);
                setBookedSeats(data.booked_seats);
            });
    }, [tripId, date]);

    // 2. Вибір місця
    const handleSeatClick = (number: string) => {
        if (bookedSeats.includes(number)) return;
        setSelectedSeats(prev => prev.includes(number)
            ? prev.filter(n => n !== number)
            : [...prev, number]);
    };

    // 3. Сабміт на бронювання
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        axios.post("/api/book-seat", {
            trip_id: tripId,
            date,
            seats: selectedSeats,
            ...form
        }).then(({data}) => {
            setTicketPreview(data.ticket_preview);
            setPaymentForm(data.payment_form);
            // Автосабміт LiqPay-форми:
            setTimeout(() => {
                document.querySelector('form[action*="liqpay"]')?.submit();
            }, 500);
        });
    };

    if (paymentForm) {
        // Після отримання форми — показуємо її і сабмітимо
        // return <div dangerouslySetInnerHTML={{__html: paymentForm}} />;
        return (
            <div className="t-prev">
                <div className="container">
                    <TicketPreview ticket={ticketPreview} />
                    <div dangerouslySetInnerHTML={{__html: paymentForm}} />
                </div>
            </div>
        );
    }

    if (!bus) return <div>Завантаження...</div>;

    return (
        <div className="max-w-xl mx-auto bg-white p-8 rounded-lg shadow mt-10">
            <h2 className="text-2xl font-bold mb-4">Вибір місць</h2>
           <div className="public-bus-layout">
                <BusLayout
                    seatLayout={bus.seat_layout}
                    bookedSeats={bookedSeats}
                    selectedSeats={selectedSeats}
                    onSeatClick={handleSeatClick}
                />
           </div>
            <form onSubmit={handleSubmit} className="grid gap-4">
                <input className="p-2 border rounded" required placeholder="Ім'я" value={form.name} onChange={e => setForm(f => ({...f, name: e.target.value}))} />
                <input className="p-2 border rounded" required placeholder="Прізвище" value={form.surname} onChange={e => setForm(f => ({...f, surname: e.target.value}))} />
                <input className="p-2 border rounded" required placeholder="Email" type="email" value={form.email} onChange={e => setForm(f => ({...f, email: e.target.value}))} />
                <input className="p-2 border rounded" required placeholder="Телефон" value={form.phone} onChange={e => setForm(f => ({...f, phone: e.target.value}))} />
                <input className="p-2 border rounded" required placeholder="Пароль" type="password" value={form.password} onChange={e => setForm(f => ({...f, password: e.target.value}))} />
                <button
                    className="bg-brand text-white font-medium p-3 rounded hover:bg-brand-dark transition"
                    type="submit"
                    disabled={selectedSeats.length === 0}
                >
                    Перейти до оплати ({selectedSeats.length} місць)
                </button>
            </form>
        </div>
    );
}
