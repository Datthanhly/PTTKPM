@extends('layouts.app')
@section('title', 'Quản lý lịch hẹn')
@section('content')
<div class="page-head"><div><span class="eyebrow">VẬN HÀNH PHÒNG KHÁM</span><h1>Quản lý lịch hẹn</h1></div><form class="filters" method="get"><input type="date" name="date" value="{{ request('date', today()->format('Y-m-d')) }}"><select name="status"><option value="">Tất cả trạng thái</option>@foreach(['PENDING','CONFIRMED','CHECKED_IN','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button>Lọc</button></form></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Bệnh nhân</th><th>Bác sĩ</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($appointments as $item)<tr><td><strong>{{ substr($item->start_time,0,5) }}</strong><br><small>{{ $item->appointment_code }}</small></td><td>{{ $item->patient->full_name }}<br><small>{{ $item->patient->phone }}</small></td><td>{{ $item->doctor->user->name }}<br><small>{{ $item->doctor->room }}</small></td><td>{{ $item->reason }}</td><td><span class="badge {{ strtolower($item->status) }}">{{ $item->status }}</span></td><td><div class="table-actions">
@if(auth()->user()->hasAnyRole(['receptionist','admin']))
 @if($item->status==='PENDING')<x-status-button :appointment="$item" status="CONFIRMED" label="Xác nhận" />@endif
 @if($item->status==='CONFIRMED')<x-status-button :appointment="$item" status="CHECKED_IN" label="Check-in" /><x-status-button :appointment="$item" status="NO_SHOW" label="Vắng" />@endif
@endif
@if(auth()->user()->hasAnyRole(['doctor','admin']))
 @if($item->status==='CHECKED_IN')<x-status-button :appointment="$item" status="IN_PROGRESS" label="Bắt đầu khám" />@endif
 @if($item->status==='IN_PROGRESS')<a class="button tiny" href="{{ route('medical-records.edit',$item) }}">Lập hồ sơ</a>@endif
@endif
@if(auth()->user()->hasAnyRole(['cashier','admin']) && $item->status==='COMPLETED')<a class="button tiny" href="{{ route('billing.create',$item) }}">Hóa đơn</a>@endif
</div></td></tr>@empty<tr><td colspan="6" class="empty">Không có lịch trong ngày đã chọn.</td></tr>@endforelse
</tbody></table></div></section>{{ $appointments->links() }}
@endsection
