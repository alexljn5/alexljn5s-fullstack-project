document.addEventListener('DOMContentLoaded', function () {
    const dateBox = document.querySelector('.date-and-time-box p2');
    const weatherBox = document.querySelector('.weather-box p3');
    if (dateBox) {
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
    }
    if (weatherBox) {
        weatherBox.textContent = 'Weather: Sunny, 20°C'; // Replace with API call
    }
});