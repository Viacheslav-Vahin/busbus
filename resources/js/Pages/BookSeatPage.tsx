import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { useLocation, useNavigate } from 'react-router-dom';
import queryString from 'query-string';

interface Seat {
    number: string;
    price: number;
    type: string; // seat, wc, coffee, driver, etc.
    booked: boolean;
}

interface Bus {
    id: number;
    name: string;
    seat_layout: Seat[];
    seats_count: number;
    // ...інші потрібні поля
}

const BookSeatPage: React.FC = () => {
    const { search } = useLocation();
    const { tripId, date } = queryString.parse(search);
    const [bus, setBus] = useState<Bus | null>(null);
    const [selectedSeats, setSelectedSeats] = useState<string[]>([]);
    const [userData, setUserData] = useState({
        name: '',
        surname: '',
        email: '',
        phone: '',
        password: '',
    });
    const [loading, setLoading] = useState(true);

    // Завантажити схему місць автобуса та заброньовані місця
    useEffect(() => {
        if (!tripId || !date) return;
        axios.get(`/api/trip/${tripId}/bus-info`, { params: { date } })
            .then(({ data }) => setBus(data))
            .finally(() => setLoading(false));
    }, [tripId, date]);

    const handleSeatClick = (seatNumber: string) => {
        setSelectedSeats(prev =>
            prev.includes(seatNumber)
                ? prev.filter(s => s !== seatNumber)
                : [...prev, seatNumber]
        );
    };

    // Збереження даних користувача з інпутів
    const handleUserChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setUserData({ ...userData, [e.target.name]: e.target.value });
    };

    // Обробка бронювання та оплати
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // TODO: API для створення бронювання та інтеграції з оплатою
        // Якщо не залогінений — реєструвати і одразу логінити
    };

    if (loading) return <div className="p-10 text-center">Завантаження…</div>;
    if (!bus) return <div className="p-10 text-center text-red-600">Автобус не знайдено.</div>;

    return (
        <div className="container mx-auto py-12">
            <h1 className="text-3xl font-bold mb-6">Бронювання місць: {bus.name}</h1>
            {/* Відображення схеми місць */}
            <div className="grid grid-cols-8 gap-2 mb-8">
                {bus.seat_layout.map(seat => (
                    <button
                        key={seat.number}
                        disabled={seat.booked || seat.type !== 'seat'}
                        onClick={() => handleSeatClick(seat.number)}
                        className={
                            `rounded-lg px-3 py-2 border text-sm
                            ${selectedSeats.includes(seat.number) ? 'bg-brand text-white' : 'bg-gray-100'}
                            ${seat.booked ? 'bg-gray-300 cursor-not-allowed' : ''}
                            ${seat.type !== 'seat' ? 'opacity-40' : ''}`
                        }
                    >
                        {seat.number}
                    </button>
                ))}
            </div>

            <form onSubmit={handleSubmit} className="space-y-4 max-w-md">
                <input name="name" value={userData.name} onChange={handleUserChange} className="w-full p-2 border rounded" placeholder="Імʼя" required />
                <input name="surname" value={userData.surname} onChange={handleUserChange} className="w-full p-2 border rounded" placeholder="Прізвище" required />
                <input name="email" value={userData.email} onChange={handleUserChange} className="w-full p-2 border rounded" placeholder="Email" type="email" required />
                <input name="phone" value={userData.phone} onChange={handleUserChange} className="w-full p-2 border rounded" placeholder="Телефон" required />
                {/* Якщо не залогінений — поле пароль */}
                <input name="password" value={userData.password} onChange={handleUserChange} className="w-full p-2 border rounded" placeholder="Пароль для кабінету" type="password" autoComplete="new-password" />
                <button type="submit" className="bg-brand text-white px-6 py-2 rounded font-semibold">
                    Перейти до оплати
                </button>
            </form>
        </div>
    );
};

export default BookSeatPage;
