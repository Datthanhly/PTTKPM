(() => {
    // A page restored from browser history can contain a token from before logout.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });

    const password = document.getElementById('login-password');
    const toggle = document.querySelector('.password-toggle');
    if (!password || !toggle) return;

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.textContent = visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
        toggle.setAttribute('aria-pressed', String(visible));
    });
})();
