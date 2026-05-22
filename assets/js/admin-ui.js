(() => {
    if (!location.pathname.includes('/admin/')) return;

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    };

    const statusMap = {
        active: 'success',
        approved: 'success',
        completed: 'success',
        paid: 'success',
        closed: 'success',
        confirmed: 'primary',
        pending: 'warning',
        waiting_match: 'warning',
        requested_by_user: 'warning',
        in_progress: 'primary',
        ai_consulting: 'primary',
        matched: 'primary',
        booked: 'primary',
        new: 'danger',
        rejected: 'danger',
        banned: 'danger',
        cancelled: 'danger',
        refunded: 'secondary',
        inactive: 'secondary',
        suspended: 'secondary',
    };

    const normalize = (value) => value.trim().toLowerCase().replace(/\s+/g, '_');

    const csvEscape = (value) => `"${value.replaceAll('"', '""')}"`;

    const exportTable = (table, filename) => {
        const rows = Array.from(table.querySelectorAll('tr'))
            .filter((row) => row.offsetParent !== null)
            .map((row) => Array.from(row.children).map((cell) => csvEscape(cell.innerText.trim())).join(','));
        const blob = new Blob([`\uFEFF${rows.join('\n')}`], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    };

    const enhanceTable = (wrap, index) => {
        if (wrap.dataset.adminEnhanced === '1') return;
        const table = wrap.querySelector('table');
        const tbody = table?.querySelector('tbody');
        if (!table || !tbody) return;

        wrap.dataset.adminEnhanced = '1';
        table.classList.add('admin-data-table');

        const toolbar = document.createElement('div');
        toolbar.className = 'admin-table-toolbar';
        toolbar.innerHTML = `
            <div class="admin-table-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="ค้นหาในตารางนี้">
            </div>
            <div class="admin-table-actions">
                <span class="admin-row-count"></span>
                <button class="btn btn-sm btn-outline-primary" type="button"><i class="bi bi-download me-1"></i>CSV</button>
            </div>
        `;
        wrap.insertBefore(toolbar, table);

        const input = toolbar.querySelector('input');
        const count = toolbar.querySelector('.admin-row-count');
        const button = toolbar.querySelector('button');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        const update = () => {
            const query = input.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach((row) => {
                const matched = !query || row.innerText.toLowerCase().includes(query);
                row.hidden = !matched;
                if (matched) visible += 1;
            });
            count.textContent = `${visible}/${rows.length} แถว`;
        };

        input.addEventListener('input', update);
        button.addEventListener('click', () => exportTable(table, `admin-table-${index + 1}.csv`));
        update();
    };

    const badgeStatuses = () => {
        document.querySelectorAll('.dashboard-shell .badge').forEach((badge) => {
            const key = normalize(badge.innerText);
            if (!statusMap[key]) return;
            badge.classList.remove('text-bg-light', 'text-dark');
            badge.classList.add('admin-status-badge', statusMap[key]);
        });

        document.querySelectorAll('.dashboard-shell table tbody td').forEach((cell) => {
            if (cell.children.length > 0) return;
            const key = normalize(cell.innerText);
            if (!statusMap[key]) return;
            cell.innerHTML = `<span class="admin-status-badge ${statusMap[key]}">${cell.innerText.trim()}</span>`;
        });
    };

    ready(() => {
        document.body.classList.add('admin-page');
        document.querySelectorAll('.dashboard-shell .table-responsive').forEach(enhanceTable);
        badgeStatuses();

        const topButton = document.createElement('button');
        topButton.className = 'admin-scroll-top';
        topButton.type = 'button';
        topButton.setAttribute('aria-label', 'กลับขึ้นด้านบน');
        topButton.innerHTML = '<i class="bi bi-arrow-up"></i>';
        document.body.appendChild(topButton);

        const toggleTopButton = () => {
            topButton.classList.toggle('show', window.scrollY > 500);
        };
        window.addEventListener('scroll', toggleTopButton, { passive: true });
        topButton.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        toggleTopButton();
    });
})();
