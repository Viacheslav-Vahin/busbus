// resources/js/components/BookingForm.tsx

import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { DayPicker } from 'react-day-picker';
import { format } from 'date-fns';
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

    // Fetch all routes on mount
    useEffect(() => {
        axios.get<Route[]>('/api/routes')
            .then(({ data }) => {
                setRoutes(data);
                setDepartureCities(Array.from(new Set(data.map(r => r.start_point))));
                setArrivalCities(Array.from(new Set(data.map(r => r.end_point))));
            })
            .catch(console.error);
    }, []);

    // Fetch available dates whenever departure + arrival change
    useEffect(() => {
        const route = routes.find(r =>
            r.start_point === selectedDeparture &&
            r.end_point === selectedArrival
        );
        if (!route) {
            setAvailableDates([]);
            return;
        }
        axios.get<string[]>(`/api/routes/${route.id}/available-dates`)
            .then(({ data }) => setAvailableDates(data))
            .catch(console.error);
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
        <form onSubmit={handleSubmit} className="max-w-xl mx-auto bg-white p-8 rounded-lg shadow grid gap-6">
            <select
                value={selectedDeparture}
                onChange={e => setSelectedDeparture(e.target.value)}
                className="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand transition"
            >
                <option value="">Оберіть місто виїзду</option>
                {departureCities.map(city => (
                    <option key={city} value={city}>{city}</option>
                ))}
            </select>

            <select
                value={selectedArrival}
                onChange={e => setSelectedArrival(e.target.value)}
                className="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand transition"
                disabled={!selectedDeparture}
            >
                <option value="">Оберіть місто прибуття</option>
                {arrivalCities
                    .filter(city =>
                        routes.some(r =>
                            r.start_point === selectedDeparture &&
                            r.end_point === city
                        )
                    )
                    .map(city => (
                        <option key={city} value={city}>{city}</option>
                    ))
                }
            </select>

            <div>
                <DayPicker
                    mode="single"
                    selected={selectedDate}
                    onSelect={setSelectedDate}
                    disabled={[
                        { before: new Date() },
                        date => {
                            const d = format(date, 'yyyy-MM-dd');
                            return !availableDates.includes(d);
                        },
                    ]}
                    footer={
                        !selectedDate && (
                            <p className="text-sm text-red-600">Оберіть доступну дату</p>
                        )
                    }
                />
            </div>

            <button
                type="submit"
                className="bg-brand text-white font-medium p-4 rounded-lg shadow hover:bg-brand-dark transition"
                disabled={!selectedDate || !selectedDeparture || !selectedArrival}
            >
                Пошук автобусів
            </button>
        </form>
    );
};
