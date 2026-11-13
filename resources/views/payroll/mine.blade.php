@extends('layouts.app')
@section('title','Lương của tôi')
@section('content')
@include('payroll.styles')
<div class="page-head"><div><span class="eyebrow">THU NHẬP CÁ NHÂN</span><h1>Lương của tôi</h1><p>Bảng lương và trạng thái thanh toán của riêng bạn.</p></div></div>
@if($profile)
<section class="payroll-rate-card"><span>Đơn giá hiện tại</span><strong>{{ number_format($profile->unit_rate,0,',','.') }} đ / {{ $profile->pay_basis === 'HOUR' ? 'giờ' : 'ca' }}</strong><small>Bảng lương cũ giữ đơn giá tại thời điểm tính. Chỉ admin được điều chỉnh.</small></section>
@else<p class="alert">Admin chưa thiết lập đơn giá lương của bạn.</p>@endif
<form method="get" class="filters payroll-filters"><label>Tháng lương<input type="month" name="month" value="{{ request('month') }}"></label><button>Lọc</button><a class="button ghost" href="{{ route('payroll.mine') }}">Tất cả tháng</a></form>
<section class="panel"><div class="table-wrap"><table class="payroll-table"><thead><tr><th>Tháng</th><th>Cách tính</th><th>Số công</th><th>Thực nhận</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>
@forelse($entries as $entry)
<tr><td>{{ $entry->pay_month->format('m/Y') }}</td><td>{{ $entry->basisLabel() }}<br><small>{{ number_format($entry->unit_rate,0,',','.') }} đ/{{ $entry->pay_basis === 'HOUR' ? 'giờ' : 'ca' }}</small></td><td>{{ $entry->pay_basis === 'HOUR' ? intdiv($entry->worked_minutes,60).' giờ '.($entry->worked_minutes%60).' phút' : $entry->worked_shifts.' ca' }}</td><td class="payroll-money">{{ number_format($entry->net_amount,0,',','.') }} đ</td><td><span class="badge {{ strtolower($entry->status) }}">{{ $entry->statusLabel() }}</span></td><td><a href="{{ route('payroll.mine.show',$entry) }}">Xem bảng lương</a></td></tr>
@empty<tr><td colspan="6" class="empty">Chưa có bảng lương{{ request('month') ? ' trong tháng đã chọn' : '' }}. Admin sẽ lập bảng lương sau khi kiểm tra lịch làm.</td></tr>@endforelse
</tbody></table></div></section>
{{ $entries->links() }}
@endsection
