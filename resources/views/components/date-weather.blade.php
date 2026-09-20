<div data-date-weather class="grid grid-cols-2 gap-2 text-left sm:min-w-[300px]" aria-label="Current date, time, and weather">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Today</p>
        <p data-current-date class="mt-1 text-xs font-semibold text-slate-900">{{ now()->setTimezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y') }}</p>
        <p data-current-time class="mt-1 text-xs text-slate-600">{{ now()->setTimezone(config('app.timezone', 'Asia/Manila'))->format('g:i A') }} PHT</p>
    </div>
    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-3 py-2">
        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-sky-700">Weather</p>
        <p data-weather class="mt-1 text-xs font-semibold text-slate-900">Loading weather...</p>
        <p class="mt-1 text-xs text-slate-600">Palompon, Leyte</p>
    </div>
</div>

@once
    <script>
        (() => {
            const weatherLabels = { 0: 'Clear sky', 1: 'Mainly clear', 2: 'Partly cloudy', 3: 'Overcast', 45: 'Foggy', 48: 'Foggy', 51: 'Light drizzle', 53: 'Drizzle', 55: 'Heavy drizzle', 61: 'Light rain', 63: 'Rain', 65: 'Heavy rain', 80: 'Rain showers', 81: 'Rain showers', 82: 'Heavy showers', 95: 'Thunderstorm', 96: 'Thunderstorm', 99: 'Thunderstorm' };
            const updateClock = () => {
                const now = new Date();
                document.querySelectorAll('[data-current-date]').forEach(element => {
                    element.textContent = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric' }).format(now);
                });
                document.querySelectorAll('[data-current-time]').forEach(element => {
                    element.textContent = `${new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(now)} PHT`;
                });
            };
            const loadWeather = async () => {
                const weatherElements = [...document.querySelectorAll('[data-weather]')];
                if (!weatherElements.length) return;
                try {
                    const response = await fetch('https://api.open-meteo.com/v1/forecast?latitude=11.05&longitude=124.78&current=temperature_2m,weather_code&temperature_unit=celsius&timezone=Asia%2FManila');
                    if (!response.ok) throw new Error('Weather request failed');
                    const current = (await response.json()).current || {};
                    const label = weatherLabels[current.weather_code] || 'Conditions available';
                    weatherElements.forEach(element => { element.textContent = `${label}, ${Math.round(Number(current.temperature_2m))}°C`; });
                } catch (error) {
                    weatherElements.forEach(element => { element.textContent = 'Weather unavailable'; });
                }
            };
            updateClock();
            window.setInterval(updateClock, 1000);
            loadWeather();
        })();
    </script>
@endonce