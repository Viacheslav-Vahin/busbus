{{-- resources/views/driver/app.blade.php --}}
    <!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="manifest" href="/driver/manifest.webmanifest">
    <script>if('serviceWorker' in navigator){navigator.serviceWorker.register('/driver/sw.js');}</script>
    <title>Driver</title>
    <style>body{font-family:system-ui, -apple-system, Segoe UI, Roboto}</style>
</head>
<body>
<div id="root">Завантаження…</div>
<script type="module">
    // Мінімальний сканер на BarcodeDetector з фолбеком
    const root = document.getElementById('root');

    async function scan() {
        if ('BarcodeDetector' in window) {
            const det = new BarcodeDetector({formats: ['qr_code']});
            const stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
            const video = document.createElement('video');
            video.playsInline = true; video.srcObject = stream; await video.play();
            root.innerHTML=''; root.append(video);
            const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');

            const tick = async () => {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const bitmap = await createImageBitmap(canvas);
                    const codes = await det.detect(bitmap);
                    if (codes.length) {
                        const qr = codes[0].rawValue;
                        stream.getTracks().forEach(t=>t.stop());
                        handleQR(qr); return;
                    }
                }
                requestAnimationFrame(tick);
            };
            tick();
        } else {
            root.innerHTML = 'Ваш браузер не підтримує сканування. Введіть код вручну.';
        }
    }

    async function handleQR(qr) {
        root.innerHTML = 'Перевіряємо…';
        try {
            const res = await fetch('/api/driver/scan/verify', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({qr})
            });
            const data = await res.json();
            if(!data.ok){ root.innerHTML = 'Квиток не знайдено'; return; }

            const b = data.booking;
            root.innerHTML = `
          <div>
            <h2>Місце ${b.seat} • ${b.name}</h2>
            <div>Статус: ${b.status} • Оплата: ${b.paid ? 'є' : 'ні'}</div>
            ${!b.paid ? `<button id="btn-cash">Прийняти готівку (${b.price_uah} UAH)</button>` : ''}
            <button id="btn-board">Посадити</button>
          </div>`;

            if(document.getElementById('btn-cash')){
                document.getElementById('btn-cash').onclick = async ()=>{
                    const shift = await (await fetch('/api/driver/shift/active')).json();
                    await fetch('/api/driver/cash/collect',{method:'POST',headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({shift_id: shift.shift.id, booking_id: b.id, amount_uah: b.price_uah})});
                    alert('Оплату зафіксовано'); location.reload();
                };
            }
            document.getElementById('btn-board').onclick = async ()=>{
                const shift = await (await fetch('/api/driver/shift/active')).json();
                await fetch('/api/driver/boarding',{method:'POST',headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({shift_id: shift.shift.id, booking_id: b.id, status:'boarded'})});
                alert('Посаджено'); location.reload();
            };
        } catch(e){ root.innerHTML = 'Помилка мережі'; }
    }

    root.innerHTML = '<button id="s">Сканувати QR</button>';
    document.getElementById('s').onclick = scan;
</script>
</body>
</html>
