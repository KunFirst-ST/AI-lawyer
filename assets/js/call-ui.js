(() => {
    const room = document.querySelector('.call-room');
    if (!room) return;

    const type = room.dataset.callType === 'video' ? 'video' : 'audio';
    const roomName = room.dataset.callRoom || '';
    const isInitiator = room.dataset.callInitiator === '1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const localPreview = room.querySelector('[data-call-preview]');
    const remotePreview = room.querySelector('[data-call-remote]');
    const status = room.querySelector('[data-call-status]');
    const startButton = room.querySelector('[data-call-start]');
    const muteButton = room.querySelector('[data-call-mute]');
    const cameraButton = room.querySelector('[data-call-camera]');
    const copyButton = room.querySelector('[data-call-copy]');
    const endButton = room.querySelector('[data-call-end]');
    const isLocalSecureHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
    const isMediaSecureContext = () => window.isSecureContext || isLocalSecureHost;
    let localStream = null;
    let remoteStream = null;
    let peer = null;
    let lastEventId = 0;
    let pollTimer = null;
    let pendingOffer = null;
    let queuedIce = [];
    let started = false;
    let ended = false;

    const setStatus = (message) => {
        if (status) status.textContent = message;
    };

    const secureContextUrl = () => {
        const host = /^\d{1,3}(?:\.\d{1,3}){3}$/.test(window.location.hostname)
            ? `${window.location.hostname}.nip.io`
            : window.location.host;
        return `https://${host}${window.location.pathname}${window.location.search}`;
    };

    const sendSignal = async (signalType, payload = {}) => {
        const response = await fetch('/api/call-signal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ room: roomName, type: signalType, payload }),
        });
        const json = await response.json();
        if (!json.success) throw new Error(json.message || 'ส่งข้อมูลการโทรไม่สำเร็จ');
    };

    const renderLocalPreview = () => {
        if (!localPreview || !localStream) return;
        if (type === 'video') {
            localPreview.innerHTML = '<video autoplay muted playsinline></video>';
            localPreview.querySelector('video').srcObject = localStream;
            return;
        }
        localPreview.innerHTML = '<div class="call-wave"><span></span><span></span><span></span><span></span></div><small>ไมโครโฟนของคุณ</small>';
    };

    const renderRemoteStream = () => {
        if (!remotePreview || !remoteStream) return;
        if (type === 'video') {
            remotePreview.innerHTML = '<video autoplay playsinline></video>';
            remotePreview.querySelector('video').srcObject = remoteStream;
            return;
        }
        remotePreview.innerHTML = '<div class="call-wave"><span></span><span></span><span></span><span></span></div><audio autoplay controls></audio><small>เชื่อมต่อเสียงกับคู่สนทนาแล้ว</small>';
        remotePreview.querySelector('audio').srcObject = remoteStream;
    };

    const flushIce = async () => {
        if (!peer?.remoteDescription) return;
        const candidates = queuedIce;
        queuedIce = [];
        for (const candidate of candidates) {
            await peer.addIceCandidate(candidate);
        }
    };

    const createPeer = () => {
        if (peer) return peer;
        peer = new RTCPeerConnection({
            iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
        });
        localStream?.getTracks().forEach((track) => peer.addTrack(track, localStream));
        remoteStream = new MediaStream();

        peer.addEventListener('icecandidate', (event) => {
            if (event.candidate) {
                sendSignal('ice', { candidate: event.candidate }).catch(() => {
                    setStatus('ส่งข้อมูลเชื่อมต่อไม่สำเร็จ กรุณาลองเข้าห้องใหม่');
                });
            }
        });
        peer.addEventListener('track', (event) => {
            event.streams[0]?.getTracks().forEach((track) => remoteStream.addTrack(track));
            renderRemoteStream();
        });
        peer.addEventListener('connectionstatechange', () => {
            if (peer.connectionState === 'connected') {
                setStatus('เชื่อมต่อกับคู่สนทนาแล้ว');
            } else if (['failed', 'disconnected'].includes(peer.connectionState)) {
                setStatus('การเชื่อมต่อสะดุด กรุณาลองเข้าห้องใหม่');
            } else if (peer.connectionState === 'closed') {
                setStatus('วางสายแล้ว');
            }
        });
        return peer;
    };

    const acceptOffer = async (offer) => {
        if (!started) {
            pendingOffer = offer;
            setStatus('มีสายเรียกเข้า กดเริ่มเพื่อรับสาย');
            return;
        }
        const connection = createPeer();
        await connection.setRemoteDescription(offer);
        await flushIce();
        const answer = await connection.createAnswer();
        await connection.setLocalDescription(answer);
        await sendSignal('answer', { description: connection.localDescription });
        setStatus('กำลังเชื่อมต่อกับคู่สนทนา...');
    };

    const handleSignal = async (event) => {
        if (event.type === 'offer' && event.payload.description) {
            await acceptOffer(event.payload.description);
            return;
        }
        if (event.type === 'answer' && event.payload.description && peer) {
            await peer.setRemoteDescription(event.payload.description);
            await flushIce();
            setStatus('กำลังเชื่อมต่อกับคู่สนทนา...');
            return;
        }
        if (event.type === 'ice' && event.payload.candidate) {
            if (peer?.remoteDescription) {
                await peer.addIceCandidate(event.payload.candidate);
            } else {
                queuedIce.push(event.payload.candidate);
            }
            return;
        }
        if (event.type === 'hangup') {
            closeCall(false);
            setStatus('คู่สนทนาวางสายแล้ว');
        }
    };

    const pollEvents = async () => {
        if (ended || !roomName) return;
        try {
            const response = await fetch(`/api/call-events.php?room=${encodeURIComponent(roomName)}&after_id=${lastEventId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await response.json();
            if (!json.success) throw new Error(json.message || 'โหลดข้อมูลการโทรไม่สำเร็จ');
            for (const event of json.data.events || []) {
                lastEventId = Math.max(lastEventId, Number(event.id) || 0);
                await handleSignal(event);
            }
        } catch (error) {
            setStatus(error.message || 'ไม่สามารถเชื่อมต่อห้องโทรได้');
        }
    };

    const startPolling = () => {
        if (pollTimer) return;
        pollEvents();
        pollTimer = window.setInterval(pollEvents, 1200);
    };

    const startCall = async () => {
        if (started) return;
        if (!isMediaSecureContext()) {
            setStatus(`ต้องเปิดผ่าน HTTPS ก่อน: ${secureContextUrl()}`);
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia || typeof RTCPeerConnection === 'undefined') {
            setStatus('เบราว์เซอร์นี้ยังไม่รองรับการโทรผ่านเว็บ');
            return;
        }

        try {
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: type === 'video' });
            started = true;
            renderLocalPreview();
            const connection = createPeer();
            startButton.disabled = true;
            muteButton.disabled = false;
            if (cameraButton) cameraButton.disabled = type !== 'video';

            if (pendingOffer) {
                const offer = pendingOffer;
                pendingOffer = null;
                await acceptOffer(offer);
            } else if (isInitiator) {
                const offer = await connection.createOffer();
                await connection.setLocalDescription(offer);
                await sendSignal('offer', { description: connection.localDescription });
                setStatus('ส่งคำเชิญแล้ว รอคู่สนทนารับสาย');
            } else {
                setStatus('เปิดไมโครโฟนแล้ว รอคำเชิญจากคู่สนทนา');
            }
        } catch (error) {
            setStatus('ไม่สามารถเริ่มการโทรได้ กรุณาอนุญาตสิทธิ์ไมโครโฟน/กล้องและลองใหม่');
        }
    };

    const closeCall = (notifyPeer = true) => {
        if (ended) return;
        ended = true;
        if (notifyPeer) sendSignal('hangup').catch(() => {});
        window.clearInterval(pollTimer);
        pollTimer = null;
        peer?.close();
        peer = null;
        localStream?.getTracks().forEach((track) => track.stop());
        localStream = null;
        remoteStream?.getTracks().forEach((track) => track.stop());
        remoteStream = null;
        startButton.disabled = true;
        muteButton.disabled = true;
        if (cameraButton) cameraButton.disabled = true;
        setStatus('วางสายแล้ว');
    };

    startButton?.addEventListener('click', startCall);
    endButton?.addEventListener('click', () => closeCall(true));
    muteButton?.addEventListener('click', () => {
        const audioTrack = localStream?.getAudioTracks()[0];
        if (!audioTrack) return;
        audioTrack.enabled = !audioTrack.enabled;
        muteButton.classList.toggle('btn-danger', !audioTrack.enabled);
        muteButton.classList.toggle('btn-outline-light', audioTrack.enabled);
        muteButton.innerHTML = audioTrack.enabled ? '<i class="bi bi-mic"></i> เปิดไมค์' : '<i class="bi bi-mic-mute"></i> ปิดไมค์';
    });
    cameraButton?.addEventListener('click', () => {
        const videoTrack = localStream?.getVideoTracks()[0];
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
    window.addEventListener('beforeunload', () => closeCall(started));

    startPolling();
})();
