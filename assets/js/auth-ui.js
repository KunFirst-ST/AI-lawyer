(() => {
    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    };

    const passwordScore = (value) => {
        const checks = [
            value.length >= 8,
            /[A-Za-z]/.test(value),
            /\d/.test(value),
            /[^A-Za-z0-9]/.test(value),
        ];
        return checks.filter(Boolean).length;
    };

    const strengthLabel = (score) => {
        if (score >= 4) return ['strong', 'แข็งแรง'];
        if (score >= 3) return ['good', 'ดี'];
        if (score >= 2) return ['fair', 'พอใช้'];
        return ['weak', 'ยังอ่อน'];
    };

    ready(() => {
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;

            button.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
                button.innerHTML = `<i class="bi bi-${show ? 'eye-slash' : 'eye'}"></i>`;
                input.focus();
            });
        });

        document.querySelectorAll('[data-demo-fill]').forEach((button) => {
            button.addEventListener('click', () => {
                const card = button.closest('.auth-card') || document;
                const email = card.querySelector('input[name="email"]');
                const password = card.querySelector('input[name="password"]');
                if (email && button.dataset.demoEmail) {
                    email.value = button.dataset.demoEmail;
                    email.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (password && button.dataset.demoPassword) {
                    password.value = button.dataset.demoPassword;
                    password.dispatchEvent(new Event('input', { bubbles: true }));
                }
                button.classList.add('is-done');
                setTimeout(() => button.classList.remove('is-done'), 900);
            });
        });

        document.querySelectorAll('[data-password-watch]').forEach((input) => {
            const warning = document.querySelector(`[data-caps-warning="${input.id}"]`);
            if (!warning) return;

            input.addEventListener('keyup', (event) => {
                warning.hidden = !event.getModifierState('CapsLock');
            });
            input.addEventListener('blur', () => {
                warning.hidden = true;
            });
        });

        document.querySelectorAll('[data-password-strength]').forEach((input) => {
            const meter = document.querySelector(`[data-strength-for="${input.id}"]`);
            const bar = meter?.querySelector('.auth-strength-bar');
            const text = meter?.querySelector('.auth-strength-text');
            const rules = document.querySelectorAll(`[data-rule-for="${input.id}"]`);

            const update = () => {
                const value = input.value;
                const score = passwordScore(value);
                const [className, label] = strengthLabel(score);
                if (bar) {
                    bar.style.width = `${Math.max(score, value ? 1 : 0) * 25}%`;
                    bar.className = `auth-strength-bar ${className}`;
                }
                if (text) {
                    text.textContent = value ? `ความปลอดภัย: ${label}` : 'เริ่มพิมพ์เพื่อเช็กความปลอดภัย';
                }
                rules.forEach((rule) => {
                    const type = rule.dataset.rule;
                    const passed = (
                        (type === 'length' && value.length >= 8) ||
                        (type === 'letter' && /[A-Za-z]/.test(value)) ||
                        (type === 'number' && /\d/.test(value)) ||
                        (type === 'special' && /[^A-Za-z0-9]/.test(value))
                    );
                    rule.classList.toggle('is-passed', passed);
                });
            };

            input.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('[data-password-confirm]').forEach((input) => {
            const original = document.getElementById(input.dataset.passwordConfirm);
            const target = document.querySelector(`[data-match-for="${input.id}"]`);
            if (!original || !target) return;

            const update = () => {
                if (!input.value) {
                    target.textContent = 'กรอกให้ตรงกับรหัสผ่าน';
                    target.className = 'form-text';
                    return;
                }
                const matched = input.value === original.value;
                target.textContent = matched ? 'รหัสผ่านตรงกัน' : 'รหัสผ่านยังไม่ตรงกัน';
                target.className = `form-text ${matched ? 'text-success' : 'text-danger'}`;
            };

            input.addEventListener('input', update);
            original.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('[data-checkbox-count]').forEach((target) => {
            const selector = target.dataset.checkboxCount;
            const boxes = document.querySelectorAll(selector);
            const update = () => {
                target.textContent = `${Array.from(boxes).filter((box) => box.checked).length} หมวด`;
            };
            boxes.forEach((box) => box.addEventListener('change', update));
            update();
        });

        document.querySelectorAll('.auth-file-input').forEach((input) => {
            const text = document.querySelector(`[data-file-name="${input.id}"]`);
            if (!text) return;
            input.addEventListener('change', () => {
                text.textContent = input.files?.[0]?.name || 'ยังไม่ได้เลือกไฟล์';
            });
        });
    });
})();
