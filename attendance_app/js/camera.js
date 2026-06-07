(function () {
    'use strict';

    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const captureButton = document.getElementById('captureBtn');
    const retakeButton = document.getElementById('retake');
    const switchButton = document.getElementById('switchCam');
    const openButton = document.getElementById('openCamBtnTop');
    const photoInput = document.getElementById('photo_data');
    const submitButton = document.getElementById('submitBtn');
    const videoFrame = document.getElementById('videoFrame');
    const earning = window.attendanceEarningConfig || null;

    if (!video || !canvas || !captureButton || !photoInput || !submitButton) return;

    let stream = null;
    let useFrontCamera = true;

    function stopCamera() {
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    async function startCamera() {
        stopCamera();
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: useFrontCamera ? 'user' : 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            });
            video.srcObject = stream;
            video.style.transform = useFrontCamera ? 'scaleX(-1)' : 'scaleX(1)';
            video.style.display = 'block';
            canvas.style.display = 'none';
            if (retakeButton) retakeButton.style.display = 'none';
            submitButton.style.display = 'none';
            photoInput.value = '';
        } catch (error) {
            alert('Camera permission is required.');
        }
    }

    captureButton.addEventListener('click', function (event) {
        event.preventDefault();
        if (!stream) {
            startCamera();
            return;
        }

        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        if (useFrontCamera) {
            context.translate(canvas.width, 0);
            context.scale(-1, 1);
        }
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        context.setTransform(1, 0, 0, 1, 0, 0);

        const now = new Date();
        const watermark = `${now.toLocaleDateString('en-IN')} | ${now.toLocaleTimeString('en-IN', {
            timeZone: 'Asia/Kolkata',
            hour12: true
        })}`;
        context.font = '50px Arial Black';
        context.fillStyle = 'rgba(255,255,255,.98)';
        context.strokeStyle = 'rgba(0,0,0,.85)';
        context.lineWidth = 6;
        const x = (canvas.width - context.measureText(watermark).width) / 2;
        const y = canvas.height - 5;
        context.strokeText(watermark, x, y);
        context.fillText(watermark, x, y);

        video.style.display = 'none';
        canvas.style.display = 'block';
        photoInput.value = canvas.toDataURL('image/jpeg', 0.9);
        if (retakeButton) retakeButton.style.display = 'inline-block';
        submitButton.style.display = 'inline-block';
        stopCamera();
    });

    retakeButton?.addEventListener('click', function (event) {
        event.preventDefault();
        startCamera();
    });

    switchButton?.addEventListener('click', function (event) {
        event.preventDefault();
        useFrontCamera = !useFrontCamera;
        startCamera();
    });

    openButton?.addEventListener('click', function () {
        startCamera();
        videoFrame?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopCamera();
    });

    if (earning && earning.checkInTime) {
        const perSecond = Number(earning.salaryPerHour) / 3600;
        const updateMeter = function () {
            const live = ((Date.now() - Number(earning.checkInTime)) / 1000) * perSecond;
            const total = Number(earning.pastEarning) + live + Number(earning.bonusToday);
            const liveElement = document.getElementById('liveEarn');
            const totalElement = document.getElementById('totalEarn');
            if (liveElement) liveElement.textContent = live.toFixed(2);
            if (totalElement) totalElement.textContent = total.toFixed(2);
        };
        updateMeter();
        window.setInterval(updateMeter, 1000);
    }
})();
