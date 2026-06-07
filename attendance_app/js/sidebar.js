(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('overlay');

    toggleButton?.addEventListener('click', function () {
        sidebar?.classList.toggle('show');
        overlay?.classList.toggle('show');
    });

    overlay?.addEventListener('click', function () {
        sidebar?.classList.remove('show');
        overlay?.classList.remove('show');
    });
})();
