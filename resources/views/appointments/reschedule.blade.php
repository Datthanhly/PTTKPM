@extends('layouts.app')
@section('content')
<h1>Đổi lịch {{ $appointment->appointment_code }}</h1><p>{{ $appointment->branch->name }} · {{ $appointment->doctor->user->name }} · cũ: {{ $appointment->appointment_date->format('d/m/Y') }} {{ substr($appointment->start_time,0,5) }}</p>
@if(auth()->user()->hasRole('patient') && !$appointment->patientCanChange())
    <div class="alert error" role="alert">Không thể đổi lịch đăng ký. Lịch phải ở trạng thái chờ xác nhận hoặc đã xác nhận và còn ít nhất {{ config('clinic.cancellation_hours') }} giờ trước giờ khám.</div>
    <a class="button" href="{{ route('appointments.index') }}">Về lịch khám của tôi</a>
@else
    @if(auth()->user()->hasRole('patient'))
        <p class="alert">Bạn được đổi lịch trước {{ $appointment->patientChangeDeadline()->format('H:i d/m/Y') }} (ít nhất {{ config('clinic.cancellation_hours') }} giờ trước giờ khám hiện tại).</p>
    @endif
    <form class="form-card" method="post" action="{{ route('appointments.reschedule',$appointment) }}">
        @csrf @method('PATCH')
        <label>Ngày mới<input id="booking-date" type="date" name="appointment_date" min="{{ today()->format('Y-m-d') }}" value="{{ old('appointment_date') }}" required></label>
        <label>Giờ mới<select id="booking-time" name="start_time" required aria-describedby="slots-message" disabled></select></label>
        <p id="slots-message" role="status"></p>
        <label>Lý do đổi<input name="reason" value="{{ old('reason') }}" required maxlength="255"></label>
        <button id="booking-submit" type="submit" disabled>Đổi lịch</button>
    </form>
    @include('appointments.slot-script',['doctorId'=>$appointment->doctor_id,'branchId'=>$appointment->branch_id,'appointmentId'=>$appointment->id])
@endif
@endsection
