@extends('layouts.app')
@section('title', 'Lịch khám của tôi')
@section('content')
<div class="page-head"><div><span class="eyebrow">HỒ SƠ CÁ NHÂN</span><h1>Lịch khám của tôi</h1></div><a class="button" href="{{ route('appointments.create') }}">+ Đặt lịch mới</a></div>
<div class="appointment-list">
@forelse($appointments as $item)
<article class="appointment-card">
    <div class="date-box"><strong>{{ $item->appointment_date->format('d') }}</strong><span>Tháng {{ $item->appointment_date->format('m') }}</span></div>
    <div class="appointment-main"><div class="row"><h3>{{ $item->doctor->user->name }}</h3><span class="badge {{ strtolower($item->status) }}">{{ $item->status }}</span></div><p>{{ $item->doctor->specialty->name }} · {{ substr($item->start_time,0,5) }}–{{ substr($item->end_time,0,5) }} · {{ $item->doctor->room }}</p><small>Mã {{ $item->appointment_code }} · Lý do: {{ $item->reason }}</small>
    @if($item->medicalRecord && $item->medicalRecord->published_at)<div class="record"><strong>Kết quả khám:</strong> {{ $item->medicalRecord->diagnosis }} @if($item->medicalRecord->prescription)<br><strong>Đơn thuốc:</strong> {{ $item->medicalRecord->prescription }}@endif</div>@endif
    </div>
    @if(in_array($item->status, ['PENDING','CONFIRMED']))<form class="cancel-form" method="post" action="{{ route('appointments.cancel', $item) }}">@csrf @method('PATCH')<input name="cancel_reason" placeholder="Lý do hủy" required><button class="danger">Hủy lịch</button></form>@endif
</article>
@empty<div class="empty-state"><div class="icon">⌁</div><h3>Bạn chưa có lịch khám</h3><a class="button" href="{{ route('appointments.create') }}">Đặt lịch đầu tiên</a></div>@endforelse
</div>{{ $appointments->links() }}
@endsection
