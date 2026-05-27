(() => {
    const root = document.documentElement;
    const button = document.querySelector('[data-theme-toggle]');
    if (!button) return;

    const icon = button.querySelector('[data-theme-icon]');
    const label = button.querySelector('[data-theme-label]');

    const applyTheme = (theme) => {
        const nextTheme = theme === 'night' ? 'night' : 'day';
        root.dataset.theme = nextTheme;
        localStorage.setItem('ai_lawyer_theme', nextTheme);
        button.setAttribute('aria-pressed', nextTheme === 'night' ? 'true' : 'false');
        button.setAttribute('title', nextTheme === 'night' ? 'เปลี่ยนเป็นโหมดกลางวัน' : 'เปลี่ยนเป็นโหมดกลางคืน');
        if (icon) {
            icon.className = nextTheme === 'night' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        if (label) {
            label.textContent = nextTheme === 'night' ? 'กลางวัน' : 'กลางคืน';
        }
    };

    applyTheme(root.dataset.theme || 'day');

    button.addEventListener('click', () => {
        applyTheme(root.dataset.theme === 'night' ? 'day' : 'night');
    });
})();
