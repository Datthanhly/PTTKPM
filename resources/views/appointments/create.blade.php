@extends('layouts.app')
@section('title', 'Đặt lịch khám')
@section('content')
<div class="page-head"><div><span class="eyebrow">ĐẶT LỊCH TRỰC TUYẾN</span><h1>Chọn bác sĩ và thời gian</h1><p>Giờ bắt đầu phải khớp với bước 30 phút trong ca làm việc.</p></div></div>
<section class="form-card booking-card"><form method="post" action="{{ route('appointments.store') }}">@csrf
<label>Bác sĩ<select name="doctor_id" required><option value="">-- Chọn bác sĩ --</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(old('doctor_id')==$doctor->id)>{{ $doctor->user->name }} — {{ $doctor->specialty->name }}</option>@endforeach</select></label>
<div class="form-grid"><label>Ngày khám<input type="date" name="appointment_date" min="{{ today()->format('Y-m-d') }}" value="{{ old('appointment_date') }}" required></label><label>Giờ bắt đầu<input type="time" name="start_time" step="1800" value="{{ old('start_time') }}" required></label></div>
<label>Lý do khám<textarea name="reason" rows="4" required>{{ old('reason') }}</textarea></label><button class="button wide">Xác nhận đặt lịch</button></form></section>
<section class="panel"><div class="panel-head"><h2>Ca làm việc đang mở</h2></div><div class="schedule-grid">@foreach($doctors as $doctor)<article><h3>{{ $doctor->user->name }}</h3><small>{{ $doctor->specialty->name }}</small>@forelse($doctor->schedules->take(8) as $shift)<p><strong>{{ $shift->work_date->format('d/m') }}</strong> {{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }} ({{ $shift->slot_minutes }}')</p>@empty<p>Chưa có lịch.</p>@endforelse</article>@endforeach</div></section>
@endsection
