import { login, logout, getToken } from './api';

export async function isLoggedIn() {
    return !!(await getToken());
}

export async function doLogin(email: string, password: string) {
    const r = await login(email, password);
    if (!r?.ok) throw new Error(r?.error || 'Login failed');
}

export async function doLogout() {
    await logout();
}
