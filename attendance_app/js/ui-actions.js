(function () {
    'use strict';

    const config = window.attendanceUiConfig || {};
    let titleClicks = 0;

    document.getElementById('pageTitle')?.addEventListener('click', function () {
        titleClicks += 1;
        if (titleClicks === 3) {
            window.open('https://www.youtube.com/@RJDigitalVlog', '_blank');
            titleClicks = 0;
        }
    });

    window.openContactModal_udharband = () => {
        document.getElementById('udhar_contactModal_udharband').style.display = 'flex';
    };
    window.closeContactModal_udharband = () => {
        document.getElementById('udhar_contactModal_udharband').style.display = 'none';
    };
    window.makeCall_udharband = () => { location.href = 'tel:9928221039'; };
    window.openWhatsApp_udharband = () => { location.href = 'https://wa.me/919928221039'; };
    window.togglePaytmQR = () => {
        const qr = document.getElementById('udhar_paytmQR');
        if (qr) qr.style.display = qr.style.display === 'none' ? 'block' : 'none';
    };

    window.openContactModal_mehulRoy = () => {
        document.getElementById('contactModal_mehulRoy').style.display = 'flex';
    };
    window.closeContactModal_mehulRoy = () => {
        document.getElementById('contactModal_mehulRoy').style.display = 'none';
    };
    window.makeCall_mehulRoy = () => { location.href = 'tel:8306023723'; };
    window.openWhatsApp_mehulRoy = () => { location.href = 'https://wa.me/918306023723'; };
    window.openContractorForm = () => {
        location.href = `./Card-form/index.php?emp_id=${encodeURIComponent(config.employeeId || '')}`;
    };
    window.openWifeForm = () => {
        location.href = `./Card-form/index2.php?emp_id=${encodeURIComponent(config.employeeId || '')}`;
    };
    window.goToDetails = (id) => { location.href = `partydetails.php?id=${encodeURIComponent(id)}`; };

    function formatMySQLTime(dateTime) {
        if (!dateTime) return '-';
        const parts = dateTime.split(' ');
        if (parts.length < 2) return '-';
        const [hour, minute] = parts[1].split(':');
        let numericHour = parseInt(hour, 10);
        const suffix = numericHour >= 12 ? 'PM' : 'AM';
        numericHour = numericHour % 12 || 12;
        return `${numericHour}:${minute} ${suffix}`;
    }

    window.openUserModal = (index) => {
        const user = (config.activeUsers || [])[index];
        if (!user) return;
        document.getElementById('modalImg').src = user.img;
        document.getElementById('modalName').textContent = user.name;
        document.getElementById('modalCheckIn').textContent = formatMySQLTime(user.check_in);
        document.getElementById('userModal').style.display = 'flex';
    };
    window.closeUserModal = () => {
        document.getElementById('userModal').style.display = 'none';
    };

    document.querySelectorAll('.payment-thumb').forEach((image) => {
        image.addEventListener('click', function () { window.open(this.src, '_blank'); });
    });

    window.openNoticeModal2 = () => {
        const modalElement = document.getElementById('noticeModal2');
        if (modalElement && window.bootstrap) new bootstrap.Modal(modalElement).show();
    };

    const currentMonth = new Date().getMonth();
    document.querySelectorAll('#targetAmountTableUnique_2026 tbody tr').forEach((row) => {
        if (Number(row.dataset.month) === currentMonth) {
            row.style.background = '#fff3cd';
            row.style.fontWeight = 'bold';
            row.style.border = '2px solid #ffc107';
        }
    });
})();
