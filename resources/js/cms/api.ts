// resources/js/cms/api.ts
import axios from 'axios';

export type CmsBlock =
    | { type: 'hero'; data: { title: string; subtitle?: string; cta_text?: string; cta_href?: string } }
    | { type: 'booking_form'; data?: {} }
    | { type: 'benefits'; data: { items: { icon?: string; title: string; text?: string }[] } }
    | { type: 'how_it_works'; data: { steps: { icon?: string; title: string; text?: string }[] } }
    | { type: 'faq'; data: { items: { q: string; a: string }[] } }
    | { type: 'trust_bar'; data?: {} }
    | { type: 'help_cta'; data?: { text?: string } }
// ...додаси свої

export interface CmsPage { slug: string; title: string; blocks: CmsBlock[]; meta?: any; }

export const getPage   = (slug: string, locale='uk') => axios.get<CmsPage>(`/api/cms/page/${slug}`, { params:{ locale } }).then(r=>r.data);
export const getMenu   = (key: string) => axios.get(`/api/cms/menus/${key}`).then(r=>r.data);
export const getSettings = (keys?: string[]) => axios.get('/api/cms/settings', { params: { keys } }).then(r=>r.data);
