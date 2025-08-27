import axios from 'axios';
import { Preferences } from '@capacitor/preferences';

// !!! ВКАЖИ СВІЙ URL API
const BASE_URL = 'http://127.0.0.1:8000/api'; // або https://api.your-domain.com/api

export async function setToken(token: string | null) {
    if (token) await Preferences.set({ key: 'token', value: token });
    else await Preferences.remove({ key: 'token' });
}

export async function getToken() {
    const { value } = await Preferences.get({ key: 'token' });
    return value;
}

export const api = axios.create({
    baseURL: BASE_URL,
    timeout: 20000,
});

api.interceptors.request.use(async (config) => {
    const token = await getToken();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

// ---- auth ----
export async function login(email: string, password: string) {
    const { data } = await api.post('/driver/login', { email, password });
    if (data?.ok && data.token) await setToken(data.token);
    return data;
}
export async function logout() {
    try { await api.post('/driver/logout'); } catch {}
    await setToken(null);
}

// ---- driver API ----
export async function shiftActive() {
    const { data } = await api.get('/driver/shift/active');
    return data.shift || null;
}
export async function shiftOpen(payload: {
    bus_id: number; route_id: number; service_date: string; opening_cash?: number;
}) {
    const { data } = await api.post('/driver/shift/open', payload);
    return data.shift;
}
export async function shiftClose(payload: {
    shift_id: number; closing_cash: number; terminal_deposit?: number;
}) {
    const { data } = await api.post('/driver/shift/close', payload);
    return data.shift;
}
export async function verifyQR(qr: string) {
    const { data } = await api.post('/driver/scan/verify', { qr });
    return data;
}
export async function boarding(payload: {
    shift_id: number; booking_id: number; status: 'boarded'|'denied'|'refunded';
    lat?: number; lng?: number;
}) {
    const { data } = await api.post('/driver/boarding', payload);
    return data;
}
export async function collectCash(payload: {
    shift_id: number; booking_id: number; amount_uah: number;
}) {
    const { data } = await api.post('/driver/cash/collect', payload);
    return data;
}
export async function manifest(shift_id: number) {
    const { data } = await api.get('/driver/manifest', { params: { shift_id } });
    return data.manifest;
}
