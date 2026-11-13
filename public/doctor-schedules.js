(() => {
    const form = document.getElementById('doctor-schedule-form');
    const panel = document.getElementById('dang-ky-ca');
    if (panel) {
        const openFromHash = () => { if (window.location.hash === '#dang-ky-ca') panel.open = true; };
        window.addEventListener('hashchange', openFromHash);
        document.querySelectorAll('a[href="#dang-ky-ca"]').forEach(link => link.addEventListener('click', () => { panel.open = true; }));
        openFromHash();
    }
    if (!form) return;
    const custom = document.getElementById('schedule-custom-times');
    const date = form.elements.work_date;
    const repeat = form.elements.repeat_until;
    const summary = document.getElementById('schedule-registration-summary');
    function update() {
        const session = form.elements.session.value;
        custom.hidden = session !== 'custom';
        for (const input of custom.querySelectorAll('input')) {
            input.disabled = session !== 'custom';
            input.required = session === 'custom';
        }
        repeat.min = date.value || date.min;
        const first = Date.parse(date.value);
        const last = Date.parse(repeat.value || date.value);
        const weeks = Math.floor((last - first) / (7 * 86400000)) + 1;
        if (!Number.isFinite(weeks) || weeks < 1) {
            summary.textContent = 'Chọn ngày làm và ngày lặp hợp lệ để xem số ca đăng ký.';
            return;
        }
        const count = weeks * (session === 'both' ? 2 : 1);
        summary.textContent = 'Sẽ đăng ký ' + count + ' ca làm' + (weeks > 1 ? ' trong ' + weeks + ' tuần, cùng thứ với ngày đầu tiên.' : ' cho ngày đã chọn.');
    }
    form.addEventListener('change', update);
    update();
})();
