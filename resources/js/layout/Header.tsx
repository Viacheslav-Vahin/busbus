// resources/js/layout/Header.tsx
import React, {useEffect, useState} from 'react';
import { getMenu, getSettings } from '../cms/api';

export default function Header() {
    const [menu, setMenu] = useState<any[]>([]);
    const [site, setSite] = useState<any>({});
    useEffect(() => {
        getMenu('header').then((m)=>setMenu(m.items||[]));
        getSettings(['logo_url']).then(setSite);
    }, []);
    return (
        <nav className="bg-white shadow-md">
            <div className="container mx-auto px-6 py-4 flex justify-between items-center">
                <a href="/" className="header-logo">
                    {site.logo_url ? <img src={site.logo_url} alt=""/> : <span className="font-bold">MaxBus</span>}
                </a>
                <ul className="flex space-x-6">
                    {menu.map((item, i)=>(
                        <li key={i}><a href={item.url} className="hover:text-brand-light">{typeof item.title==='string'?item.title:item?.title?.uk}</a></li>
                    ))}
                </ul>
            </div>
        </nav>
    );
}
