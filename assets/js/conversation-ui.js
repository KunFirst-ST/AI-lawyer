(() => {
    const form = document.querySelector('.conversation-form');
    if (!form) return;

    const result = document.querySelector('#messageResult');
    const fileInput = form.querySelector('input[name="message_file"]');
    const filePreview = form.querySelector('[data-file-preview]');
    const typeInput = form.querySelector('input[name="message_type"]');
    const callInput = form.querySelector('input[name="call_type"]');
    const receiverInput = form.querySelector('input[name="receiver_id"]');
    const messageInput = form.querySelector('textarea[name="message"]');
    const speechButton = form.querySelector('[data-speech-text]');
    const recordButton = form.querySelector('[data-record-audio]');
    const stopButton = form.querySelector('[data-stop-recording]');
    const recordingState = form.querySelector('[data-recording-state]');
    const threadWindow = document.querySelector('.chat-thread-window');
    const audioFallbackInput = document.createElement('input');
    let recorder = null;
    let stream = null;
    let chunks = [];
    let threadSignature = '';
    let speechRecognition = null;
    let speechBaseText = '';

    audioFallbackInput.type = 'file';
    audioFallbackInput.accept = 'audio/*,.webm';
    audioFallbackInput.capture = 'microphone';
    audioFallbackInput.className = 'visually-hidden';
    form.appendChild(audioFallbackInput);

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const formatText = (value) => escapeHtml(value).replace(/\r?\n/g, '<br>');
    const isLocalSecureHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
    const isMicrophoneSecureContext = () => window.isSecureContext || isLocalSecureHost;
    const secureContextUrl = () => {
        const host = /^\d{1,3}(?:\.\d{1,3}){3}$/.test(window.location.hostname)
            ? `${window.location.hostname}.nip.io`
            : window.location.host;
        return `https://${host}${window.location.pathname}${window.location.search}`;
    };

    const showResult = (type, message, autoHide = true) => {
        if (!result) return;
        result.innerHTML = `<div class="alert alert-${type} mb-0">${escapeHtml(message)}</div>`;
        if (autoHide) {
            window.setTimeout(() => {
                result.innerHTML = '';
            }, 1800);
        }
    };

    const showResultHtml = (type, html, autoHide = false) => {
        if (!result) return;
        result.innerHTML = `<div class="alert alert-${type} mb-0">${html}</div>`;
        if (autoHide) {
            window.setTimeout(() => {
                result.innerHTML = '';
            }, 1800);
        }
    };

    const attachAudioFile = (file) => {
        if (!file || !fileInput) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        fileInput.files = transfer.files;
        typeInput.value = 'text';
        setFilePreview(file);
    };

    const showMicrophoneHelp = (error = null) => {
        const errorName = error?.name || '';
        const insecureContext = !isMicrophoneSecureContext();
        const reason = insecureContext
            ? 'ต้องเปิดเว็บผ่าน HTTPS ก่อนถึงจะใช้ไมโครโฟนได้'
            : (errorName === 'NotFoundError'
            ? 'ไม่พบไมโครโฟนที่เชื่อมต่อกับเครื่องนี้'
            : (errorName === 'NotAllowedError' || errorName === 'SecurityError'
                ? 'เบราว์เซอร์ยังไม่อนุญาตให้เว็บนี้ใช้ไมโครโฟน'
                : 'เบราว์เซอร์ไม่สามารถเปิดไมโครโฟนได้ในตอนนี้'));
        const helpText = insecureContext
            ? `ตอนนี้คุณเปิดผ่าน <strong>http</strong> อยู่ ให้เปิดผ่าน <a href="${escapeHtml(secureContextUrl())}"><strong>HTTPS</strong></a> แล้วกดปุ่มเสียงอีกครั้ง`
            : `ให้กดไอคอนกุญแจหรือไอคอนไมค์ข้าง URL <strong>${escapeHtml(window.location.host)}</strong> แล้วตั้งค่า <strong>Microphone = Allow</strong> จากนั้น Reload หน้าและกดปุ่มเสียงอีกครั้ง`;

        showResultHtml('warning', `
            <div class="mic-help">
                <div class="mic-help-title"><i class="bi bi-mic-mute"></i><strong>${escapeHtml(reason)}</strong></div>
                <div class="mic-help-text">${helpText}</div>
                <div class="mic-help-actions">
                    <button class="btn btn-sm btn-primary" type="button" data-open-audio-file><i class="bi bi-soundwave me-1"></i>เลือกไฟล์เสียงแทน</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-retry-mic><i class="bi bi-arrow-clockwise me-1"></i>ลองเปิดไมค์อีกครั้ง</button>
                </div>
            </div>
        `);
    };

    const setSpeechListening = (isListening) => {
        if (!speechButton) return;
        speechButton.classList.toggle('is-listening', isListening);
        const label = speechButton.querySelector('span');
        if (label) {
            label.textContent = isListening ? 'กำลังฟัง' : 'เสียงเป็นข้อความ';
        }
    };

    const appendSpeechText = (text) => {
        if (!messageInput || !text.trim()) return;
        const prefix = speechBaseText.trim();
        messageInput.value = `${prefix ? `${prefix} ` : ''}${text.trim()}`.trim();
        messageInput.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const startSpeechToText = () => {
        if (!receiverInput?.value) {
            showResult('danger', 'กรุณาเลือกคู่สนทนาก่อนพูดเป็นข้อความ', false);
            return;
        }

        if (!isMicrophoneSecureContext()) {
            const error = new Error('insecure-context');
            error.name = 'SecurityError';
            showMicrophoneHelp(error);
            return;
        }

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            showResultHtml('warning', `
                <div class="mic-help">
                    <div class="mic-help-title"><i class="bi bi-mic-mute"></i><strong>เบราว์เซอร์นี้ยังไม่รองรับการพูดเป็นข้อความ</strong></div>
                    <div class="mic-help-text">คุณยังสามารถใช้ปุ่ม <strong>ไฟล์</strong> เพื่อแนบไฟล์เสียง หรือใช้ปุ่ม <strong>โทร</strong> เพื่อสนทนาด้วยเสียงได้</div>
                </div>
            `);
            return;
        }

        if (speechRecognition) {
            speechRecognition.stop();
            return;
        }

        speechBaseText = messageInput?.value || '';
        speechRecognition = new SpeechRecognition();
        speechRecognition.lang = 'th-TH';
        speechRecognition.interimResults = true;
        speechRecognition.continuous = false;

        speechRecognition.addEventListener('start', () => {
            setSpeechListening(true);
            showResult('info', 'กำลังฟังเสียงพูด... พูดแล้วระบบจะเติมข้อความให้ตรวจทานก่อนส่ง', false);
        });

        speechRecognition.addEventListener('result', (event) => {
            let spokenText = '';
            for (let index = 0; index < event.results.length; index += 1) {
                spokenText += event.results[index][0]?.transcript || '';
            }
            appendSpeechText(spokenText);
        });

        speechRecognition.addEventListener('error', (event) => {
            if (['not-allowed', 'service-not-allowed'].includes(event.error)) {
                const error = new Error('not-allowed');
                error.name = 'NotAllowedError';
                showMicrophoneHelp(error);
                return;
            }
            if (event.error === 'no-speech') {
                showResult('warning', 'ยังไม่ได้ยินเสียงพูด ลองกดพูดอีกครั้งและพูดใกล้ไมค์มากขึ้น', false);
                return;
            }
            showResult('warning', 'ยังแปลงเสียงพูดเป็นข้อความไม่ได้ ลองใหม่อีกครั้ง หรือแนบไฟล์เสียงแทน', false);
        });

        speechRecognition.addEventListener('end', () => {
            speechRecognition = null;
            setSpeechListening(false);
            if (result?.textContent.includes('กำลังฟังเสียงพูด')) {
                result.innerHTML = '';
            }
        });

        try {
            speechRecognition.start();
        } catch (error) {
            speechRecognition = null;
            setSpeechListening(false);
            showMicrophoneHelp(error);
        }
    };

    const messageMediaHtml = (message) => {
        const type = message.message_type || 'text';
        if (type === 'call' && message.call_url) {
            const isVideo = message.call_type === 'video';
            return `
                <div class="conversation-call-card">
                    <div><i class="bi bi-${isVideo ? 'camera-video' : 'telephone'}"></i></div>
                    <span><strong>${isVideo ? 'วิดีโอคอล' : 'โทรเสียง'}</strong><small>กดเพื่อเปิดห้องสนทนาในเบราว์เซอร์</small></span>
                    <a class="btn btn-sm btn-primary" href="${escapeHtml(message.call_url)}" target="_blank">เข้าห้อง</a>
                </div>
            `;
        }

        if (!message.file_url) return '';

        if (type === 'image') {
            return `
                <a class="conversation-image-link" href="${escapeHtml(message.file_url)}" target="_blank">
                    <img src="${escapeHtml(message.file_url)}" alt="รูปภาพที่แนบในแชต">
                </a>
            `;
        }

        if (type === 'audio') {
            return `
                <div class="conversation-audio">
                    <i class="bi bi-soundwave"></i>
                    <audio controls preload="metadata" src="${escapeHtml(message.file_url)}"></audio>
                </div>
            `;
        }

        return `
            <a class="conversation-file-link" href="${escapeHtml(message.file_url)}" target="_blank">
                <i class="bi bi-paperclip"></i> เปิดไฟล์แนบ
            </a>
        `;
    };

    const messageHtml = (message) => `
        <article class="conversation-message ${message.mine ? 'mine' : 'theirs'}" data-message-id="${escapeHtml(message.id)}">
            <div class="conversation-message-meta">
                <span>${escapeHtml(message.mine ? 'คุณ' : message.sender_name)}</span>
                <time>${escapeHtml(message.created_at || '')}</time>
            </div>
            ${String(message.message || '').trim() !== '' ? `<div class="conversation-bubble">${formatText(message.message)}</div>` : ''}
            ${messageMediaHtml(message)}
        </article>
    `;

    const emptyThreadHtml = () => `
        <div class="chat-empty-state">
            <i class="bi bi-chat-dots"></i>
            <strong>ยังไม่มีแชตในห้องนี้</strong>
            <span>พิมพ์แชตแรก หรือเริ่มโทรเพื่อเปิดบทสนทนา</span>
        </div>
    `;

    const renderThread = (messages) => {
        if (!threadWindow) return;
        const nextSignature = messages.map((message) => message.id).join(',');
        if (nextSignature === threadSignature) return;

        const shouldStickToBottom = !threadSignature
            || threadWindow.scrollHeight - threadWindow.scrollTop - threadWindow.clientHeight < 90;
        threadSignature = nextSignature;
        threadWindow.innerHTML = messages.length ? messages.map(messageHtml).join('') : emptyThreadHtml();

        if (shouldStickToBottom) {
            threadWindow.scrollTop = threadWindow.scrollHeight;
        }
    };

    const fetchThread = async (silent = true) => {
        const peerId = receiverInput?.value;
        if (!peerId || !threadWindow) return;

        try {
            const response = await fetch(`/api/message-thread.php?peer_id=${encodeURIComponent(peerId)}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            const json = await response.json();
            if (json.success) {
                renderThread(json.data.messages || []);
            } else if (!silent) {
                showResult('danger', json.message || 'โหลดแชตไม่สำเร็จ', false);
            }
        } catch (error) {
            if (!silent) {
                showResult('danger', 'ไม่สามารถโหลดแชตล่าสุดได้', false);
            }
        }
    };

    const setFilePreview = (file) => {
        if (!filePreview) return;
        if (!file) {
            filePreview.hidden = true;
            filePreview.innerHTML = '';
            return;
        }
        const icon = file.type.startsWith('image/')
            ? 'image'
            : (file.type.startsWith('audio/') || file.type === 'video/webm' ? 'soundwave' : 'paperclip');
        filePreview.hidden = false;
        filePreview.innerHTML = `<i class="bi bi-${icon}"></i><span>${escapeHtml(file.name)}</span><small>${Math.ceil(file.size / 1024)} KB</small>`;
    };

    const clearRecording = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        recorder = null;
        chunks = [];
        if (recordButton) {
            recordButton.classList.remove('is-recording');
            recordButton.querySelector('span').textContent = 'อัดเสียง';
        }
        if (recordingState) {
            recordingState.hidden = true;
        }
    };

    fileInput?.addEventListener('change', () => {
        typeInput.value = 'text';
        setFilePreview(fileInput.files?.[0] || null);
    });

    audioFallbackInput.addEventListener('change', () => {
        const file = audioFallbackInput.files?.[0] || null;
        if (file) {
            attachAudioFile(file);
        }
    });

    result?.addEventListener('click', (event) => {
        const audioButton = event.target.closest('[data-open-audio-file]');
        if (audioButton) {
            audioFallbackInput.click();
            return;
        }

        const retryButton = event.target.closest('[data-retry-mic]');
        if (retryButton) {
            speechButton?.click();
        }
    });

    speechButton?.addEventListener('click', startSpeechToText);

    recordButton?.addEventListener('click', async () => {
        if (recorder && recorder.state === 'recording') {
            recorder.stop();
            return;
        }

        if (!isMicrophoneSecureContext()) {
            const error = new Error('insecure-context');
            error.name = 'SecurityError';
            showMicrophoneHelp(error);
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            showMicrophoneHelp(new Error('unsupported'));
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            chunks = [];
            recorder = new MediaRecorder(stream);
            recorder.addEventListener('dataavailable', (event) => {
                if (event.data.size > 0) chunks.push(event.data);
            });
            recorder.addEventListener('stop', () => {
                const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                const file = new File([blob], `voice-${Date.now()}.webm`, { type: blob.type });
                attachAudioFile(file);
                clearRecording();
            });
            recorder.start();
            recordButton.classList.add('is-recording');
            recordButton.querySelector('span').textContent = 'กำลังอัด';
            if (recordingState) {
                recordingState.hidden = false;
                recordingState.querySelector('span').textContent = 'กำลังอัดเสียงจากไมค์...';
            }
        } catch (error) {
            showMicrophoneHelp(error);
            clearRecording();
        }
    });

    stopButton?.addEventListener('click', () => {
        if (recorder && recorder.state === 'recording') {
            recorder.stop();
        }
    });

    form.querySelectorAll('[data-call-type]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!receiverInput?.value) {
                showResult('danger', 'กรุณาเลือกคู่สนทนาก่อนเริ่มโทร', false);
                return;
            }
            typeInput.value = 'call';
            callInput.value = button.dataset.callType || 'audio';
            form.requestSubmit();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('.conversation-submit');
        const originalText = submit.innerHTML;
        submit.disabled = true;
        submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>ส่งแชต...';

        try {
            const response = await fetch('/api/message-send.php', {
                method: 'POST',
                headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
                body: new FormData(form),
            });
            const json = await response.json();
            if (!json.success) {
                showResult('danger', json.message || 'ส่งแชตไม่สำเร็จ', false);
                return;
            }

            form.querySelector('textarea[name="message"]').value = '';
            fileInput.value = '';
            setFilePreview(null);
            showResult('success', json.message || 'ส่งในแชตแล้ว');
            await fetchThread(true);
        } catch (error) {
            showResult('danger', 'ไม่สามารถเชื่อมต่อ API แชตได้', false);
        } finally {
            typeInput.value = 'text';
            callInput.value = 'audio';
            submit.disabled = false;
            submit.innerHTML = originalText;
        }
    });

    if (threadWindow) {
        threadWindow.scrollTop = threadWindow.scrollHeight;
        fetchThread(true);
        window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetchThread(true);
            }
        }, 5000);
    }
})();
