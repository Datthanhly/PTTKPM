@extends('layouts.app')
@section('title','Thông báo')
@push('styles')<link rel="stylesheet" href="{{ asset('shift-transfers.css') }}">@endpush
@section('content')
<div class="page-head"><div><h1>Thông báo</h1><p>Xin chào, {{ $user->name }} · {{ $user->role->name }}</p></div>@if($user->hasRole('patient'))<a class="button" href="{{ route('appointments.create') }}">Đặt lịch khám</a>@endif</div>
@if($user->hasRole('doctor')) @include('shift-transfers.notifications') @endif
@include('staff-roster.notifications')
@if($stats)<form method="get" class="filters"><x-branch-filter :branches="$branches"/><label>Từ ngày<input type="date" name="from" value="{{ request('from',today()->startOfMonth()->format('Y-m-d')) }}"></label><label>Đến ngày<input type="date" name="to" value="{{ request('to',today()->format('Y-m-d')) }}"></label><button>Lọc</button></form>@include('reports.stats')@endif
<section class="panel"><h2>Lịch khám sắp tới</h2><div class="table-wrap"><table class="dashboard-appointments"><thead><tr><th>Mã</th><th>Chi nhánh / Ngày giờ</th><th>Bệnh nhân</th><th>Bác sĩ</th><th>Trạng thái</th>@if($user->hasRole('receptionist'))<th>Thao tác</th>@endif</tr></thead><tbody>
@forelse($appointments as $item)
<tr><td>{{ $item->appointment_code }}</td><td>{{ $item->branch->name }}<br>{{ $item->appointment_date->format('d/m/Y') }} {{ substr($item->start_time,0,5) }}</td><td>{{ $item->patient->full_name }}</td><td>{{ $item->doctor->user->name }}</td><td><span class="badge {{ strtolower($item->status) }}">{{ $item->statusLabel() }}</span></td>
@if($user->hasRole('receptionist'))<td><div class="table-actions dashboard-appointment-actions"><x-reception-appointment-actions :appointment="$item" :compact="true" /></div></td>@endif</tr>
@empty<tr><td colspan="{{ $user->hasRole('receptionist') ? 6 : 5 }}">Chưa có lịch sắp tới.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
