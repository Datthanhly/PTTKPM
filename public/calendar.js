(() => {
    const scroll = document.querySelector('.calendar-scroll');
    const mobile = window.matchMedia('(max-width: 800px)');
    function showCurrentDay() {
        if (!scroll || !mobile.matches) return;
        const column = scroll.querySelector('.calendar-day-column.is-today') || scroll.querySelector('.shift-card')?.parentElement;
        if (column) scroll.scrollLeft += column.getBoundingClientRect().left - scroll.getBoundingClientRect().left - 64;
    }
    mobile.addEventListener('change', showCurrentDay);
    showCurrentDay();

    const dialog = document.getElementById('shift-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const body = document.getElementById('shift-dialog-body');
    const title = document.getElementById('shift-dialog-title');
    let controller;
    let displayedUrl;

    async function load(url) {
        if (new URL(url, window.location.href).origin !== window.location.origin) return;
        controller?.abort();
        const current = new AbortController();
        controller = current;
        body.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'shift-loading';
        loading.textContent = 'Đang tải lịch hẹn trong ca…';
        body.append(loading);
        body.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {
                signal: current.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                cache: 'no-store',
            });
            if (!response.ok || response.redirected) throw new Error('Shift unavailable');
            const html = await response.text();
            if (current.signal.aborted || !dialog.open) return;
            body.innerHTML = html;
            displayedUrl = url;
        } catch (error) {
            if (current.signal.aborted || !dialog.open) return;
            const message = document.createElement('p');
            message.className = 'shift-load-error';
            message.textContent = 'Không tải được lịch hẹn. Phiên có thể đã hết hạn hoặc ca này không còn được phép truy cập. ';
            const retry = document.createElement('button');
            retry.className = 'button ghost small';
            retry.type = 'button';
            retry.textContent = 'Thử lại';
            retry.addEventListener('click', () => load(url));
            body.replaceChildren(message, retry);
        } finally {
            if (controller === current) body.setAttribute('aria-busy', 'false');
        }
    }

    document.querySelectorAll('.shift-card').forEach(link => {
        link.addEventListener('click', event => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            title.textContent = link.dataset.shiftTitle;
            dialog.showModal();
            load(link.href);
        });
    });
    document.getElementById('shift-dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { controller?.abort(); body.replaceChildren(); });
    dialog.addEventListener('click', event => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    body.addEventListener('submit', async event => {
        const statusForm = event.target.closest('form[data-appointment-status]');
        if (statusForm) {
            event.preventDefault();
            const submittedUrl = displayedUrl;
            const controls = [...body.querySelectorAll('form[data-appointment-status] button:not(:disabled)')];
            controls.forEach(button => { button.disabled = true; });
            function message(text, success = false) {
                if (!dialog.open || displayedUrl !== submittedUrl) return;
                body.querySelector('[data-status-feedback]')?.remove();
                const alert = document.createElement('p');
                alert.className = success ? 'alert success' : 'alert error';
                alert.dataset.statusFeedback = '';
                alert.setAttribute('role', success ? 'status' : 'alert');
                alert.tabIndex = -1;
                alert.textContent = text;
                body.prepend(alert);
                alert.focus({ preventScroll: true });
            }
            try {
                const response = await fetch(statusForm.action, {
                    method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(statusForm),
                });
                const data = await response.json();
                if (!response.ok) {
                    message(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Không thể cập nhật. Hãy tải lại lịch và thử lại.');
                } else if (data.redirect) {
                    window.location.assign(data.redirect);
                } else if (dialog.open && displayedUrl === submittedUrl) {
                    await load(submittedUrl);
                    message(data.message || 'Đã cập nhật lịch hẹn.', true);
                }
            } catch (error) {
                message('Chưa nhận được phản hồi. Hãy mở lại ca để kiểm tra trạng thái trước khi gửi lại.');
            } finally {
                controls.forEach(button => { if (body.contains(button)) button.disabled = false; });
            }
            return;
        }
        const transfer = event.target.closest('form[data-shift-transfer]');
        if (transfer) {
            event.preventDefault();
            const button = transfer.querySelector('button[type="submit"]');
            const message = transfer.querySelector('.transfer-message');
            button.disabled = true;
            message.textContent = 'Đang gửi đề nghị…';
            try {
                const response = await fetch(transfer.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(transfer) });
                const data = await response.json();
                if (!response.ok) {
                    message.textContent = data.errors ? Object.values(data.errors).flat().join(' ') : 'Không gửi được đề nghị. Vui lòng tải lại trang và thử lại.';
                    button.disabled = false;
                } else {
                    message.textContent = data.message;
                    transfer.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = true; });
                }
            } catch (error) {
                message.textContent = 'Không nhận được phản hồi. Hãy kiểm tra Thông báo trước khi thử gửi lại.';
                button.disabled = false;
            }
            return;
        }
        const form = event.target.closest('form[data-shift-filter]');
        if (!form) return;
        event.preventDefault();
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form)).toString();
        load(url.href);
    });
    body.addEventListener('click', event => {
        const link = event.target.closest('.shift-pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.href);
    });
})();
