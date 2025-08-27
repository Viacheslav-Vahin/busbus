export function $(q: string, root: Document|HTMLElement = document) {
    const el = root.querySelector(q) as HTMLElement | null;
    if (!el) throw new Error(`No element ${q}`);
    return el;
}
export function setView(id: string) {
    document.querySelectorAll<HTMLElement>('.view').forEach(v => v.style.display = 'none');
    const el = document.getElementById(id);
    if (el) el.style.display = 'block';
}
export function fmtMoney(n: number) {
    return new Intl.NumberFormat('uk-UA', { style: 'currency', currency: 'UAH', maximumFractionDigits: 0 }).format(n);
}
