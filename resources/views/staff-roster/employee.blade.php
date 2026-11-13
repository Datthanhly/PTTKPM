@extends('layouts.app')
@section('title', 'Lịch làm của tôi')
@push('scripts')<script src="{{ asset('staff-roster.js') }}" defer></script>@endpush
@section('content')
@include('staff-roster.tabs')
<div class="page-head"><div><span class="eyebrow">LỊCH LÀM VIỆC NHÂN VIÊN</span><h1>Lịch làm của tôi</h1><p>Đăng ký → WAITING → admin xếp dự kiến → chốt lịch chính thức.</p></div><a class="button" href="#staff-register">+ Đăng ký lịch làm</a></div>
@include('staff-roster.registration-form')
<form method="get" class="filters roster-filters"><x-branch-filter :branches="$branches"/><label>Tuần có ngày<input type="date" name="date" value="{{ $date }}" required></label><button>Xem lịch làm</button></form>
@include('staff-roster.week-grid', ['baseRoute'=>'employee-schedules.index', 'adminView'=>false])
<section class="panel"><h2>Đăng ký của tôi trong tuần</h2><p>Ca dự kiến chưa được tính là lịch chính thức. Đăng ký không được chọn khi xếp tự động vẫn ở trạng thái WAITING.</p>
<div class="roster-table-wrap"><table class="roster-request-table"><thead><tr><th>Ca đã đăng ký</th><th>Trạng thái / phản hồi</th><th>Thao tác</th></tr></thead><tbody>
@forelse($registrations as $registration)
    <tr><td><strong>{{ $registration->work_date->format('d/m/Y') }} · {{ substr($registration->start_time,0,5) }}–{{ substr($registration->end_time,0,5) }}</strong><br>{{ $registration->branch->name }}@if($registration->note)<p>{{ $registration->note }}</p>@endif</td>
    <td><span class="badge {{ $registration->status === 'CONFIRMED' ? 'confirmed' : 'pending' }}">{{ $registration->label() }}</span>
        @if($registration->shift && in_array($registration->shift->status,['DRAFT','CONFIRMED']))<p>{{ $registration->shift->status === 'DRAFT' ? 'Dự kiến' : 'Chính thức' }}: {{ $registration->shift->work_date->format('d/m/Y') }} · {{ substr($registration->shift->start_time,0,5) }}–{{ substr($registration->shift->end_time,0,5) }} · {{ $registration->shift->branch->name }}</p>@endif
        @if($registration->decision_note)<p>{{ $registration->decision_note }}</p>@endif</td>
    <td>@if(in_array($registration->status,['WAITING','ARRANGED']))<form method="post" action="{{ route('employee-schedules.withdraw',$registration) }}">@csrf @method('PATCH')<button class="button ghost small">Rút đăng ký</button></form>@else<span>—</span>@endif</td></tr>
@empty<tr><td colspan="3">Chưa có đăng ký trong tuần này.</td></tr>@endforelse
</tbody></table></div>{{ $registrations->links() }}</section>
@endsection
