<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Сканер квитків (водій)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;max-width:560px;margin:32px auto;padding:0 16px}
        .card{border:1px solid #e5e7eb;border-radius:12px;padding:16px}
        .muted{color:#6b7280;font-size:14px}
        .ok{color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:8px;border-radius:8px;margin-top:8px}
        .err{color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:8px;border-radius:8px;margin-top:8px}
        input[type=text]{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px}
        button{padding:10px 14px;border:0;border-radius:8px;background:#111827;color:#fff;cursor:pointer}
        button.secondary{background:#374151}
        button:disabled{opacity:.6;cursor:not-allowed}

        /* Оверлей камери */
        .overlay{position:fixed;inset:0;background:rgba(17,24,39,.92);display:none;align-items:center;justify-content:center;z-index:50}
        .overlay.active{display:flex}
        .scan-box{position:relative;width:min(92vw,680px);aspect-ratio:3/4;background:#000;border-radius:14px;overflow:hidden}
        video{width:100%;height:100%;object-fit:cover}
        .hud{position:absolute;inset:0;pointer-events:none}
        .reticle{position:absolute;inset:12%;border:2px solid rgba(255,255,255,.8);border-radius:12px}
        .topbar{position:absolute;top:0;left:0;right:0;display:flex;justify-content:space-between;gap:8px;padding:10px}
        .topbar button{pointer-events:auto}
        .hint{position:absolute;left:0;right:0;bottom:8px;text-align:center;color:#e5e7eb;font-size:14px}
    </style>
</head>
<body>
<h2>Сканер квитків</h2>
<p class="muted">Встав, відскануй або введи UUID/посилання з QR-коду.</p>

<div class="card">
    <form id="f">
        <input id="code" type="text" placeholder="Встав сюди QR / UUID" autocomplete="off" autofocus>
        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px">
            <button id="btn" type="submit">Перевірити / посадити</button>
            <button type="button" id="clear" class="secondary">Очистити</button>
            <button type="button" id="openCam" class="secondary">Сканувати камерою</button>
        </div>
    </form>

    <div id="out" style="margin-top:12px" class="muted">Очікую на введення…</div>
</div>

<!-- Оверлей зі сканером -->
<div id="camOverlay" class="overlay" aria-hidden="true">
    <div class="scan-box">
        <video id="cam" playsinline muted></video>
        <canvas id="qrCanvas" style="display:none"></canvas>
        <div class="hud">
            <div class="topbar">
                <button type="button" id="flip" class="secondary">Змінити камеру</button>
                <button type="button" id="closeCam">Закрити</button>
            </div>
            <div class="reticle"></div>
            <div class="hint">Наведи на QR-код — зчитування відбудеться автоматично</div>
        </div>
    </div>
</div>

<script>
    (function () {
        const $ = q => document.querySelector(q);
        const out = $('#out');
        const btn = $('#btn');
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const camOverlay = $('#camOverlay');
        const video = $('#cam');
        const canvas = $('#qrCanvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        let stream = null;
        let scanning = false;
        let useRear = true;
        let detector = null;
        let jsQRReady = false;

        const UUID_RE=/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i;

        function tryDecodeBase64(s){
            try{let b=String(s).replace(/^data:[^,]+,/, '').replace(/-/g,'+').replace(/_/g,'/'); while(b.length%4)b+='='; return atob(b);}catch{return null;}
        }
        function pickUuidFromStringOrJson(str){
            try{const j=JSON.parse(str);const cand=j.u||j.uuid||j.ticket_uuid||j.id; if(typeof cand==='string'&&UUID_RE.test(cand))return cand.toLowerCase();}catch{}
            const m=String(str).match(UUID_RE); return m?m[0].toLowerCase():null;
        }
        function extractUuid(raw){
            if(!raw)return null; raw=String(raw).trim();
            let m=raw.match(UUID_RE); if(m) return m[0].toLowerCase();
            try{
                const u=new URL(raw, window.location.origin);
                m=u.pathname.match(UUID_RE); if(m) return m[0].toLowerCase();
                for(const[,v]of u.searchParams){
                    if(!v) continue;
                    const mm=String(v).match(UUID_RE); if(mm) return mm[0].toLowerCase();
                    const dec=tryDecodeBase64(v); if(dec){const cand=pickUuidFromStringOrJson(dec); if(cand) return cand;}
                }
            }catch{}
            const dec=tryDecodeBase64(raw); if(dec){const cand=pickUuidFromStringOrJson(dec); if(cand) return cand;}
            return null;
        }

        function render(data, ok){
            const b=data.booking||{};
            const rows=[
                b.name?`Пасажир: <b>${b.name}</b>`:null,
                b.seat?`Місце: <b>${b.seat}</b>`:null,
                b.route?`Рейс: ${b.route}`:null,
                b.date?`Дата: ${b.date}`:null,
            ].filter(Boolean).join('<br>');
            out.innerHTML=`<div>${data.message||(ok?'OK':'Помилка')}</div>`+(rows?`<div style="margin-top:6px">${rows}</div>`:'');
            out.className=ok?'ok':'err';
            if(navigator.vibrate) navigator.vibrate(ok?80:[60,40,60]);
        }

        async function checkin(uuid){
            btn.disabled=true;
            out.className='muted';
            out.textContent='Виконуємо перевірку…';
            try{
                const resp=await fetch(`/driver/checkin/${uuid}`,{
                    method:'POST',
                    headers:{
                        'X-CSRF-TOKEN':csrf,
                        'Accept':'application/json',
                        'Content-Type':'application/json',
                    },
                    body:JSON.stringify({place:'boarding'}),
                });
                const data=await resp.json().catch(()=>({}));
                if(resp.status===401){
                    out.className='err';
                    out.textContent='Сесія недійсна. Увійдіть як водій.';
                    setTimeout(()=>location.href='/driver/login',600);
                    return;
                }
                render(data, !!data.ok);
            }catch{
                out.className='err';
                out.textContent='Мережна помилка. Спробуйте ще раз.';
            }finally{
                btn.disabled=false;
                $('#code').select();
            }
        }

        // ===== Сканер камери =====
        function showCam(on){ camOverlay.classList.toggle('active', !!on); camOverlay.setAttribute('aria-hidden', on?'false':'true'); }

        async function startScanner(){
            // Перший пріоритет — BarcodeDetector (швидко і точно)
            if('BarcodeDetector' in window){
                try{ detector=new window.BarcodeDetector({ formats:['qr_code'] }); }
                catch{ detector=null; }
            }
            // Права на камеру
            try{
                stream = await navigator.mediaDevices.getUserMedia({
                    video:{ facingMode: useRear ? { exact: 'environment' } : 'user', width:{ideal:1280}, height:{ideal:720} },
                    audio:false
                });
            }catch(e){
                out.className='err';
                out.textContent='Камеру не дозволено або її не знайдено.';
                return;
            }
            video.srcObject=stream;
            await video.play();
            showCam(true);
            scanning=true;
            scanLoop();
        }

        function stopScanner(){
            scanning=false;
            if(stream){ stream.getTracks().forEach(t=>t.stop()); stream=null; }
            showCam(false);
        }

        async function scanLoop(){
            if(!scanning) return;
            try{
                // 1) BarcodeDetector
                if(detector){
                    const codes = await detector.detect(video);
                    if(codes && codes.length){
                        const raw = codes[0].rawValue || '';
                        const uuid = extractUuid(raw);
                        if(uuid){ stopScanner(); $('#code').value=uuid; checkin(uuid); return; }
                    }
                }else{
                    // 2) jsQR fallback
                    if(!jsQRReady){
                        await loadJsQR();
                        jsQRReady = true;
                    }
                    const w = video.videoWidth, h = video.videoHeight;
                    if(w && h){
                        canvas.width=w; canvas.height=h;
                        ctx.drawImage(video, 0, 0, w, h);
                        const img = ctx.getImageData(0,0,w,h);
                        const res = window.jsQR && window.jsQR(img.data, w, h, { inversionAttempts:'attemptBoth' });
                        if(res && res.data){
                            const uuid = extractUuid(res.data);
                            if(uuid){ stopScanner(); $('#code').value=uuid; checkin(uuid); return; }
                        }
                    }
                }
            }catch(e){ /* ігноруємо разові помилки рендеру */ }
            requestAnimationFrame(scanLoop);
        }

        function loadJsQR(){
            return new Promise((resolve,reject)=>{
                if(window.jsQR) return resolve();
                const s=document.createElement('script');
                s.src='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
                s.onload=()=>resolve();
                s.onerror=()=>reject(new Error('jsQR load failed'));
                document.head.appendChild(s);
            });
        }

        // UI хуки
        $('#f').addEventListener('submit', (e)=>{
            e.preventDefault();
            const uuid = extractUuid($('#code').value);
            if(!uuid){ out.className='err'; out.textContent='Не вдалося розпізнати UUID з QR.'; return; }
            checkin(uuid);
        });
        $('#clear').addEventListener('click', ()=>{
            $('#code').value='';
            out.className='muted';
            out.textContent='Очикую на введення…';
            $('#code').focus();
        });
        $('#code').addEventListener('change', ()=> $('#f').dispatchEvent(new Event('submit')));
        $('#openCam').addEventListener('click', startScanner);
        $('#closeCam').addEventListener('click', stopScanner);
        $('#flip').addEventListener('click', ()=>{ useRear = !useRear; stopScanner(); startScanner(); });

    })();
</script>
</body>
</html>
