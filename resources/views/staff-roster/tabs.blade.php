@if(auth()->user()->hasAnyRole(['receptionist','cashier','admin']))
@once
    @push('styles')<link rel="stylesheet" href="{{ asset('staff-roster.css') }}?v={{ filemtime(public_path('staff-roster.css')) }}">@endpush
@endonce
<nav class="roster-tabs" aria-label="Chọn lịch">
    @if(auth()->user()->hasRole('admin'))
        <a href="{{ route('admin.roster.index') }}" class="@if(request()->routeIs('admin.roster.*')) is-active @endif">Xếp lịch nhân viên</a>
    @else
        <a href="{{ route('employee-schedules.index') }}" class="@if(request()->routeIs('employee-schedules.*')) is-active @endif">Lịch làm của tôi</a>
    @endif
    <a href="{{ route('staff.appointments') }}" class="@if(request()->routeIs('staff.*')) is-active @endif">Lịch khám bác sĩ</a>
</nav>
@endif
