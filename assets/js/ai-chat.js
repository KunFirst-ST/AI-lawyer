const chatState = {
    caseId: document.querySelector('#chatMessages')?.dataset.currentCaseId || localStorage.getItem('ai_lawyer_current_case_id') || null,
    lastAi: null,
};

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const chatMessages = document.querySelector('#chatMessages');
const chatForm = document.querySelector('#chatForm');
const messageInput = document.querySelector('#messageInput');
const fileInput = document.querySelector('#caseDocument');
const analysisPanel = document.querySelector('#analysisPanel');
const filePreview = document.querySelector('[data-ai-file-preview]');
const voiceResult = document.querySelector('[data-ai-voice-result]');
const speechButton = document.querySelector('[data-ai-speech-text]');
const sendButton = chatForm?.querySelector('button[type="submit"]');
const defaultInputPlaceholder = messageInput?.getAttribute('placeholder') || '';
const defaultSpeechButtonHtml = speechButton?.innerHTML || '';
let speechRecognition = null;
let speechBaseText = '';

if (chatState.caseId === '0' || chatState.caseId === '') {
    chatState.caseId = null;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function formatMessageText(text) {
    return escapeHtml(text).replace(/\r?\n/g, '<br>');
}

const isLocalSecureHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
const isMicrophoneSecureContext = () => window.isSecureContext || isLocalSecureHost;
const secureContextUrl = () => {
    const host = /^\d{1,3}(?:\.\d{1,3}){3}$/.test(window.location.hostname)
        ? `${window.location.hostname}.nip.io`
        : window.location.host;
    return `https://${host}${window.location.pathname}${window.location.search}`;
};

function appendMessage(type, text) {
    const wrap = document.createElement('div');
    wrap.className = `ai-chat-row ${type === 'user' ? 'user' : 'ai'}`;
    wrap.innerHTML = `<div class="chat-message ${type}">${formatMessageText(text)}</div>`;
    chatMessages.appendChild(wrap);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function appendTyping() {
    const wrap = document.createElement('div');
    wrap.className = 'ai-chat-row ai';
    wrap.innerHTML = `
        <div class="chat-message ai typing-message" aria-live="polite">
            <span class="ai-typing-label">AI กำลังเรียบเรียงคำตอบให้เข้าใจง่าย</span>
            <span class="ai-typing" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>
    `;
    chatMessages.appendChild(wrap);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return wrap;
}

function setComposerBusy(isBusy) {
    if (sendButton) {
        sendButton.disabled = isBusy;
        sendButton.innerHTML = isBusy ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' : '<i class="bi bi-send"></i>';
    }
    if (messageInput) {
        messageInput.placeholder = isBusy ? 'รอสักครู่ AI กำลังคิดคำตอบ...' : defaultInputPlaceholder;
    }
}

function setFilePreview(file) {
    if (!filePreview) return;
    if (!file) {
        filePreview.hidden = true;
        filePreview.innerHTML = '';
        return;
    }
    filePreview.hidden = false;
    const icon = file.type.startsWith('audio/') || file.type === 'video/webm' ? 'soundwave' : 'paperclip';
    filePreview.innerHTML = `<i class="bi bi-${icon}"></i><span>${escapeHtml(file.name)}</span><small>${Math.ceil(file.size / 1024)} KB</small>`;
}

function showVoiceResultHtml(type, html, autoHide = false) {
    if (!voiceResult) return;
    voiceResult.hidden = false;
    voiceResult.innerHTML = `<div class="alert alert-${type}">${html}</div>`;
    if (autoHide) {
        window.setTimeout(() => {
            voiceResult.hidden = true;
            voiceResult.innerHTML = '';
        }, 2200);
    }
}

function showVoiceResult(type, message, autoHide = false) {
    showVoiceResultHtml(type, escapeHtml(message), autoHide);
}

function showMicrophoneHelp(error = null) {
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

    showVoiceResultHtml('warning', `
        <div class="mic-help">
            <div class="mic-help-title"><i class="bi bi-mic-mute"></i><strong>${escapeHtml(reason)}</strong></div>
            <div class="mic-help-text">${helpText}</div>
            <div class="mic-help-actions">
                <button class="btn btn-sm btn-primary" type="button" data-ai-open-audio-file><i class="bi bi-soundwave me-1"></i>แนบไฟล์เสียงแทน</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-ai-retry-mic><i class="bi bi-arrow-clockwise me-1"></i>ลองเปิดไมค์อีกครั้ง</button>
            </div>
        </div>
    `);
}

function setSpeechListening(isListening) {
    if (!speechButton) return;
    speechButton.classList.toggle('is-listening', isListening);
    speechButton.innerHTML = isListening
        ? '<i class="bi bi-mic-fill"></i><span class="visually-hidden">กำลังฟัง</span>'
        : defaultSpeechButtonHtml;
}

function appendSpeechText(text) {
    if (!messageInput || !text.trim()) return;
    const prefix = speechBaseText.trim();
    messageInput.value = `${prefix ? `${prefix} ` : ''}${text.trim()}`.trim();
    messageInput.dispatchEvent(new Event('input', { bubbles: true }));
}

function startSpeechToText() {
    if (!messageInput) return;

    if (!isMicrophoneSecureContext()) {
        const error = new Error('insecure-context');
        error.name = 'SecurityError';
        showMicrophoneHelp(error);
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        showVoiceResultHtml('warning', `
            <div class="mic-help">
                <div class="mic-help-title"><i class="bi bi-mic-mute"></i><strong>เบราว์เซอร์นี้ยังไม่รองรับการพูดเป็นข้อความ</strong></div>
                <div class="mic-help-text">ลองใช้ Chrome หรือ Edge เวอร์ชันล่าสุด หรือพิมพ์คำถามแล้วแนบไฟล์เสียงเป็นหลักฐานแทนได้ครับ</div>
                <div class="mic-help-actions">
                    <button class="btn btn-sm btn-primary" type="button" data-ai-open-audio-file><i class="bi bi-soundwave me-1"></i>แนบไฟล์เสียง</button>
                </div>
            </div>
        `);
        return;
    }

    if (speechRecognition) {
        speechRecognition.stop();
        return;
    }

    speechBaseText = messageInput.value || '';
    speechRecognition = new SpeechRecognition();
    speechRecognition.lang = 'th-TH';
    speechRecognition.interimResults = true;
    speechRecognition.continuous = false;

    speechRecognition.addEventListener('start', () => {
        setSpeechListening(true);
        showVoiceResult('info', 'กำลังฟังเสียงพูด... พูดแล้วระบบจะเติมข้อความให้ตรวจทานก่อนส่ง');
    });

    speechRecognition.addEventListener('result', event => {
        let spokenText = '';
        for (let index = 0; index < event.results.length; index += 1) {
            spokenText += event.results[index][0]?.transcript || '';
        }
        appendSpeechText(spokenText);
    });

    speechRecognition.addEventListener('error', event => {
        if (['not-allowed', 'service-not-allowed'].includes(event.error)) {
            const error = new Error('not-allowed');
            error.name = 'NotAllowedError';
            showMicrophoneHelp(error);
            return;
        }
        if (event.error === 'no-speech') {
            showVoiceResult('warning', 'ยังไม่ได้ยินเสียงพูด ลองกดไมค์อีกครั้งและพูดใกล้ไมค์มากขึ้น');
            return;
        }
        showVoiceResult('warning', 'ยังแปลงเสียงเป็นข้อความไม่ได้ ลองใหม่อีกครั้ง หรือพิมพ์คำถามแทนได้เลย');
    });

    speechRecognition.addEventListener('end', () => {
        speechRecognition = null;
        setSpeechListening(false);
        if (voiceResult?.textContent.includes('กำลังฟังเสียงพูด')) {
            voiceResult.hidden = true;
            voiceResult.innerHTML = '';
        }
    });

    try {
        speechRecognition.start();
    } catch (error) {
        speechRecognition = null;
        setSpeechListening(false);
        showMicrophoneHelp(error);
    }
}

function levelText(level) {
    return { low: 'ต่ำ', medium: 'ปานกลาง', high: 'สูง', critical: 'วิกฤต' }[level] || '-';
}

function categoryText(slug) {
    return {
        criminal: 'กฎหมายอาญา',
        civil: 'กฎหมายแพ่ง',
        family: 'กฎหมายครอบครัว',
        labor: 'กฎหมายแรงงาน',
        business: 'กฎหมายธุรกิจ',
        land: 'กฎหมายที่ดิน',
        inheritance: 'กฎหมายมรดก',
        tax: 'กฎหมายภาษี',
        consumer: 'กฎหมายผู้บริโภค',
        intellectual_property: 'ทรัพย์สินทางปัญญา',
        immigration: 'ตรวจคนเข้าเมือง',
        bankruptcy: 'ล้มละลาย',
        contract: 'สัญญา',
    }[slug] || slug || '-';
}

function listItems(items) {
    if (!items || !items.length) {
        return '<li class="text-muted">ยังไม่มีข้อมูล</li>';
    }
    return items.map(item => `<li>${escapeHtml(item)}</li>`).join('');
}

function renderLegalSections(sections) {
    if (!sections || !sections.length) {
        return '<div class="text-muted">ยังไม่มีมาตราที่ระบุได้จากข้อมูลนี้</div>';
    }
    return sections.map(section => `
        <div class="border rounded-2 p-2 mb-2 bg-white">
            <div class="fw-semibold">${escapeHtml(section.law_name)} มาตรา ${escapeHtml(section.section)}</div>
            <div class="small text-muted">${escapeHtml(section.plain_meaning || '')}</div>
            <div class="small mt-1">${escapeHtml(section.why_relevant || '')}</div>
            <div class="mt-1">
                <span class="badge text-bg-light text-dark">ความมั่นใจ ${escapeHtml(levelText(section.confidence))}</span>
                ${section.needs_lawyer_review ? '<span class="badge text-bg-warning">ควรให้ทนายตรวจ</span>' : ''}
            </div>
        </div>
    `).join('');
}

function renderConversationState(ai) {
    const answered = ai.answered_fields || {};
    const labels = {
        province: 'จังหวัด',
        consultation_type: 'รูปแบบปรึกษา',
        budget_min: 'งบต่ำสุด',
        budget_max: 'งบสูงสุด',
        incident_date: 'วันเกิดเหตุ',
        damage_amount: 'มูลค่าความเสียหาย',
        has_court_or_police_document: 'เอกสารจากศาล/ตำรวจ',
    };
    const answeredItems = Object.entries(answered)
        .filter(([, value]) => value !== null && value !== '' && typeof value !== 'undefined')
        .map(([key, value]) => {
            const displayValue = typeof value === 'boolean' ? (value ? 'มีแล้ว' : 'ยังไม่มี') : String(value);
            return `<span class="badge text-bg-success me-1 mb-1">${escapeHtml(labels[key] || key)}: ${escapeHtml(displayValue)}</span>`;
        })
        .join('');
    const missing = (ai.missing_context_fields || [])
        .map(field => `<span class="badge text-bg-light text-dark me-1 mb-1">ยังขาด: ${escapeHtml(labels[field] || field)}</span>`)
        .join('');

    if (!answeredItems && !missing && !ai.conversation_intent) {
        return '';
    }

    const intentText = {
        new_legal_question: 'คำถามกฎหมายใหม่',
        answering_follow_up: 'กำลังตอบคำถามที่ AI ถามไว้',
        procedural_follow_up: 'ถามขั้นตอนต่อจากเคสเดิม',
        lawyer_match_info: 'ข้อมูลเพื่อหาทนาย',
    }[ai.conversation_intent] || 'กำลังวิเคราะห์บริบท';

    return `
        <div class="col-12">
            <div class="small text-muted mb-2">สถานะบทสนทนา</div>
            <div class="border rounded-2 p-2 bg-white">
                <div class="fw-semibold mb-2">${escapeHtml(intentText)}</div>
                <div>${answeredItems || '<span class="small text-muted">ยังไม่พบคำตอบใหม่ที่เป็นข้อมูลโครงสร้าง</span>'}</div>
                <div class="mt-1">${missing}</div>
            </div>
        </div>
    `;
}

function renderAnalysis(ai, caseId) {
    const related = (ai.related_categories || []).map(categoryText).join(', ') || '-';
    analysisPanel.innerHTML = `
        <div class="app-card analysis-card p-3">
            <h5 class="fw-bold mb-3">AI วิเคราะห์เบื้องต้น</h5>
            <div class="row g-3">
                <div class="col-md-6"><div class="small text-muted">หมวดหลัก</div><div class="fw-semibold">${escapeHtml(categoryText(ai.primary_category))}</div></div>
                <div class="col-md-6"><div class="small text-muted">หมวดที่เกี่ยวข้อง</div><div class="fw-semibold">${escapeHtml(related)}</div></div>
                <div class="col-md-6"><div class="small text-muted">ความซับซ้อน</div><div class="fw-semibold level-${escapeHtml(ai.complexity_level)}">${escapeHtml(levelText(ai.complexity_level))}</div></div>
                <div class="col-md-6"><div class="small text-muted">ความเร่งด่วน</div><div class="fw-semibold level-${escapeHtml(ai.urgency)}">${escapeHtml(levelText(ai.urgency))}</div></div>
                <div class="col-md-6">
                    <div class="small text-muted">เอกสารที่ควรเตรียม</div>
                    <ul class="mb-0 ps-3">${listItems(ai.recommended_documents || [])}</ul>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">คำถามเพิ่มเติม</div>
                    <ul class="mb-0 ps-3">${listItems(ai.questions_to_ask_next || [])}</ul>
                </div>
                ${renderConversationState(ai)}
                <div class="col-12">
                    <div class="small text-muted mb-2">มาตรากฎหมายที่อาจเกี่ยวข้อง</div>
                    ${renderLegalSections(ai.possible_legal_sections || [])}
                    <div class="small text-muted">รายการนี้เป็นการชี้ประเด็นเบื้องต้นจาก AI ไม่ใช่ข้อสรุปว่าผิดหรือชนะคดีแน่นอน</div>
                </div>
            </div>
            <div class="border-top mt-3 pt-3">
                <div class="fw-semibold mb-2">คุณต้องการให้ระบบช่วยหาทนายที่เหมาะกับเคสนี้ไหม?</div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" data-consent="yes" data-case-id="${caseId}">ต้องการหาทนาย</button>
                    <button class="btn btn-outline-secondary" data-consent="no" data-case-id="${caseId}">ยังไม่ต้องการ</button>
                </div>
            </div>
        </div>
    `;
}

function renderConsentDetails(caseId, questions) {
    analysisPanel.insertAdjacentHTML('beforeend', `
        <div class="app-card p-3 mt-3" id="matchDetailsCard">
            <h6 class="fw-bold">ก่อนจับคู่ ขอข้อมูลเพิ่มเล็กน้อย</h6>
            <ol class="small-muted mb-3">${questions.map(q => `<li>${escapeHtml(q)}</li>`).join('')}</ol>
            <form id="matchDetailsForm" class="row g-3">
                <input type="hidden" name="case_id" value="${caseId}">
                <input type="hidden" name="consent" value="yes">
                <div class="col-md-4">
                    <label class="form-label">จังหวัด</label>
                    <input class="form-control" name="province" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">รูปแบบปรึกษา</label>
                    <select class="form-select" name="consultation_type" required>
                        <option value="chat">ออนไลน์/แชต</option>
                        <option value="phone">โทร</option>
                        <option value="video">วิดีโอคอล</option>
                        <option value="onsite">พบตัวจริง</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">งบประมาณสูงสุด</label>
                    <input class="form-control" type="number" name="budget_max" min="0" step="100" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">เริ่มค้นหาทนาย</button>
                </div>
            </form>
        </div>
    `);
}

function renderMatches(matches) {
    if (!matches.length) {
        analysisPanel.insertAdjacentHTML('beforeend', '<div class="alert alert-warning mt-3">ยังไม่พบทนายที่เหมาะสมในตอนนี้ แอดมินสามารถเพิ่มหรืออนุมัติทนายเพิ่มเติมได้</div>');
        return;
    }

    const html = matches.map(match => `
        <div class="col-md-6">
            <div class="app-card p-3 h-100">
                <div class="d-flex gap-3">
                    <div class="profile-avatar"><i class="bi bi-person-badge"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">${escapeHtml(match.name)}</h6>
                        <div class="small-muted">${escapeHtml(match.province || '-')} · ${escapeHtml(match.consultation_fee)} บาท</div>
                        <div class="mt-2"><span class="badge text-bg-primary">ความเหมาะสม ${Math.round(match.match_score)} คะแนน</span> ${Number(match.verified) === 1 ? '<span class="badge text-bg-success">ยืนยันโปรไฟล์แล้ว</span>' : ''}</div>
                    </div>
                </div>
                <p class="small-muted mt-3 mb-3">${escapeHtml(match.match_reason)}</p>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="/public/lawyer-detail.php?id=${match.id}">ดูโปรไฟล์</a>
                    <a class="btn btn-sm btn-primary" href="/user/booking.php?lawyer_id=${match.id}&case_id=${chatState.caseId}">จองปรึกษา</a>
                </div>
            </div>
        </div>
    `).join('');

    analysisPanel.insertAdjacentHTML('beforeend', `<div class="mt-3"><h6 class="fw-bold">ระบบพบทนายที่เหมาะกับเคสของคุณ</h6><div class="row g-3">${html}</div><a class="btn btn-link px-0 mt-2" href="/user/matches.php?case_id=${chatState.caseId}">ดูรายการทั้งหมด</a></div>`);
}

async function submitConsent(formData) {
    const response = await fetch('/api/user-consent.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData,
    });
    const result = await response.json();
    if (!result.success) {
        appendMessage('ai', result.message || 'เกิดข้อผิดพลาด');
        return;
    }

    appendMessage('ai', result.message);
    document.querySelector('#matchDetailsCard')?.remove();
    if (result.data.requires_more_info) {
        renderConsentDetails(formData.get('case_id'), result.data.questions || []);
    }
    if (result.data.matches) {
        renderMatches(result.data.matches);
    }
}

analysisPanel?.addEventListener('click', event => {
    const button = event.target.closest('[data-consent]');
    if (!button) return;

    const formData = new FormData();
    formData.append('case_id', button.dataset.caseId);
    formData.append('consent', button.dataset.consent);
    submitConsent(formData);
});

analysisPanel?.addEventListener('submit', event => {
    if (event.target.id !== 'matchDetailsForm') return;
    event.preventDefault();
    submitConsent(new FormData(event.target));
});

chatForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const text = messageInput.value.trim();
    if (!text) return;

    appendMessage('user', text);
    messageInput.value = '';
    if (voiceResult) {
        voiceResult.hidden = true;
        voiceResult.innerHTML = '';
    }

    const formData = new FormData(chatForm);
    formData.set('message', text);
    if (chatState.caseId) {
        formData.set('case_id', chatState.caseId);
    }

    const loadingNode = appendTyping();
    setComposerBusy(true);

    try {
        const response = await fetch('/api/ai-chat.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData,
        });
        const result = await response.json();
        loadingNode?.remove();
        setComposerBusy(false);

        if (!result.success) {
            appendMessage('ai', result.message || 'เกิดข้อผิดพลาด');
            return;
        }

        chatState.lastAi = result.data.ai;
        appendMessage('ai', result.data.ai.reply_to_user);
        if (!result.data.ai.is_casual_chat && result.data.case_id) {
            chatState.caseId = result.data.case_id;
            localStorage.setItem('ai_lawyer_current_case_id', String(result.data.case_id));
            chatMessages.dataset.currentCaseId = String(result.data.case_id);
            renderAnalysis(result.data.ai, result.data.case_id);
        }
        fileInput.value = '';
        setFilePreview(null);
    } catch (error) {
        loadingNode?.remove();
        setComposerBusy(false);
        appendMessage('ai', 'ตอนนี้ผมติดต่อระบบ AI ไม่ได้ครับ ลองส่งคำถามใหม่อีกครั้งได้เลย');
    }
});

fileInput?.addEventListener('change', () => setFilePreview(fileInput.files?.[0] || null));

speechButton?.addEventListener('click', startSpeechToText);

voiceResult?.addEventListener('click', event => {
    const audioButton = event.target.closest('[data-ai-open-audio-file]');
    if (audioButton) {
        fileInput?.click();
        return;
    }

    const retryButton = event.target.closest('[data-ai-retry-mic]');
    if (retryButton) {
        startSpeechToText();
    }
});

messageInput?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        chatForm.requestSubmit();
    }
});

chatMessages?.scrollTo({ top: chatMessages.scrollHeight });
