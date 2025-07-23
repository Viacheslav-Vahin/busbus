import React, {useEffect, useState} from 'react';
import axios from 'axios';
import {DayPicker} from 'react-day-picker';
import {format} from 'date-fns';
import 'react-day-picker/dist/style.css';
import {useNavigate} from 'react-router-dom';

interface Route {
    id: number;
    start_point: string;
    end_point: string;
}

export const BookingForm: React.FC = () => {
    const navigate = useNavigate();
    const [routes, setRoutes] = useState<Route[]>([]);
    const [departureCities, setDepartureCities] = useState<string[]>([]);
    const [arrivalCities, setArrivalCities] = useState<string[]>([]);
    const [selectedDeparture, setSelectedDeparture] = useState<string>('');
    const [selectedArrival, setSelectedArrival] = useState<string>('');
    const [availableDates, setAvailableDates] = useState<string[]>([]);
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(undefined);

    // 1) на завантаженні запитуємо усі маршрути
    useEffect(() => {
        console.log(axios.get<Route[]>('/api/routes'));
        axios.get<Route[]>('/api/routes')
            .then(({data}) => {
                setRoutes(data);
                setDepartureCities(Array.from(new Set(data.map(r => r.start_point))));
                setArrivalCities(Array.from(new Set(data.map(r => r.end_point))));
            });
    }, []);

    useEffect(() => {
        const route = routes.find(r =>
            r.start_point === selectedDeparture
            && r.end_point === selectedArrival,
        );
        if (!route) {
            setAvailableDates([]);
            return;
        }
        axios.get<string[]>(`/api/routes/${route.id}/available-dates`)
            .then(({data}) => setAvailableDates(data));
    }, [selectedDeparture, selectedArrival, routes]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const route = routes.find(r =>
            r.start_point === selectedDeparture &&
            r.end_point === selectedArrival
        );
        if (!route || !selectedDate) return;
        const dateStr = format(selectedDate, 'yyyy-MM-dd');
        navigate(`/search?routeId=${route.id}&date=${dateStr}`);
    };

    return (
        <form onSubmit={handleSubmit} className="main-form max-w-xl mx-auto bg-white p-8 rounded-lg shadow grid gap-6">
            <svg className="frto" xmlns="http://www.w3.org/2000/svg" version="1.1"
                 xmlnsXlink="http://www.w3.org/1999/xlink"
                 xmlns:svgjs="http://svgjs.dev/svgjs" viewBox="0 0 800 800">
                <g strokeWidth="10" stroke="#faa51a" fill="none" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M250 250Q619 268 400 400Q176 533 550 550 " markerEnd="url(#SvgjsMarker2074)"
                          markerStart="url(#SvgjsMarker2075)"></path>
                </g>
                <defs>
                    <marker markerWidth="8.5" markerHeight="8.5" refX="4.25" refY="4.25" viewBox="0 0 8.5 8.5"
                            orient="auto" id="SvgjsMarker2074">
                        <polygon points="0,8.5 2.8333333333333335,4.25 0,0 8.5,4.25" fill="#faa51a"></polygon>
                    </marker>
                    <marker markerWidth="8.5" markerHeight="8.5" refX="4.25" refY="4.25" viewBox="0 0 8.5 8.5"
                            orient="auto" id="SvgjsMarker2075">
                        <polygon points="0,4.25 8.5,0 5.666666666666667,4.25 8.5,8.5" fill="#faa51a"></polygon>
                    </marker>
                </defs>
            </svg>
            <select
                value={selectedDeparture}
                onChange={e => setSelectedDeparture(e.target.value)}
                className="w-full p-4 border rounded-lg"
            >
                <option value="">Оберіть місто виїзду</option>
                {departureCities.map(city => (
                    <option key={city} value={city}>{city}</option>
                ))}
            </select>

            <select
                value={selectedArrival}
                onChange={e => setSelectedArrival(e.target.value)}
                className="w-full p-4 border rounded-lg"
                disabled={!selectedDeparture}
            >
                <option value="">Оберіть місто прибуття</option>
                {arrivalCities
                    .filter(city =>
                        // опціонально — показати тільки ті напрямки, що доступні з обраного departure
                        routes.some(r =>
                            r.start_point === selectedDeparture
                            && r.end_point === city,
                        ),
                    )
                    .map(city => (
                        <option key={city} value={city}>{city}</option>
                    ))}
            </select>

            <div>
                <DayPicker
                    mode="single"
                    selected={selectedDate}
                    onSelect={setSelectedDate}
                    disabled={[
                        // всі дати до сьогодні
                        {before: new Date()},
                        // і ті, що не входять в availableDates
                        date => {
                            const d = format(date, 'yyyy-MM-dd');
                            return !availableDates.includes(d);
                        },
                    ]}
                    footer={
                        !selectedDate && <p className="text-sm text-red-600">Оберіть доступну дату</p>
                    }
                />
            </div>

            <button
                type="submit"
                className="bg-brand text-white font-medium p-4 rounded-lg shadow hover:bg-brand-dark transition"
                disabled={!selectedDate}
            >
                Пошук автобусів
            </button>
        </form>
    );
};

const IndexPage: React.FC = () => {
    return (
        <div className="flex flex-col min-h-screen bg-gray-50 text-gray-900">

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
            <header
                className="main-header bg-gradient-to-r from-brand to-brand-dark text-white flex-1 flex items-center">
                <div className="header-container container mx-auto px-6 py-40 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 className="text-4xl md:text-5xl font-extrabold mb-4 leading-tight heading">
                        Забронюйте поїздку зараз
                        </h1>
                        <p className="text-lg mb-6 max-w-md">
                            Швидка та зручна система бронювання автобусів.
                        </p>
                        <a
                            href="#booking-form"
                            className="inline-block bg-white text-brand-dark font-semibold px-8 py-3 rounded-lg shadow hover:shadow-lg transition"
                        >
                            Розпочати бронювання
                        </a>
                    </div>
                    <div className="flex justify-center">
                        <div className="hcard">
                            <span className="icon">
                              <i className="fa fa-phone"></i>
                            </span>
                            <div className="content-wrap">
                                  <span className="item-title">
                                    Зв'яжіться з нами
                                  </span>
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

            <svg className="hero-line" id="visual" viewBox="0 450 900 150" width="900" height="150" xmlns="http://www.w3.org/2000/svg"
                 xmlnsXlink="http://www.w3.org/1999/xlink" version="1.1">
                <path d="M0 524L129 537L257 511L386 544L514 536L643 527L771 550L900 528" fill="none"
                      strokeLinecap="square" strokeLinejoin="bevel" stroke="#faa51a" strokeWidth="40"></path>
            </svg>

            {/* Booking Form */}
            <section id="booking-form" className="container mx-auto px-6 py-16">
                <h2 className="text-3xl font-bold text-center mb-8 text-brand-dark heading">Форма бронювання</h2>
                <BookingForm/>
            </section>

            {/* Footer */}
            <footer className="bg-brand-dark text-gray-300">
                <div className="container mx-auto px-6 py-8 grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <h3 className="text-xl font-semibold mb-2 text-white heading">MaxBus</h3>
                        <p>© 2025 MaxBus. Всі права захищені.</p>
                    </div>
                    <div>
                        <h4 className="font-semibold mb-2 text-white heading">Посилання</h4>
                        <ul className="space-y-1">
                            <li><a href="#" className="hover:text-white transition">Головна</a></li>
                            <li><a href="#" className="hover:text-white transition">Про нас</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="font-semibold mb-2 text-white heading">Контакти</h4>
                        <p>info@maxbus.com</p>
                        <p>+380 44 123 4567</p>
                    </div>
                </div>
            </footer>
            <link src="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css"/>
        </div>
    );
};

export default IndexPage;
