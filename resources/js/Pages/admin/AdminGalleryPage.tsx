import React, {useEffect, useRef, useState} from 'react';
import axios from 'axios';

type Pending = {
    file: File;
    id: string;
    title: string;
    tags: string;
    progress: number;
    status: 'queued'|'uploading'|'done'|'error';
    error?: string;
};

const AdminGalleryPage: React.FC = () => {
    const [items, setItems] = useState<Pending[]>([]);
    const inputRef = useRef<HTMLInputElement|null>(null);

    const onFiles = (files: FileList | null) => {
        if (!files) return;
        const arr: Pending[] = Array.from(files).map(f => ({
            file: f, id: crypto.randomUUID(), title: f.name.replace(/\.[^.]+$/,''), tags: '', progress: 0, status:'queued'
        }));
        setItems(prev => [...prev, ...arr]);
    };

    const upload = async (row: Pending) => {
        const form = new FormData();
        form.append('image', row.file);
        form.append('title', row.title);
        form.append('tags', row.tags); // comma separated

        setItems(s => s.map(i => i.id===row.id ? {...i,status:'uploading',progress:0} : i));

        try {
            await axios.post('/api/admin/gallery', form, {
                headers: {'Content-Type':'multipart/form-data'},
                onUploadProgress: (ev) => {
                    const p = ev.total ? Math.round((ev.loaded/ev.total)*100) : 0;
                    setItems(s => s.map(i => i.id===row.id ? {...i,progress:p} : i));
                }
            });
            setItems(s => s.map(i => i.id===row.id ? {...i,status:'done',progress:100} : i));
        } catch (e:any) {
            setItems(s => s.map(i => i.id===row.id ? {...i,status:'error',error:String(e?.response?.data?.message || e.message)} : i));
        }
    };

    const uploadAll = () => items.filter(i=>i.status==='queued').forEach(upload);
    type Existing = {
        id:number; url:string; title?:string|null; tags:string[];
        w:number; h:number; created_at:string;
    };
    const [existing, setExisting] = useState<Existing[]>([]);
    const [loadingList, setLoadingList] = useState(false);

    const loadExisting = async () => {
        setLoadingList(true);
        try {
            const {data} = await axios.get('/api/gallery', { params: { limit: 100 } });
            setExisting(data.items);
        } finally { setLoadingList(false); }
    };
    useEffect(()=>{ loadExisting(); }, []);
    return (
        <div className="container mx-auto px-6 py-10">
            <h1 className="text-3xl font-bold mb-6 heading">Галерея — завантаження</h1>

            <div className="mb-6">
                <div
                    onDragOver={(e)=>{e.preventDefault();}}
                    onDrop={(e)=>{e.preventDefault(); onFiles(e.dataTransfer.files);}}
                    className="border-2 border-dashed rounded-2xl p-10 text-center bg-white"
                >
                    <p className="mb-2">Перетягніть сюди фото або</p>
                    <button className="px-4 py-2 rounded bg-brand text-white" onClick={()=>inputRef.current?.click()}>
                        Обрати файли
                    </button>
                    <input ref={inputRef} type="file" accept="image/*" multiple className="hidden"
                           onChange={(e)=>onFiles(e.target.files)} />
                </div>
            </div>

            {!!items.length && (
                <>
                    <div className="flex justify-between items-center mb-4">
                        <button className="px-4 py-2 rounded bg-brand text-white" onClick={uploadAll}>Завантажити все
                        </button>
                        <button className="px-3 py-2 rounded border" onClick={loadExisting}>
                            Оновити список
                        </button>
                    </div>
                    <div className="mt-10">
                        <h2 className="text-xl font-semibold mb-3">Фото в галереї</h2>
                        {loadingList ? <div>Завантаження…</div> : (
                            <div className="overflow-x-auto bg-white rounded-xl shadow">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                    <tr>
                                        <th className="p-3 text-left">Прев’ю</th>
                                        <th className="p-3 text-left">Заголовок</th>
                                        <th className="p-3 text-left">Теги</th>
                                        <th className="p-3 text-left">Розмір</th>
                                        <th className="p-3 text-left">Дії</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    {existing.map(p => (
                                        <tr key={p.id} className="border-t">
                                            <td className="p-3"><img src={p.url}
                                                                     className="w-24 h-16 object-cover rounded"/></td>
                                            <td className="p-3 w-[280px]">
                                                <input className="border rounded px-2 py-1 w-full"
                                                       defaultValue={p.title || ''}
                                                       onBlur={e => {
                                                           const title = e.target.value;
                                                           axios.patch(`/api/admin/gallery/${p.id}`, {title})
                                                               .then(() => setExisting(arr => arr.map(x => x.id === p.id ? {
                                                                   ...x,
                                                                   title
                                                               } : x)));
                                                       }}/>
                                            </td>
                                            <td className="p-3 w-[280px]">
                                                <input className="border rounded px-2 py-1 w-full"
                                                       defaultValue={p.tags.join(', ')}
                                                       onBlur={e => {
                                                           const tags = e.target.value.split(',').map(t => t.trim()).filter(Boolean);
                                                           axios.patch(`/api/admin/gallery/${p.id}`, {tags})
                                                               .then(() => setExisting(arr => arr.map(x => x.id === p.id ? {
                                                                   ...x,
                                                                   tags
                                                               } : x)));
                                                       }}/>
                                            </td>
                                            <td className="p-3">{p.w}×{p.h}</td>
                                            <td className="p-3">
                                                <button className="px-3 py-1 rounded text-red-600 hover:bg-red-50"
                                                        onClick={async () => {
                                                            if (!confirm('Видалити фото?')) return;
                                                            await axios.delete(`/api/admin/gallery/${p.id}`);
                                                            setExisting(arr => arr.filter(x => x.id !== p.id));
                                                        }}>Видалити
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <ul className="space-y-3">
                        {items.map(row => (
                            <li key={row.id} className="bg-white p-4 rounded-xl shadow flex gap-4 items-center">
                                <img src={URL.createObjectURL(row.file)} className="w-24 h-16 object-cover rounded"/>
                                <div className="flex-1 grid gap-2">
                                    <input className="border rounded px-3 py-2"
                                           value={row.title} onChange={e => setItems(s => s.map(i => i.id === row.id ? {
                                        ...i,
                                        title: e.target.value
                                    } : i))}
                                           placeholder="Заголовок"/>
                                    <input className="border rounded px-3 py-2"
                                           value={row.tags} onChange={e => setItems(s => s.map(i => i.id === row.id ? {
                                        ...i,
                                        tags: e.target.value
                                    } : i))}
                                           placeholder="теги через кому (наприклад: bus,kyiv,team)"/>
                                    <div className="h-2 bg-gray-100 rounded">
                                        <div className="h-full rounded bg-brand" style={{width: `${row.progress}%`}}/>
                                    </div>
                                </div>
                                <div className="w-32 text-right">
                                    {row.status === 'queued' &&
                                        <button className="px-3 py-1 rounded bg-brand text-white"
                                                onClick={() => upload(row)}>Завантажити</button>}
                                    {row.status === 'uploading' &&
                                        <span className="text-gray-600">{row.progress}%</span>}
                                    {row.status === 'done' && <span className="text-green-600">Готово</span>}
                                    {row.status === 'error' && <span className="text-red-600">Помилка</span>}
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
};

export default AdminGalleryPage;
