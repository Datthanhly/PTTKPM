<script>
(() => {
    const doctor = document.getElementById('booking-doctor');
    const branch = document.getElementById('booking-branch');
    const date = document.getElementById('booking-date');
    const time = document.getElementById('booking-time');
    const specialty = document.getElementById('booking-specialty');
    const message = document.getElementById('slots-message');
    const retry = document.getElementById('slots-retry');
    const submit = document.getElementById('booking-submit');
    let revision = 0;

    function updateSubmit() {
        if (submit) submit.disabled = time.disabled || !time.value;
    }

    async function refresh(restoreSelection = false) {
        const current = ++revision;
        const selected = restoreSelection ? (time.value || @json(old('start_time', ''))) : '';
        time.disabled = true;
        time.replaceChildren(new Option('Đang kiểm tra giờ khám…', ''));
        message.textContent = '';
        if (retry) retry.hidden = true;
        updateSubmit();

        const doctorId = doctor ? doctor.value : @json($doctorId);
        const branchId = branch ? branch.value : @json($branchId);
        if (!doctorId || !branchId || !date.value) {
            time.replaceChildren(new Option('Chọn đủ chi nhánh, bác sĩ và ngày khám', ''));
            message.textContent = 'Giờ khám sẽ hiện sau khi bạn chọn đủ thông tin phía trên.';
            return;
        }
        message.textContent = 'Đang tải các khung giờ còn trống…';

        try {
            const params = new URLSearchParams({ doctor_id: doctorId, branch_id: branchId, date: date.value });
            const appointmentId = @json($appointmentId);
            if (appointmentId) params.set('appointment_id', appointmentId);
            const response = await fetch(@json(route('appointments.availability')) + '?' + params, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Availability request failed');
            const data = await response.json();
            if (current !== revision) return;

            time.replaceChildren(new Option(data.slots.length ? 'Chọn giờ khám' : 'Không có giờ khám còn trống', ''));
            const groups = new Map();
            for (const slot of data.slots) {
                const period = slot.start_time < '12:00' ? 'Buổi sáng' : (slot.start_time < '18:00' ? 'Buổi chiều' : 'Buổi tối');
                if (!groups.has(period)) {
                    const group = document.createElement('optgroup');
                    group.label = period;
                    groups.set(period, group);
                    time.append(group);
                }
                groups.get(period).append(new Option(
                    slot.start_time + '–' + slot.end_time + ' (còn ' + slot.remaining + ' chỗ)', slot.start_time,
                ));
            }
            time.disabled = data.slots.length === 0;
            if (selected && data.slots.some(slot => slot.start_time === selected)) time.value = selected;
            message.textContent = data.slots.length
                ? 'Có ' + data.slots.length + ' khung giờ còn trống. Chọn giờ khám phù hợp với bạn.'
                : 'Ngày này chưa có ca làm hoặc các giờ khám đã hết chỗ. Vui lòng chọn ngày hoặc bác sĩ khác.';
            updateSubmit();
        } catch (error) {
            if (current !== revision) return;
            time.replaceChildren(new Option('Không tải được giờ khám', ''));
            message.textContent = 'Không tải được giờ khám. Hãy thử tải lại; nếu phiên đăng nhập hết hạn, vui lòng đăng nhập lại.';
            if (retry) retry.hidden = false;
        }
    }

    function filter() {
        if (!doctor) return;
        for (const option of doctor.options) {
            if (!option.value) continue;
            const visible = (!branch.value || option.dataset.branches.split(',').includes(branch.value))
                && (!specialty?.value || option.dataset.specialties.split(',').includes(specialty.value));
            option.hidden = !visible;
            option.disabled = !visible;
        }
        if (doctor.selectedOptions[0]?.disabled) doctor.value = '';
    }

    branch?.addEventListener('change', () => { filter(); refresh(); });
    specialty?.addEventListener('change', () => { filter(); refresh(); });
    doctor?.addEventListener('change', () => refresh());
    date.addEventListener('change', () => refresh());
    time.addEventListener('change', updateSubmit);
    retry?.addEventListener('click', () => refresh(true));
    filter();
    refresh(true);
})();
</script>
