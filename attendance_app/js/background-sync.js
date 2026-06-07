(function () {
    'use strict';

    function syncMonthlyReport() {
        fetch('attendance_app/api/monthly_report_sync.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: 'sync=1',
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {
            // Background sync failure must not block the attendance page.
        });
    }

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(syncMonthlyReport, { timeout: 3000 });
    } else {
        window.setTimeout(syncMonthlyReport, 1200);
    }
})();
