@php($scheduleErrors = $errors->getBag('doctorSchedule'))
<details id="dang-ky-ca" class="schedule-registration-toggle" @if($scheduleErrors->any()) open @endif>
<summary>Đăng ký ca làm <span>Chọn ca sáng, chiều hoặc giờ tùy chỉnh</span></summary>
@if($scheduleErrors->any())
    <div class="alert error" role="alert"><strong>Chưa thể đăng ký ca làm:</strong><ul>@foreach($scheduleErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if($branches->isEmpty())
    <div class="alert error">Bạn chưa được phân công tại chi nhánh đang hoạt động. Vui lòng liên hệ quản trị viên để được phân công trước khi đăng ký ca làm.</div>
@else
    <div class="schedule-registration-grid">
        <section class="form-card schedule-registration-card">
            <h2>Thông tin ca làm</h2>
            <form method="post" action="{{ route('doctor-schedules.store') }}" id="doctor-schedule-form">
                @csrf
                <div class="form-grid">
                    <label>Chi nhánh<select name="branch_id" required>
                        @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach
                    </select></label>
                    <label>Ngày làm đầu tiên<input type="date" name="work_date" min="{{ today()->format('Y-m-d') }}" max="{{ today()->addDays(90)->format('Y-m-d') }}" value="{{ old('work_date', today()->addDay()->format('Y-m-d')) }}" required></label>
                </div>
                <fieldset class="schedule-period-fieldset">
                    <legend>Chọn ca làm</legend>
                    <div class="schedule-periods">
                        @foreach(['morning' => ['Ca sáng', implode('–', config('clinic.working_hours.morning'))], 'afternoon' => ['Ca chiều', implode('–', config('clinic.working_hours.afternoon'))], 'both' => ['Cả sáng và chiều', 'Đăng ký 2 ca riêng, có giờ nghỉ trưa'], 'custom' => ['Giờ tùy chỉnh', 'Tự chọn giờ bắt đầu và kết thúc']] as $value => [$name, $description])
                            <label class="schedule-period"><input type="radio" name="session" value="{{ $value }}" @checked(old('session','morning')===$value) required><span><strong>{{ $name }}</strong><small>{{ $description }}</small></span></label>
                        @endforeach
                    </div>
                </fieldset>
                <div id="schedule-custom-times">
                    <div class="form-grid">
                        <label>Giờ bắt đầu<input type="time" name="start_time" value="{{ old('start_time', '08:00') }}"></label>
                        <label>Giờ kết thúc<input type="time" name="end_time" value="{{ old('end_time', '11:30') }}"></label>
                    </div>
                    <p class="schedule-field-help">Giờ tùy chỉnh phải trong cùng ngày và đủ thời lượng ít nhất một lượt khám.</p>
                </div>
                <div class="form-grid">
                    <label>Thời lượng mỗi lượt<select name="slot_minutes" required>@foreach([15,20,30,45,60] as $minutes)<option value="{{ $minutes }}" @selected(old('slot_minutes', config('clinic.slot_minutes'))==$minutes)>{{ $minutes }} phút</option>@endforeach</select></label>
                    <label>Số chỗ mỗi khung giờ<input type="number" name="capacity" min="1" max="20" value="{{ old('capacity',1) }}" required></label>
                </div>
                <label>Lặp cùng thứ hằng tuần đến ngày (tùy chọn)<input type="date" name="repeat_until" min="{{ old('work_date', today()->addDay()->format('Y-m-d')) }}" max="{{ today()->addDays(90)->format('Y-m-d') }}" value="{{ old('repeat_until') }}"></label>
                <p class="schedule-field-help">Để trống nếu chỉ đăng ký một ngày. Có thể đăng ký trước tối đa 90 ngày.</p>
                <p id="schedule-registration-summary" class="schedule-registration-summary" role="status">Ca mới sẽ xuất hiện trong Lịch của tôi sau khi đăng ký thành công.</p>
                <button class="button wide" type="submit">Đăng ký ca làm</button>
            </form>
        </section>
        <aside class="panel schedule-registration-help">
            <span class="eyebrow">TRƯỚC KHI ĐĂNG KÝ</span><h2>Chủ động sắp xếp thời gian</h2>
            <p>Ca làm được gắn với tài khoản bác sĩ đang đăng nhập và chi nhánh đã được phân công.</p>
            <ul><li>Không đăng ký ca đã bắt đầu.</li><li>Không trùng ca đã có, kể cả tại chi nhánh khác.</li><li>Không trùng lịch nghỉ đã được ghi nhận.</li><li>Nếu đăng ký nhiều ca, tất cả phải hợp lệ để lưu cùng lúc.</li></ul>
            <p>Đăng ký thành công sẽ mở giờ khám cho bệnh nhân đặt lịch. Bấm vào ca trên lịch tuần để xem ai đã hẹn.</p>
        </aside>
    </div>
@endif
</details>
