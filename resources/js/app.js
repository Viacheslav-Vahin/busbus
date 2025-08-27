// resources/js/app.js
import './bootstrap';

function initLivewireBridge() {
    // 1) Місток: seat_layout -> Livewire(updateSeatLayout)
    const seatLayoutInput = document.querySelector('input#seat_layout');
    if (seatLayoutInput) {
        // Підстрахуємось: спрацює і коли Livewire міняє 'value',
        // і коли хтось вручну тригерне input/change.
        const dispatchIfNeeded = () => {
            const newValue = seatLayoutInput.value;
            if (newValue && newValue !== '[]') {
                console.log('[bridge] dispatch updateSeatLayout with:', newValue);
                // Livewire v3: window.Livewire.dispatch(eventName, payloadArray)
                // Передаємо саме масивом, бо слухач очікує один аргумент
                if (window.Livewire?.dispatch) {
                    window.Livewire.dispatch('updateSeatLayout', [newValue]); // v3
                } else if (window.Livewire?.emit) {
                    window.Livewire.emit('updateSeatLayout', newValue);       // v2
                }
            }
        };

        // 1a) MutationObserver — відслідковує зміну атрибуту value
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'attributes' && m.attributeName === 'value') {
                    dispatchIfNeeded();
                }
            }
        });
        observer.observe(seatLayoutInput, { attributes: true, attributeFilter: ['value'] });

        // 1b) На всяк випадок — ще й події input/change
        seatLayoutInput.addEventListener('input', dispatchIfNeeded);
        seatLayoutInput.addEventListener('change', dispatchIfNeeded);
    } else {
        console.warn('[bridge] <input id="seat_layout"> не знайдено у DOM');
    }

    // 2) Ловимо browser event від Livewire-компонента:
    // $this->dispatch('seatSelected', [...])
    // Це НЕ livewire-бас подія, тому слухаємо через addEventListener
    window.addEventListener('seatSelected', (e) => {
        // e.detail може бути або об’єктом, або масивом із одним об’єктом (залежно як диспатчиш)
        const payload = Array.isArray(e.detail) && e.detail.length ? e.detail[0] : e.detail;
        if (!payload) return;

        const selectedSeatInput = document.getElementById('selected_seat');
        const numberSeatInput   = document.getElementById('data.seat_number');
        const priceInput        = document.getElementById('data.price');

        if (selectedSeatInput) {
            selectedSeatInput.value = payload.seatNumber ?? '';
            selectedSeatInput.dispatchEvent(new Event('change'));
        }
        if (numberSeatInput) {
            numberSeatInput.value = payload.seatNumber ?? '';
            numberSeatInput.dispatchEvent(new Event('change'));
        }
        if (priceInput) {
            // Якщо ціна може бути рядком — ставимо як є
            priceInput.value = payload.seatPrice ?? '';
            priceInput.dispatchEvent(new Event('change'));
        }

        console.log('[bridge] seatSelected -> form updated:', payload);
    });
}

if (window.Livewire) {
    initLivewireBridge();
} else {
    document.addEventListener('livewire:load', initLivewireBridge);
}
