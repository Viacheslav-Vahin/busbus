// // resources/js/pages/SearchResultsPage.tsx
// import React, { useEffect, useState } from 'react';
// import axios from 'axios';
// import { useLocation, Link } from 'react-router-dom';
// import queryString from 'query-string';
//
// interface SearchResult {
//     idFrom: number;
//     idTo: number;
//     oneWayPrice: number;
//     currency: string;
//     routeName: string;
//     departureTime: string;
//     departureDate: string;
//     arrivalTime: string;
//     arrivalDate: string;
//     freeSeats: number;
//     maxSeats: number;
//     rating: number;
//     bonus: number;
//     carrier: string;
//     carrierRating: number;
//     // …
// }
//
// export default function SearchResultsPage() {
//     const { routeId, date } = queryString.parse(useLocation().search);
//     const [results, setResults] = useState<SearchResult[]>([]);
//     const [loading, setLoading] = useState(true);
//
//     useEffect(() => {
//         if (!routeId || !date) return;
//         axios
//             .get<SearchResult[]>('/api/search', { params: { route_id: routeId, date } })
//             .then(({ data }) => setResults(data))
//             .finally(() => setLoading(false));
//     }, [routeId, date]);
//
//     if (loading) return <div className="text-center py-20">Завантаження…</div>;
//     if (!results.length) return <div className="text-center py-20">Рейси не знайдено.</div>;
//
//     return (
//         <div className="container mx-auto px-6 py-12">
//             <h2 className="text-2xl font-bold mb-8">Результати пошуку</h2>
//             <div className="space-y-6">
//                 {results.map(r => (
//                     <div key={`${r.idFrom}-${r.idTo}-${r.departureTime}`} className="bg-white rounded-lg shadow p-6 grid md:grid-cols-3 gap-4">
//                         {/* Ліва колонка: час + маршрути */}
//                         <div>
//                             <div className="text-sm text-gray-500">{r.departureDate}</div>
//                             <div className="text-2xl font-semibold">{r.departureTime}</div>
//                             <div className="mt-2 text-gray-700">{r.routeName}</div>
//                             <div className="mt-4 text-gray-500">
//                                 Доступно місць: <span className="font-semibold">{r.freeSeats}</span> / {r.maxSeats}
//                             </div>
//                         </div>
//                         {/* Центр: час прибуття */}
//                         <div className="text-center">
//                             <div className="text-sm text-gray-500">{r.arrivalDate}</div>
//                             <div className="text-2xl font-semibold">{r.arrivalTime}</div>
//                             <div className="mt-2 flex items-center justify-center text-yellow-500">
//                                 <svg className="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927C9.37 2.07 10.63 2.07 10.951 2.927l.89 2.707a1 1 0 00.95.69h2.847c.969 0 1.371 1.24.588 1.81l-2.303 1.67a1 1 0 00-.364 1.118l.89 2.708c.321.857-.755 1.57-1.538 1.118l-2.303-1.67a1 1 0 00-1.175 0l-2.303 1.67c-.783.452-1.859-.261-1.538-1.118l.89-2.708a1 1 0 00-.364-1.118L2.776 8.134c-.783-.57-.38-1.81.588-1.81h2.847a1 1 0 00.95-.69l.89-2.707z"/></svg>
//                                 {r.rating.toFixed(1)}
//                             </div>
//                         </div>
//                         {/* Права: ціна + кнопка */}
//                         <div className="text-right space-y-2">
//                             <div className="text-gray-500">Ціна:</div>
//                             <div className="text-3xl font-bold">{r.oneWayPrice} <span className="text-xl">{r.currency}</span></div>
//                             <Link
//                                 to={`/booking?routeId=${routeId}&date=${date}&from=${r.idFrom}&to=${r.idTo}`}
//                                 className="inline-block mt-4 bg-brand text-white font-medium px-6 py-2 rounded hover:bg-brand-dark transition"
//                             >
//                                 Бронювати
//                             </Link>
//                         </div>
//                     </div>
//                 ))}
//             </div>
//         </div>
//     );
// }
// resources/js/Pages/SearchResultsPage.tsx
// resources/js/pages/SearchResultsPage.tsx
import React, {useEffect, useState} from 'react';
import axios from 'axios';
import {useLocation, Link} from 'react-router-dom';
import queryString from 'query-string';

interface Trip {
    id: number;
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

    useEffect(() => {
        if (!routeId || !date) return;

        // 𝗘𝗡𝗗𝗣𝗢𝗜𝗡𝗧: викликаємо POST /get-buses-by-date
        axios.post('/get-buses-by-date', {
            route_id: routeId,
            date: date,
        })
            .then(({data}) => {
                console.log("API returns", data);
                const mapped = data.map((item: any) => ({
                    id: item.id,
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
                <nav className="bg-brand-dark text-white shadow-md">
                    <div className="container mx-auto px-6 py-4 flex justify-between items-center">
                        <div className="text-2xl font-bold">MaxBus</div>
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

                <div className="container mx-auto py-8">
                    <ul className="space-y-4">
                        {trips.map((trip) => (
                            <li key={trip.id} className="card bg-white p-6 rounded-lg shadow flex justify-between">
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
                                    to={`/book?tripId=${trip.id}&date=${date}`}
                                    className="mt-2 inline-block bg-brand px-4 py-2 rounded text-white hover:bg-brand-dark transition"
                                >
                                    Бронювати
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
};

export default SearchResultsPage;
