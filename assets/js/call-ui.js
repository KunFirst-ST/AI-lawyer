(() => {
    const room = document.querySelector('.call-room');
    if (!room) return;

    const type = room.dataset.callType === 'video' ? 'video' : 'audio';
    const preview = room.querySelector('[data-call-preview]');
    const status = room.querySelector('[data-call-status]');
    const startButton = room.querySelector('[data-call-start]');
    const muteButton = room.querySelector('[data-call-mute]');
    const cameraButton = room.querySelector('[data-call-camera]');
    const copyButton = room.querySelector('[data-call-copy]');
    let stream = null;

    const setStatus = (message) => {
        if (status) status.textContent = message;
    };

    startButton?.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: type === 'video' });
            if (type === 'video') {
                preview.innerHTML = '<video autoplay muted playsinline></video>';
                preview.querySelector('video').srcObject = stream;
            } else {
                preview.innerHTML = '<div class="call-wave"><span></span><span></span><span></span><span></span></div>';
            }
            startButton.disabled = true;
            muteButton.disabled = false;
            if (cameraButton) cameraButton.disabled = type !== 'video';
            setStatus(type === 'video' ? 'เปิดกล้องและไมค์แล้ว' : 'เปิดไมค์แล้ว');
        } catch (error) {
            setStatus('ไม่สามารถเปิดไมค์/กล้องได้ กรุณาอนุญาตสิทธิ์ในเบราว์เซอร์');
        }
    });

    muteButton?.addEventListener('click', () => {
        const audioTrack = stream?.getAudioTracks()[0];
        if (!audioTrack) return;
        audioTrack.enabled = !audioTrack.enabled;
        muteButton.classList.toggle('btn-danger', !audioTrack.enabled);
        muteButton.classList.toggle('btn-outline-light', audioTrack.enabled);
        muteButton.innerHTML = audioTrack.enabled ? '<i class="bi bi-mic"></i> เปิดไมค์' : '<i class="bi bi-mic-mute"></i> ปิดไมค์';
    });

    cameraButton?.addEventListener('click', () => {
        const videoTrack = stream?.getVideoTracks()[0];
        if (!videoTrack) return;
        videoTrack.enabled = !videoTrack.enabled;
        cameraButton.classList.toggle('btn-danger', !videoTrack.enabled);
        cameraButton.classList.toggle('btn-outline-light', videoTrack.enabled);
    });

    copyButton?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(location.href);
            setStatus('คัดลอกลิงก์ห้องสนทนาแล้ว');
        } catch (error) {
            setStatus('คัดลอกอัตโนมัติไม่ได้ กรุณาคัดลอกจากแถบ URL');
        }
    });

    window.addEventListener('beforeunload', () => {
        stream?.getTracks().forEach((track) => track.stop());
    });
})();
