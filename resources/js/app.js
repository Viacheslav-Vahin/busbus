// resources/js/app.js

import './bootstrap';

function initLivewireListeners() {
    const seatLayoutInput = document.querySelector('input#seat_layout');
    if (seatLayoutInput) {
        console.log('seatLayoutInput is present');
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    const newValue = seatLayoutInput.value;
                    if (newValue !== '[]') {
                        console.log('Livewire event updateSeatLayout dispatched with:', newValue);
                        window.Livewire.dispatch('updateSeatLayout', [newValue]);

                    }
                }
            });
        });
        observer.observe(seatLayoutInput, { attributes: true, attributeFilter: ['value'] });
    }

    window.Livewire.on('seatSelected', function (data) {

        // const selectedSeatInput = document.getElementById('selected_seat');
        // const numberSeatInput = document.getElementById('data.seat_number');
        // const priceInput = document.getElementById('data.price');

        // if (selectedSeatInput) {
        //     selectedSeatInput.value = data.seatNumber;
        //     console.log(selectedSeatInput.value);
        //     selectedSeatInput.dispatchEvent(new Event('change'));
        // }
        //
        // if (numberSeatInput) {
        //     numberSeatInput.value = data.seatNumber;
        //     console.log(numberSeatInput.value);
        //     numberSeatInput.dispatchEvent(new Event('change'));
        // }
        //
        // if (priceInput && data.seatPrice) {
        //     priceInput.value = data.seatPrice;
        //     priceInput.dispatchEvent(new Event('change'));
        // }

        const dataSelect = Array.isArray(event.detail) && event.detail.length ? event.detail[0] : event.detail;
        if (dataSelect) {
            const selectedSeatInput = document.getElementById('selected_seat');
            const numberSeatInput = document.getElementById('data.seat_number');
            const priceInput = document.getElementById('data.price');

            if (selectedSeatInput) {
                selectedSeatInput.value = dataSelect.seatNumber;
                selectedSeatInput.dispatchEvent(new Event('change'));
                console.log(selectedSeatInput.value);
            }

            if (numberSeatInput) {
                numberSeatInput.value = dataSelect.seatNumber;
                console.log(numberSeatInput.value);
                numberSeatInput.dispatchEvent(new Event('change'));
            }

            if (priceInput) {
                priceInput.value = dataSelect.seatPrice;
                priceInput.dispatchEvent(new Event('change'));
                console.log(priceInput.value);
            }
        }
    });

    document.addEventListener('seat-layout-updated', function() {
        const seatLayoutInput = document.querySelector('input#seat_layout');
        if (seatLayoutInput && seatLayoutInput.value && seatLayoutInput.value !== '[]') {
            console.log('Livewire event updateSeatLayout dispatched from seat-layout-updated:', seatLayoutInput.value);
            window.Livewire.dispatch('updateSeatLayout', seatLayoutInput.value);
        }
    });

}

if (window.Livewire) {
    initLivewireListeners();
} else {
    document.addEventListener('livewire:load', initLivewireListeners);
}

document.addEventListener('seatSelected', event => {
    // event.detail - це масив, тому беремо перший елемент
    // const data = Array.isArray(event.detail) && event.detail.length ? event.detail[0] : event.detail;
    // if (data) {
    //     const selectedSeatInput = document.getElementById('selected_seat');
    //     // const priceInput = document.querySelector('input[name="data[price]"]');
    //     const priceInput = document.getElementById('data.price');
    //
    //     if (selectedSeatInput) {
    //         selectedSeatInput.value = data.seatNumber;
    //         selectedSeatInput.dispatchEvent(new Event('change'));
    //         console.log(selectedSeatInput.value);
    //     }
    //
    //     if (priceInput) {
    //         priceInput.value = data.seatPrice;
    //         priceInput.dispatchEvent(new Event('change'));
    //         console.log(priceInput.value);
    //     }
    // }
});

// var scene = $('#scene').get(0);
// var parallaxInstance = new Parallax(scene, {
//     relativeInput: true,
//     hoverOnly: true,
//     calibrateX: true
// });
