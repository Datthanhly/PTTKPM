@php($registrationErrors = $errors->getBag('staffRegistration'))
<details id="staff-register" class="roster-registration" @if($registrationErrors->any()) open @endif>
    <summary>Đăng ký lịch làm <small>Gửi thời gian có thể làm; admin sẽ sắp xếp và chốt</small></summary>
    @if($registrationErrors->any())<div class="alert error" role="alert"><ul>@foreach($registrationErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($branches->isEmpty())
        <p>Bạn chưa được phân công cơ sở hoạt động. Liên hệ admin để được phân công trước khi đăng ký.</p>
    @else
        <form method="post" action="{{ route('employee-schedules.store') }}" id="staff-registration-form">
            @csrf
            <div class="form-grid">
                <label>Cơ sở đăng ký<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <label>Ca đăng ký<select name="session" required><option value="morning" @selected(old('session','both')==='morning')>Ca sáng · {{ implode('–', config('clinic.working_hours.morning')) }}</option><option value="afternoon" @selected(old('session','both')==='afternoon')>Ca chiều · {{ implode('–', config('clinic.working_hours.afternoon')) }}</option><option value="both" @selected(old('session','both')==='both')>Cả sáng và chiều · 2 ca riêng</option></select></label>
                <label>Từ ngày<input type="date" name="from_date" min="{{ today()->format('Y-m-d') }}" max="{{ today()->addDays(config('staff_roster.max_registration_days'))->format('Y-m-d') }}" value="{{ old('from_date', today()->addDay()->format('Y-m-d')) }}" required></label>
                <label>Đến ngày<input type="date" name="to_date" min="{{ today()->format('Y-m-d') }}" max="{{ today()->addDays(config('staff_roster.max_registration_days'))->format('Y-m-d') }}" value="{{ old('to_date', today()->addDays(7)->format('Y-m-d')) }}" required></label>
            </div>
            <fieldset><legend>Các thứ có thể làm trong khoảng ngày</legend><div class="roster-weekdays">@foreach(config('staff_roster.weekdays') as $number => $name)<label class="check"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number,old('weekdays',[1,2,3,4,5,6])))>{{ $name }}</label>@endforeach</div></fieldset>
            <label>Ghi chú đăng ký<textarea name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea></label>
            <p>Đăng ký được lưu ở trạng thái <strong>WAITING</strong>, chưa phải lịch làm chính thức. Có thể rút trước khi admin chốt. Mỗi lần gửi được lưu toàn bộ hoặc không lưu ca nào nếu có lỗi.</p>
            <button type="submit">Nộp đăng ký lịch làm</button>
        </form>
    @endif
</details>
