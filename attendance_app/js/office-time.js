(function () {
    'use strict';

    const config = window.attendanceOfficeConfig;
    if (!config) return;

    const countdown = document.getElementById('countdown');
    const start = new Date(config.openTime).getTime();
    if (document.getElementById('officeModal')) {
        document.body.style.overflow = 'hidden';
    }

    function updateCountdown() {
        if (!countdown || config.afterOfficeEnd) return;

        const difference = start - Date.now();
        if (difference <= 0) {
            const modal = document.getElementById('officeModal');
            if (modal) modal.remove();
            document.body.style.overflow = 'auto';
            return;
        }

        const hours = Math.floor(difference / 3600000);
        const minutes = Math.floor((difference % 3600000) / 60000);
        const seconds = Math.floor((difference % 60000) / 1000);
        countdown.textContent = `${hours}h ${minutes}m ${seconds}s`;
    }

    updateCountdown();
    window.setInterval(updateCountdown, 1000);
})();
