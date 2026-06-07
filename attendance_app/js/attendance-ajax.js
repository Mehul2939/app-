(function () {
    'use strict';

    const form = document.getElementById('attForm');
    if (!form) return;

    const submitButton = form.querySelector('button[type="submit"], button[name="checkin"], button[name="checkout"]');
    const photoInput = document.getElementById('photo_data');
    const totalEarn = document.getElementById('totalEarn');
    const liveEarn = document.getElementById('liveEarn');

    function showMessage(message, success) {
        let alert = document.getElementById('attendanceAjaxMessage');
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'attendanceAjaxMessage';
            alert.style.cssText = 'position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:100000;padding:12px 18px;border-radius:10px;color:#fff;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.2)';
            document.body.appendChild(alert);
        }

        alert.style.background = success ? '#198754' : '#dc3545';
        alert.textContent = message;
        alert.hidden = false;
        window.setTimeout(() => { alert.hidden = true; }, 4500);
    }

    function switchAction(action) {
        if (!submitButton) return;

        if (action === 'checkin') {
            submitButton.name = 'checkout';
            submitButton.textContent = 'Submit Check-Out';
            submitButton.classList.remove('btn-primary-large');
            submitButton.classList.add('btn-danger-large');
        } else if (action === 'checkout') {
            submitButton.name = 'checkin';
            submitButton.textContent = 'Submit Check-In';
            submitButton.classList.remove('btn-danger-large');
            submitButton.classList.add('btn-primary-large');
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (!photoInput || !photoInput.value) {
            showMessage('Please capture your photo first.', false);
            return;
        }

        const actionButton = event.submitter || submitButton;
        const formData = new FormData(form);
        if (actionButton && actionButton.name) {
            formData.set(actionButton.name, '1');
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalText = submitButton.textContent;
            submitButton.textContent = 'Please wait...';
        }

        try {
            const response = await fetch('attendance_app/api/attendance_action.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Attendance request failed.');
            }

            showMessage(data.message, true);
            switchAction(data.action);

            if (data.action === 'checkout' && totalEarn && data.total_today_earning !== undefined) {
                totalEarn.textContent = Number(data.total_today_earning).toFixed(2);
            }
            if (liveEarn) liveEarn.textContent = '0.00';

            photoInput.value = '';
            const retake = document.getElementById('retake');
            if (retake) retake.click();
        } catch (error) {
            showMessage(error.message || 'Something went wrong.', false);
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                if (submitButton.textContent === 'Please wait...') {
                    submitButton.textContent = submitButton.dataset.originalText || 'Submit';
                }
            }
        }
    }, true);
})();
