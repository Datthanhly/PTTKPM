(() => {
    const button = document.querySelector('.sidebar-toggle');
    if (!button) return;
    document.body.classList.add('sidebar-ready');
    button.hidden = false;
    const account = document.querySelector('.sidebar-account');
    document.addEventListener('click', event => {
        if (account?.open && !account.contains(event.target)) account.open = false;
    });
    const close = () => {
        document.body.classList.remove('sidebar-open');
        button.setAttribute('aria-expanded', 'false');
        if (account) account.open = false;
    };
    button.addEventListener('click', () => {
        const open = document.body.classList.toggle('sidebar-open');
        button.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && account?.open) {
            event.preventDefault();
            account.open = false;
            account.querySelector('summary').focus();
            return;
        }
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            close();
            button.focus();
        }
    });
    window.matchMedia('(max-width: 800px)').addEventListener('change', close);
})();
