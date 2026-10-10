<div data-date-weather class="grid grid-cols-2 gap-3 text-left sm:min-w-[320px]" aria-label="Current date, time, and weather">
    <div class="min-w-0 rounded-2xl border border-emerald-200 bg-gradient-to-br from-white via-white to-emerald-50 p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-800">Today</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v13a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                </svg>
            </span>
        </div>
        <p data-current-date class="mt-2 text-sm font-bold tracking-tight text-slate-900">{{ now()->setTimezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y') }}</p>
        <div class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-600">
            <svg class="h-3.5 w-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p data-current-time>{{ now()->setTimezone(config('app.timezone', 'Asia/Manila'))->format('g:i A') }} PHT</p>
        </div>
    </div>
    <div class="min-w-0 rounded-2xl border border-sky-200 bg-gradient-to-br from-white via-white to-sky-50 p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-sky-800">Weather</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2m7.07.93-1.42 1.42M21 12h-2M5 12H3m3.35-5.65L4.93 4.93M16 16a5 5 0 10-9.9-1H6a3 3 0 000 6h10a4 4 0 000-8h-.17"/>
                </svg>
            </span>
        </div>
        <p data-weather class="mt-2 min-h-10 text-sm font-bold leading-5 tracking-tight text-slate-900">Loading weather...</p>
        <div class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-600">
            <svg class="h-3.5 w-3.5 shrink-0 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s7-4.5 7-11a7 7 0 10-14 0c0 6.5 7 11 7 11z"/>
                <circle cx="12" cy="10" r="2.2" stroke-width="1.8"/>
            </svg>
            <p>Palompon, Leyte</p>
        </div>
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
                    const response = await fetch('https://api.open-meteo.com/v1/forecast?latitude=11.0508&longitude=124.3843&current=temperature_2m,weather_code&temperature_unit=celsius&timezone=Asia%2FManila');
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