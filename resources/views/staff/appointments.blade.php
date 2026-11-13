@extends('layouts.app')
@section('title', auth()->user()->hasRole('doctor') ? 'Lịch của tôi' : 'Quản lý thời gian')
@push('styles')<link rel="stylesheet" href="{{ asset('calendar.css') }}">@endpush
@push('styles')<link rel="stylesheet" href="{{ asset('shift-transfers.css') }}">@endpush
@push('scripts')<script src="{{ asset('calendar.js') }}?v={{ filemtime(public_path('calendar.js')) }}" defer></script>@endpush
@if(auth()->user()->hasRole('doctor'))
    @push('styles')<link rel="stylesheet" href="{{ asset('doctor-schedules.css') }}">@endpush
    @push('scripts')<script src="{{ asset('doctor-schedules.js') }}" defer></script>@endpush
@endif
@section('content')
@include('staff-roster.tabs')
@php
    $weekQuery = array_filter(['branch_id' => request('branch_id'), 'doctor_id' => $selectedDoctor?->id]);
    $byDay = $schedules->groupBy(fn ($shift) => $shift->work_date->format('Y-m-d'));
    $height = ($endHour - $startHour) * 60;
    $dayNames = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ nhật'];
@endphp
<div class="page-head calendar-page-head">
    <div><span class="eyebrow">LỊCH LÀM VIỆC</span><h1>{{ auth()->user()->hasRole('doctor') ? 'Lịch của tôi' : 'Quản lý thời gian' }}</h1><p>{{ $selectedDoctor?->user->name ?? 'Lịch làm việc bác sĩ' }} · Bấm vào ca để xem những người đã đặt lịch.</p></div>
    @if(auth()->user()->hasRole('doctor'))<a class="button" href="#dang-ky-ca">+ Đăng ký ca làm</a>@endif
</div>
@if(auth()->user()->hasRole('doctor')) @include('doctor-schedules.form') @endif
<form method="get" class="filters calendar-filters" action="{{ route('staff.appointments') }}">
    <x-branch-filter :branches="$branches"/>
    @if(!auth()->user()->hasRole('doctor'))
        <label>Bác sĩ<select name="doctor_id">
            @php
                $hasSelectedDoctor = false;
            @endphp
            @foreach($branches->sortBy('id') as $branch)
                @php
                    $branchDoctors = $doctors->filter(fn ($doctor) => $doctor->user->branches->contains('id', $branch->id));
                    $branchLabel = $branch->shortLabel();
                @endphp
                @if($branchDoctors->isNotEmpty())
                    <optgroup label="{{ $branchLabel }} — {{ $branch->name }}">
                        @foreach($branchDoctors as $doctor)
                            @php
                                $isSelectedDoctor = !$hasSelectedDoctor && $selectedDoctor?->id === $doctor->id;
                                $hasSelectedDoctor = $hasSelectedDoctor || $isSelectedDoctor;
                            @endphp
                            <option value="{{ $doctor->id }}" @selected($isSelectedDoctor)>{{ $doctor->user->name }} · {{ $branchLabel }}</option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
            @if($doctors->isEmpty())<option value="">Chưa có bác sĩ được phân công</option>@endif
        </select></label>
    @else
        <input type="hidden" name="doctor_id" value="{{ $selectedDoctor?->id }}">
    @endif
    <label>Tuần có ngày<input type="date" name="date" value="{{ $date->format('Y-m-d') }}" required></label>
    <button type="submit">Xem lịch</button>
