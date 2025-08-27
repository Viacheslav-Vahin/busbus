import './style.css';
import { $, setView, fmtMoney } from './ui';
import { isLoggedIn, doLogin, doLogout } from './auth';
import { scanQR, extractUuid } from './scanner';
import { shiftActive, shiftOpen, shiftClose, verifyQR, boarding, collectCash, manifest } from './api';

let currentShift: any = null;

async function boot() {
    if (await isLoggedIn()) {
        await goHome();
    } else {
        setView('v-login');
    }
}

// ---- LOGIN ----
$('#f-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = (document.getElementById('email') as HTMLInputElement).value.trim();
    const pass  = (document.getElementById('pass') as HTMLInputElement).value;
    const out = $('#login-out');
    out.textContent = 'Входимо...';
    try {
        await doLogin(email, pass);
        await goHome();
    } catch (err: any) {
        out.textContent = 'Помилка: ' + (err.message || 'login');
    }
});

// ---- HOME ----
async function goHome() {
    currentShift = await shiftActive();
    setView('v-home');
    renderHome();
}
function renderHome() {
    const s = currentShift;
    $('#home-shift').innerHTML = s
        ? `Відкрита зміна на ${s.service_date}, автобус #${s.bus_id}`
        : 'Зміна не відкрита';
    $('#btn-open-shift').style.display = s ? 'none' : 'inline-block';
    $('#btn-close-shift').style.display = s ? 'inline-block' : 'none';
}
$('#btn-logout').addEventListener('click', async () => {
    await doLogout();
    setView('v-login');
});

// ---- OPEN SHIFT ----
$('#btn-open-shift').addEventListener('click', () => {
    setView('v-open');
});
$('#f-open').addEventListener('submit', async (e) => {
    e.preventDefault();
    const busId = Number((document.getElementById('bus') as HTMLInputElement).value);
    const routeId = Number((document.getElementById('route') as HTMLInputElement).value);
    const date = (document.getElementById('svcdate') as HTMLInputElement).value;
    const cash = Number((document.getElementById('open_cash') as HTMLInputElement).value || 0);
    const out = $('#open-out');
    out.textContent = 'Відкриваємо...';
    try {
        currentShift = await shiftOpen({ bus_id: busId, route_id: routeId, service_date: date, opening_cash: cash });
        await goHome();
    } catch (e: any) {
        out.textContent = 'Помилка: ' + (e.response?.data?.message || e.message);
    }
});

// ---- CLOSE SHIFT ----
$('#btn-close-shift').addEventListener('click', () => {
    setView('v-close');
});
$('#f-close').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cash = Number((document.getElementById('closing_cash') as HTMLInputElement).value || 0);
    const term = Number((document.getElementById('terminal') as HTMLInputElement).value || 0);
    const out = $('#close-out');
    out.textContent = 'Закриваємо...';
    try {
        await shiftClose({ shift_id: currentShift.id, closing_cash: cash, terminal_deposit: term });
        currentShift = null;
        await goHome();
    } catch (e: any) {
        out.textContent = 'Помилка: ' + (e.response?.data?.message || e.message);
    }
});

// ---- SCAN ----
$('#btn-scan').addEventListener('click', async () => {
    setView('v-scan');
    $('#scan-out').textContent = 'Скануємо...';
    const raw = await scanQR();
    if (!raw) { $('#scan-out').textContent = 'Скасовано/нема доступу до камери'; return; }
    try {
        // віддаємо сирий QR (бек сам розбере base64-json), але на всяк випадок лишаємо uuid fallback
        const tryUuid = extractUuid(raw);
        const payload = tryUuid ? { qr: btoa(JSON.stringify({ u: tryUuid })) } : { qr: raw };
        const data = await verifyQR(payload.qr);
        if (!data.ok) throw new Error('not ok');
        const b = data.booking;
        $('#scan-out').innerHTML = `
      <div><b>Місце:</b> ${b.seat}, <b>Пасажир:</b> ${b.name}</div>
      <div><b>Статус:</b> ${b.status} • <b>Оплата:</b> ${b.paid ? 'є' : 'нема'} ${!b.paid ? '('+fmtMoney(b.price_uah)+')' : ''}</div>
      <div style="margin-top:10px">
        ${!b.paid ? '<button id="btn-cash" class="btn">Прийняти готівку</button>' : ''}
        <button id="btn-board" class="btn">Посадити</button>
        <button id="btn-back" class="btn btn-ghost">Назад</button>
      </div>
    `;
        $('#btn-back').addEventListener('click', goHome);
        if (document.getElementById('btn-cash')) {
            document.getElementById('btn-cash')!.addEventListener('click', async () => {
                await collectCash({ shift_id: currentShift.id, booking_id: b.id, amount_uah: b.price_uah });
                alert('Оплату зафіксовано');
                await goHome();
            });
        }
        document.getElementById('btn-board')!.addEventListener('click', async () => {
            await boarding({ shift_id: currentShift.id, booking_id: b.id, status: 'boarded' });
            alert('Посаджено');
            await goHome();
        });
    } catch (e: any) {
        $('#scan-out').textContent = 'Помилка сканування/перевірки';
    }
});

// ---- MANIFEST ----
$('#btn-manifest').addEventListener('click', async () => {
    if (!currentShift) return alert('Спочатку відкрийте зміну');
    setView('v-manifest');
    $('#man-out').textContent = 'Завантаження...';
    const list = await manifest(currentShift.id);
    const rows = (list || []).map((b: any) =>
        `<tr>
      <td>${b.seat_number}</td>
      <td>${b.passengers?.[0]?.last_name || ''} ${b.passengers?.[0]?.first_name || ''}</td>
      <td>${b.paid_at ? 'Оплачено' : 'На борту'}</td>
    </tr>`).join('');
    $('#man-out').innerHTML = `
    <table class="tbl">
      <thead><tr><th>Місце</th><th>Пасажир</th><th>Оплата</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>`;
});

// ---- NAV ----
document.querySelectorAll('[data-go-home]')!.forEach(el => el.addEventListener('click', goHome));

boot();
