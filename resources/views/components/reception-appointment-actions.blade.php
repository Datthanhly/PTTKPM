@props(['appointment','compact'=>false])
@if(auth()->user()->hasRole('receptionist') && auth()->user()->hasPermission('VISIT_CHECK_IN',$appointment->branch_id))
    @if($appointment->status==='PENDING')<x-status-button :appointment="$appointment" status="CONFIRMED" label="Xác nhận" />@endif
    @if($appointment->status==='CONFIRMED')
        <x-status-button :appointment="$appointment" status="CHECKED_IN" label="Tiếp nhận" :disabled="!$appointment->appointment_date->isToday()" hint="Chỉ tiếp nhận vào đúng ngày hẹn" />
        <x-status-button :appointment="$appointment" status="NO_SHOW" label="Vắng mặt" :disabled="\Carbon\Carbon::parse($appointment->appointment_date->format('Y-m-d').' '.$appointment->end_time)->isFuture()" hint="Chỉ ghi nhận vắng mặt sau khi hết giờ hẹn" />
        @if(!$appointment->appointment_date->isToday())<small class="appointment-action-hint">Tiếp nhận vào ngày {{ $appointment->appointment_date->format('d/m/Y') }}.</small>@endif
    @endif
    @if(!$compact && in_array($appointment->status,['PENDING','CONFIRMED']))
        <a class="button tiny" href="{{ route('appointments.edit',$appointment) }}">Đổi lịch</a>
        <form method="post" action="{{ route('appointments.cancel',$appointment) }}">@csrf @method('PATCH')<input name="cancel_reason" placeholder="Lý do hủy" required maxlength="1000" aria-label="Lý do hủy lịch {{ $appointment->appointment_code }}"><button class="danger">Hủy</button></form>
    @endif
    @if($compact && $appointment->doctor_schedule_id)<a class="button tiny ghost" href="{{ route('staff.shifts.show',$appointment->doctor_schedule_id) }}">Xem ca</a>@endif
@endif
