(() => {
    const form = document.getElementById('password-recovery-form');
    const message = document.getElementById('recovery-wait');
    if (!form || !message) return;
    const button = form.querySelector('button[type="submit"]');
    const deadline = Date.now() + Number(message.dataset.retryAfter || 0) * 1000;
    function update() {
        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        button.disabled = remaining > 0;
        message.textContent = remaining > 0 ? `Vui lòng chờ ${remaining} giây trước khi gửi lại.` : '';
        const limitMessage = document.getElementById('recovery-limit-message');
        if (remaining === 0 && limitMessage) limitMessage.hidden = true;
        return remaining;
    }
    if (update() > 0) {
        const timer = setInterval(() => { if (update() === 0) clearInterval(timer); }, 1000);
    }
    form.addEventListener('submit', () => { button.disabled = true; button.textContent = 'Đang gửi yêu cầu…'; });
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });
})();
