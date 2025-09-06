// resources/js/pages/SearchResultsPage.tsx
import React, {useEffect, useState} from 'react';
import axios from 'axios';
import {useLocation, Link} from 'react-router-dom';
import queryString from 'query-string';

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
    const {search} = useLocation();
    const {routeId, date} = queryString.parse(search);
    const [trips, setTrips] = useState<Trip[]>([]);
    const [loading, setLoading] = useState(true);
    const dateStr = String(date ?? '');
    const routeIdNum = Number(routeId);

    useEffect(() => {
        if (!routeId || !date) return;

        // 𝗘𝗡𝗗𝗣𝗢𝗜𝗡𝗧: викликаємо POST /get-buses-by-date
        axios.post('/get-buses-by-date', {
            route_id: routeIdNum,
            date: dateStr,
        })
            .then(({data}) => {
                console.log("API returns", data);
                const arr = Array.isArray(data) ? data : Array.isArray(data?.trips) ? data.trips : [];
                const mapped = arr.map((item: any) => ({
                    trip_id: item.trip_id,
                    bus_id:  item.bus_id,
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
            .finally(() => {
                setLoading(false);
            });
    }, [routeId, date]);

    if (loading) {
        return <div className="p-8 text-center">Завантаження...</div>;
    }
    if (!trips.length) {
        return <div className="p-8 text-center">Рейси не знайдено.</div>;
    }

    return (
        <div className="page-wrapper">

            {/* Navigation */}
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

            {/* Hero Section */}
            <header className="bg-gradient-to-r from-brand to-brand-dark text-white flex-1 flex items-center">
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

            <div className="container mx-auto py-8 pt-40 pb-40">
                <ul className="space-y-4">
                    {trips.map((trip) => (
                        <li key={`${trip.trip_id}-${trip.bus_id}`} className="card bg-white p-6 rounded-lg shadow flex justify-between">
                            <div>
                                <p>Назва автобуса: <span className="font-semibold">{trip.bus_name}</span></p>
                                <p>Звідки: <span className="font-semibold">{trip.start_location}</span> → Куди: <span
                                    className="font-semibold">{trip.end_location}</span>
                                </p>
                                <p>Час відправлення: <span className="font-semibold">{trip.departure_time}</span> – Час
                                    прибуття: <span className="font-semibold">{trip.arrival_time}</span>
                                </p>
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
};
export default SearchResultsPage;
