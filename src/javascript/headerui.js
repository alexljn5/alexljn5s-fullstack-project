document.addEventListener('DOMContentLoaded', function () {
    console.log('headerui.js loaded via DOMContentLoaded');
    initDateTime();
});

function initDateTime() {
    const dateBox = document.querySelector('.date-and-time-box p');
    const weatherBox = document.querySelector('.weather-box p');
    if (dateBox) {
        console.log('Date box found, updating time...');
        function updateDateTime() {
            const now = new Date();
            dateBox.textContent = now.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
    } else {
        console.error('Date box not found, retrying in 500ms...');
        setTimeout(initDateTime, 500); // Retry if DOM not ready
    }
    if (weatherBox) {
        console.log('Weather box found, setting placeholder...');
        // Placeholder weather; replace with API call (e.g., OpenWeatherMap)
        weatherBox.textContent = 'Weather: Sunny, 20°C';
    } else {
        console.error('Weather box not found!');
    }
}

// Fallback: Try initializing if DOMContentLoaded missed
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    console.log('DOM already loaded, initializing headerui.js');
    initDateTime();
}