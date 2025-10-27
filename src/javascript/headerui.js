document.addEventListener('DOMContentLoaded', function () {
    console.log('headerui.js loaded via DOMContentLoaded');
    initDateTime();
});

// Fallback: Try initializing if DOMContentLoaded missed
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    console.log('DOM already loaded, initializing headerui.js');
    initDateTime();
}

function initDateTime() {
    const dateBox = document.querySelector('.date-and-time-box p');
    const weatherBox = document.querySelector('.weather-box p');

    // Update date and time for Amsterdam (CET)
    if (dateBox) {
        console.log('Date box found, updating time...');
        function updateDateTime() {
            const now = new Date();
            dateBox.textContent = now.toLocaleString('en-US', {
                timeZone: 'Europe/Amsterdam',
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

    // Fetch weather for Amsterdam
    if (weatherBox) {
        console.log('Weather box found, fetching weather...');
        const amsterdamCoords = { lat: 52.3676, lon: 4.9041 };
        const cacheKey = 'weather_data_amsterdam';
        const cacheExpiration = 10 * 60 * 1000; // Cache for 10 minutes

        // Check for cached weather data
        const cachedWeather = JSON.parse(localStorage.getItem(cacheKey));
        const now = Date.now();
        if (cachedWeather && cachedWeather.timestamp && (now - cachedWeather.timestamp < cacheExpiration)) {
            console.log('Using cached weather data');
            displayWeather(cachedWeather.data, weatherBox);
            return;
        }

        // Fetch from Open-Meteo
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${amsterdamCoords.lat}&longitude=${amsterdamCoords.lon}&current_weather=true&timezone=Europe/Amsterdam`;
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Open-Meteo response:', data);
                if (data.current_weather) {
                    const weatherData = {
                        temp: data.current_weather.temperature,
                        conditionCode: data.current_weather.weathercode,
                        city: 'Amsterdam'
                    };
                    // Cache the data
                    localStorage.setItem(cacheKey, JSON.stringify({
                        data: weatherData,
                        timestamp: Date.now()
                    }));
                    displayWeather(weatherData, weatherBox);
                } else {
                    console.error('No current_weather in response:', data);
                    weatherBox.textContent = 'Data unavailable';
                }
            })
            .catch(error => {
                console.error('Error fetching weather:', error);
                weatherBox.textContent = 'Unable to fetch';
            });
    } else {
        console.error('Weather box not found, retrying in 500ms...');
        setTimeout(initDateTime, 500); // Retry if DOM not ready
    }
}

function displayWeather(data, weatherBox) {
    // Expanded WMO weather code mapping (based on Open-Meteo documentation)
    const conditionMap = {
        0: 'Clear sky',
        1: 'Mainly clear',
        2: 'Partly cloudy',
        3: 'Overcast',
        45: 'Fog',
        48: 'Depositing rime fog',
        51: 'Light drizzle',
        53: 'Moderate drizzle',
        55: 'Dense drizzle',
        61: 'Light rain',
        63: 'Moderate rain',
        65: 'Heavy rain',
        71: 'Light snow',
        73: 'Moderate snow',
        75: 'Heavy snow',
        77: 'Snow grains',
        80: 'Light rain showers',
        81: 'Moderate rain showers',
        82: 'Violent rain showers',
        85: 'Light snow showers',
        86: 'Heavy snow showers',
        95: 'Thunderstorm',
        96: 'Thunderstorm with slight hail',
        99: 'Thunderstorm with heavy hail'
    };
    const condition = conditionMap[data.conditionCode] || 'Unknown';
    weatherBox.textContent = `${data.city}: ${condition}, ${Math.round(data.temp)}°C`;
}