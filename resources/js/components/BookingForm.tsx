// // resources/js/components/BookingForm.tsx
//
// import React, { useEffect, useState } from 'react';
// import { useNavigate } from 'react-router-dom';
// import axios from 'axios';
// import { DayPicker } from 'react-day-picker';
// import { format } from 'date-fns';
// import { startOfToday } from 'date-fns';
// import 'react-day-picker/dist/style.css';
//
// interface Route {
//     id: number;
//     start_point: string;
//     end_point: string;
// }
//
// export const BookingForm: React.FC = () => {
//     const navigate = useNavigate();
//
//     const [routes, setRoutes] = useState<Route[]>([]);
//     const [departureCities, setDepartureCities] = useState<string[]>([]);
//     const [arrivalCities, setArrivalCities] = useState<string[]>([]);
//     const [selectedDeparture, setSelectedDeparture] = useState<string>('');
//     const [selectedArrival, setSelectedArrival] = useState<string>('');
//     const [availableDates, setAvailableDates] = useState<string[]>([]);
//     const [selectedDate, setSelectedDate] = useState<Date | undefined>(undefined);
//     const routeSelected = !!(selectedDeparture && selectedArrival);
//     const availableSet  = React.useMemo(() => new Set(availableDates), [availableDates]);
//
//     // Fetch all routes on mount
//     useEffect(() => {
//         axios.get<Route[]>('/api/routes')
//             .then(({ data }) => {
//                 setRoutes(data);
//                 setDepartureCities(Array.from(new Set(data.map(r => r.start_point))));
//                 setArrivalCities(Array.from(new Set(data.map(r => r.end_point))));
//             })
//             .catch(console.error);
//     }, []);
//
//     // Fetch available dates whenever departure + arrival change
//     useEffect(() => {
//         const route = routes.find(r =>
//             r.start_point === selectedDeparture && r.end_point === selectedArrival,
//         );
//         if (!route) { setAvailableDates([]); return; }
//
//         axios.get<string[]>(`/api/routes/${route.id}/available-dates`)
//             .then(({ data }) => {
//                 const list = (Array.isArray(data) ? data : []).map(s => String(s).slice(0, 10));
//                 setAvailableDates(list); // завжди 'YYYY-MM-DD'
//             });
//     }, [selectedDeparture, selectedArrival, routes]);
//
//     const handleSubmit = (e: React.FormEvent) => {
//         e.preventDefault();
//         const route = routes.find(r =>
//             r.start_point === selectedDeparture &&
//             r.end_point === selectedArrival
//         );
//         if (!route || !selectedDate) return;
//         const dateStr = format(selectedDate, 'yyyy-MM-dd');
//         navigate(`/search?routeId=${route.id}&date=${dateStr}`);
//     };
//
//     return (
//         <form onSubmit={handleSubmit} className="max-w-xl mx-auto bg-white p-8 rounded-lg shadow grid gap-6">
//             <select
//                 value={selectedDeparture}
//                 onChange={e => setSelectedDeparture(e.target.value)}
//                 className="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand transition"
//             >
//                 <option value="">Оберіть місто виїзду</option>
//                 {departureCities.map(city => (
//                     <option key={city} value={city}>{city}</option>
//                 ))}
//             </select>
//
//             <select
//                 value={selectedArrival}
//                 onChange={e => setSelectedArrival(e.target.value)}
//                 className="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand transition"
//                 disabled={!selectedDeparture}
//             >
//                 <option value="">Оберіть місто прибуття</option>
//                 {arrivalCities
//                     .filter(city =>
//                         routes.some(r =>
//                             r.start_point === selectedDeparture &&
//                             r.end_point === city
//                         )
//                     )
//                     .map(city => (
//                         <option key={city} value={city}>{city}</option>
//                     ))
//                 }
//             </select>
//
//             <div>
//                 <DayPicker
//                     key={`${selectedDeparture}-${selectedArrival}`}
//                     mode="single"
//                     selected={selectedDate}
//                     onSelect={setSelectedDate}
//                     fromDate={startOfToday()} // не дає йти у минуле
//                     disabled={[
//                         { before: startOfToday() },
//                         // ⬇️ тільки коли вже є обраний напрямок і завантажені дні
//                         ...(routeSelected ? [
//                             (date: Date) => !availableSet.has(format(date, 'yyyy-MM-dd'))
//                         ] : []),
//                     ]}
//                     footer={
//                         !routeSelected
//                             ? <p className="text-sm text-orange-600">Спочатку оберіть напрямок</p>
//                             : !selectedDate && <p className="text-sm text-red-600">Оберіть доступну дату</p>
//                     }
//                 />
//             </div>
//
//             <button
//                 type="submit"
//                 className="bg-brand text-white font-medium p-4 rounded-lg shadow hover:bg-brand-dark transition"
//                 disabled={!selectedDate || !selectedDeparture || !selectedArrival}
//             >
//                 Пошук автобусів
//             </button>
//         </form>
//     );
// };
// resources/js/components/BookingForm.tsx
import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { DayPicker } from 'react-day-picker';
import { format, startOfToday } from 'date-fns';
import 'react-day-picker/dist/style.css';

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

    // додано для UI (календар у поповері)
    const [showCalendar, setShowCalendar] = useState(false);

    const routeSelected = !!(selectedDeparture && selectedArrival);
    const availableSet = React.useMemo(() => new Set(availableDates), [availableDates]);

    /* ===== Fetch all routes on mount (залишено як було) ===== */
    useEffect(() => {
        axios
            .get<Route[]>('/api/routes')
            .then(({ data }) => {
                setRoutes(data);
                setDepartureCities(Array.from(new Set(data.map((r) => r.start_point))));
                setArrivalCities(Array.from(new Set(data.map((r) => r.end_point))));
            })
            .catch(console.error);
    }, []);

    /* ===== Fetch available dates whenever departure + arrival change (залишено як було) ===== */
    useEffect(() => {
        const route = routes.find(
            (r) => r.start_point === selectedDeparture && r.end_point === selectedArrival,
        );
        if (!route) {
            setAvailableDates([]);
            return;
        }

        axios.get<string[]>(`/api/routes/${route.id}/available-dates`).then(({ data }) => {
            const list = (Array.isArray(data) ? data : []).map((s) => String(s).slice(0, 10));
            setAvailableDates(list); // завжди 'YYYY-MM-DD'
        });
    }, [selectedDeparture, selectedArrival, routes]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const route = routes.find(
            (r) => r.start_point === selectedDeparture && r.end_point === selectedArrival,
        );
        if (!route || !selectedDate) return;
        const dateStr = format(selectedDate, 'yyyy-MM-dd');
        navigate(`/search?routeId=${route.id}&date=${dateStr}`);
    };

    // кнопка "поміняти міста"
    const swapCities = () => {
        if (!selectedDeparture && !selectedArrival) return;
        setSelectedDeparture(selectedArrival);
        setSelectedArrival(selectedDeparture);
        setSelectedDate(undefined);
        setShowCalendar(false);
    };

    const filteredArrivals = arrivalCities.filter((city) =>
        routes.some((r) => r.start_point === selectedDeparture && r.end_point === city),
    );

    return (
        <form onSubmit={handleSubmit} className="relative">
            {/* широка пошукова панель */}
            <div className="w-full rounded-2xl bg-white/95 backdrop-blur shadow-xl ring-1 ring-black/5 px-4 py-4 md:px-6 md:py-6">
                <div
                    className="
            grid gap-3 items-end
            md:grid-cols-[1fr_auto_1fr_auto_auto]
          "
                >
                    {/* FROM */}
                    <div className="flex flex-col">
                        <label className="text-sm font-medium text-gray-600 mb-1">Звідки</label>
                        <select
                            value={selectedDeparture}
                            onChange={(e) => {
                                setSelectedDeparture(e.target.value);
                                // UX: якщо змінюємо місто виїзду — очищаємо напрямок/дату
                                setSelectedArrival('');
                                setSelectedDate(undefined);
                                setShowCalendar(false);
                            }}
                            className="h-14 w-full rounded-xl border border-gray-300 px-4 text-base focus:outline-none focus:ring-2 focus:ring-brand/60"
                        >
                            <option value="">Оберіть місто виїзду</option>
                            {departureCities.map((city) => (
                                <option key={city} value={city}>
                                    {city}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* SWAP */}
                    <div className="hidden md:flex items-center justify-center pb-1">
                        <button
                            type="button"
                            onClick={swapCities}
                            title="Поміняти міста місцями"
                            className="h-12 w-12 rounded-full border border-gray-300 hover:bg-gray-50 grid place-items-center"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M4 7h11M10 3l4 4-4 4" />
                                <path d="M20 17H9M13 21l-4-4 4-4" />
                            </svg>
                        </button>
                    </div>

                    {/* TO */}
                    <div className="flex flex-col">
                        <label className="text-sm font-medium text-gray-600 mb-1">Куди</label>
                        <select
                            value={selectedArrival}
                            onChange={(e) => {
                                setSelectedArrival(e.target.value);
                                setSelectedDate(undefined);
                                setShowCalendar(false);
                            }}
                            className="h-14 w-full rounded-xl border border-gray-300 px-4 text-base focus:outline-none focus:ring-2 focus:ring-brand/60 disabled:bg-gray-100"
                            disabled={!selectedDeparture}
                        >
                            <option value="">
                                {selectedDeparture ? 'Оберіть місто прибуття' : 'Спочатку оберіть звідки'}
                            </option>
                            {filteredArrivals.map((city) => (
                                <option key={city} value={city}>
                                    {city}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* DATE (кнопка + поповер з DayPicker) */}
                    <div className="flex flex-col relative">
                        <label className="text-sm font-medium text-gray-600 mb-1">Дата</label>
                        <button
                            type="button"
                            onClick={() => setShowCalendar((s) => !s)}
                            disabled={!routeSelected}
                            className="h-14 w-full rounded-xl border border-gray-300 px-4 text-left text-base focus:outline-none focus:ring-2 focus:ring-brand/60 disabled:bg-gray-100"
                        >
                            {selectedDate ? format(selectedDate, 'dd MMM yyyy') : 'Оберіть дату'}
                        </button>

                        {showCalendar && (
                            <div className="absolute z-50 top-full mt-2 left-0 bg-white rounded-xl shadow-2xl ring-1 ring-black/5 p-2">
                                <DayPicker
                                    key={`${selectedDeparture}-${selectedArrival}`} // щоб перерендерювати при зміні напрямку
                                    mode="single"
                                    selected={selectedDate}
                                    onSelect={(d) => {
                                        setSelectedDate(d);
                                        if (d) setShowCalendar(false);
                                    }}
                                    fromDate={startOfToday()}
                                    disabled={[
                                        { before: startOfToday() },
                                        ...(routeSelected
                                            ? [(date: Date) => !availableSet.has(format(date, 'yyyy-MM-dd'))]
                                            : []),
                                    ]}
                                    footer={
                                        !routeSelected ? (
                                            <p className="text-sm text-orange-600 px-2">Спочатку оберіть напрямок</p>
                                        ) : !selectedDate ? (
                                            <p className="text-sm text-red-600 px-2">Оберіть доступну дату</p>
                                        ) : undefined
                                    }
                                />
                            </div>
                        )}
                    </div>

                    {/* ACTION */}
                    <div className="lg:pl-2">
                        <button
                            type="submit"
                            className="h-14 w-full lg:w-auto px-8 rounded-xl bg-brand text-white font-semibold shadow hover:bg-brand-dark transition disabled:opacity-60"
                            disabled={!selectedDate || !selectedDeparture || !selectedArrival}
                        >
                            Пошук автобусів
                        </button>
                    </div>
                </div>
            </div>
        </form>
    );
};
