@extends('layouts.app')
@section('title','Đặt lịch khám')
@section('content')
<div class="page-head">
    <div><h1>Chọn chi nhánh, bác sĩ và giờ khám</h1><p>Chọn giờ còn trống trong ca sáng hoặc chiều. Giờ hiển thị theo múi giờ Việt Nam.</p></div>
</div>
@if(!auth()->user()->hasRole('patient'))
    <form method="get" class="filters">
        <label>Tìm bệnh nhân theo mã/tên/điện thoại<input name="patient_q" value="{{ request('patient_q') }}"></label>
        <button>Tìm bệnh nhân để đặt hộ</button>
    </form>
@endif
<section class="form-card booking-card">
    <form method="post" action="{{ route('appointments.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string) Illuminate\Support\Str::uuid()) }}">
        @if(!auth()->user()->hasRole('patient'))
            <label>Bệnh nhân<select name="patient_id" required>
                @foreach($patients as $p)
                    <option value="{{ $p->id }}" @selected(old('patient_id', request('patient_id'))==$p->id)>{{ $p->patient_code }} · {{ $p->full_name }}</option>
                @endforeach
            </select></label>
            <p>Tìm/tạo hồ sơ tại <a href="{{ route('patients.index') }}">Bệnh nhân</a>.</p>
        @endif
        <label>Chi nhánh<select id="booking-branch" name="branch_id" required>
            <option value="">Chọn chi nhánh</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" @selected(old('branch_id', $branches->count()===1 ? $branches->first()->id : request('branch_id'))==$b->id)>{{ $b->name }}</option>
            @endforeach
        </select></label>
        <label>Chuyên khoa<select id="booking-specialty" name="specialty_id">
            <option value="">Tất cả chuyên khoa</option>
            @foreach($specialties as $s)
                <option value="{{ $s->id }}" @selected(old('specialty_id', request('specialty_id'))==$s->id)>{{ $s->name }}</option>
            @endforeach
        </select></label>
        <label>Bác sĩ<select id="booking-doctor" name="doctor_id" required>
            <option value="">Chọn bác sĩ</option>
            @foreach($doctors as $d)
                <option value="{{ $d->id }}" data-branches="{{ $d->user->branches->pluck('id')->join(',') }}" data-specialties="{{ $d->specialties->pluck('id')->push($d->specialty_id)->unique()->join(',') }}" @selected(old('doctor_id', request('doctor_id'))==$d->id)>{{ $d->user->name }} · {{ $d->specialty->name }}</option>
            @endforeach
        </select></label>
        <label>Ngày khám<input id="booking-date" type="date" name="appointment_date" min="{{ today()->format('Y-m-d') }}" value="{{ old('appointment_date', today()->format('Y-m-d')) }}" required></label>
        <label>Giờ còn trống<select id="booking-time" name="start_time" required aria-describedby="slots-message" disabled>
            <option value="">Chọn chi nhánh, bác sĩ và ngày khám trước</option>
        </select></label>
        <p id="slots-message" role="status" aria-live="polite"></p>
        <button id="slots-retry" class="button ghost small" type="button" hidden>Thử tải lại giờ khám</button>
        <noscript><p>Vui lòng bật JavaScript để xem và chọn giờ khám còn trống.</p></noscript>
        <label>Lý do khám<textarea name="reason" rows="3" required maxlength="2000">{{ old('reason') }}</textarea></label>
        <button id="booking-submit" class="button wide" type="submit" disabled>Xác nhận đặt lịch</button>
    </form>
</section>
@include('appointments.slot-script', ['doctorId'=>null,'branchId'=>null,'appointmentId'=>null])
@endsection