</form>
<section class="weekly-calendar" aria-label="Lịch làm việc theo tuần">
    <div class="calendar-toolbar">
        <div class="calendar-week-nav">
            <a class="button ghost small" href="{{ route('staff.appointments', [...$weekQuery, 'date' => $weekStart->copy()->subWeek()->format('Y-m-d')]) }}" aria-label="Tuần trước">←</a>
            <a class="button ghost small" href="{{ route('staff.appointments', [...$weekQuery, 'date' => today()->format('Y-m-d')]) }}">Tuần này</a>
            <a class="button ghost small" href="{{ route('staff.appointments', [...$weekQuery, 'date' => $weekStart->copy()->addWeek()->format('Y-m-d')]) }}" aria-label="Tuần sau">→</a>
        </div>
        <h2>{{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</h2>
        <div class="calendar-legend"><span class="legend-dot morning"></span>Sáng <span class="legend-dot afternoon"></span>Chiều <span>{{ $schedules->count() }} ca</span></div>
    </div>
    @if($schedules->isEmpty())<p class="calendar-empty-note">Chưa có ca làm việc trong tuần này với bộ lọc đã chọn. Hãy chọn tuần khác hoặc liên hệ quản trị viên để bổ sung ca.</p>@endif
    <p class="calendar-scroll-hint">Trên điện thoại, cuộn ngang để xem đủ các ngày trong tuần.</p>
    <div class="calendar-scroll" tabindex="0" aria-label="Bảng lịch tuần, có thể cuộn ngang">
        <div class="calendar-grid">
            <div class="calendar-corner">Giờ VN<br><small>GMT+7</small></div>
            @foreach($days as $day)
                <div class="calendar-day-heading @if($day->isToday()) is-today @endif"><span>{{ $dayNames[$loop->index] }}</span><strong>{{ $day->format('d') }}</strong><small>{{ $day->format('m/Y') }}</small></div>
            @endforeach
            <div class="calendar-time-axis" style="height:{{ $height }}px">
                @foreach(range($startHour, $endHour - 1) as $hour)<span style="top:{{ ($hour - $startHour) * 60 }}px">{{ sprintf('%02d:00', $hour) }}</span>@endforeach
            </div>
            @foreach($days as $day)
                <div class="calendar-day-column @if($day->isToday()) is-today @endif" style="height:{{ $height }}px" aria-label="{{ $dayNames[$loop->index] }} {{ $day->format('d/m/Y') }}">
                    @foreach($byDay->get($day->format('Y-m-d'), collect()) as $shift)
                        @php
                            $from = (int) substr($shift->start_time, 0, 2) * 60 + (int) substr($shift->start_time, 3, 2);
                            $until = (int) substr($shift->end_time, 0, 2) * 60 + (int) substr($shift->end_time, 3, 2);
                            $period = $from < 720 ? 'morning' : 'afternoon';
                            $label = $from < 720 ? 'Ca sáng' : ($from < 1080 ? 'Ca chiều' : 'Ca tối');
                            $title = $label.' · '.$day->format('d/m/Y').' · '.substr($shift->start_time,0,5).'–'.substr($shift->end_time,0,5);
                        @endphp
                        <a class="shift-card {{ $period }} @if(!$shift->is_open) is-closed @endif @if($until - $from < 100) is-short @endif" href="{{ route('staff.shifts.show', $shift) }}" data-shift-title="{{ $title }}" style="top:{{ $from - $startHour * 60 }}px;height:{{ max(16, $until - $from) }}px" aria-label="{{ $title }} · {{ $shift->branch->name }} · {{ $shift->appointments_count }} lịch hẹn">
                            <div class="shift-card-heading"><strong>{{ $label }}</strong><span>{{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }}</span></div>
                            <div class="shift-card-body"><span>{{ $shift->branch->name }}</span><small>{{ $shift->doctor->room ?: 'Chưa có phòng' }}</small><span class="shift-count">{{ $shift->appointments_count }} lịch hẹn</span><small>{{ $shift->is_open ? 'Bấm để xem lịch hẹn' : 'Ca đã đóng · Bấm để xem' }}</small></div>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
<dialog id="shift-dialog" class="shift-dialog" aria-labelledby="shift-dialog-title">
    <div class="shift-dialog-heading"><h2 id="shift-dialog-title">Lịch hẹn trong ca</h2><button id="shift-dialog-close" class="button ghost small" type="button" aria-label="Đóng danh sách lịch hẹn">Đóng ×</button></div>
    <div id="shift-dialog-body" class="shift-dialog-body" aria-live="polite"></div>
</dialog>
@endsection
