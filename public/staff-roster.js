(() => {
    function reveal() {
        if (!location.hash) return;
        const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (target?.tagName === 'DETAILS') target.open = true;
    }
    window.addEventListener('hashchange', reveal);
    reveal();
    document.querySelectorAll('a[href="#staff-register"]').forEach(link => {
        link.addEventListener('click', () => { const form = document.getElementById('staff-register'); if (form) form.open = true; });
    });
    const form = document.getElementById('staff-registration-form');
    if (form) {
        const from = form.elements.from_date;
        const to = form.elements.to_date;
        from.addEventListener('change', () => {
            to.min = from.value;
            if (to.value < from.value) to.value = from.value;
        });
    }
})();
