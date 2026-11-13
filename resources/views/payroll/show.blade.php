@extends('layouts.app')
@section('title','Bảng lương tháng '.$payroll->pay_month->format('m/Y'))
@section('content')
@include('payroll.styles')
<a class="payroll-back" href="{{ $adminView ? route('admin.payroll.index',['month'=>$payroll->pay_month->format('Y-m')]) : route('payroll.mine') }}">← {{ $adminView ? 'Quản lý bảng lương' : 'Lương của tôi' }}</a>
<div class="page-head"><div><span class="eyebrow">BẢNG LƯƠNG THÁNG {{ $payroll->pay_month->format('m/Y') }}</span><h1>{{ $payroll->employee_name }}</h1><p>{{ $payroll->role_name }} · {{ $payroll->basisLabel() }}</p></div><span class="badge {{ strtolower($payroll->status) }}">{{ $payroll->statusLabel() }}</span></div>
<div class="payroll-summary"><article><small>Đơn giá áp dụng</small><strong>{{ number_format($payroll->unit_rate,0,',','.') }} đ / {{ $payroll->pay_basis==='HOUR'?'giờ':'ca' }}</strong></article><article><small>Số công tính lương</small><strong>{{ $payroll->pay_basis==='HOUR' ? intdiv($payroll->worked_minutes,60).' giờ '.($payroll->worked_minutes%60).' phút' : $payroll->worked_shifts.' ca' }}</strong></article><article><small>Thực nhận</small><strong>{{ number_format($payroll->net_amount,0,',','.') }} đ</strong></article></div>
<div class="payroll-detail-grid">
<section class="panel"><h2>Chi tiết thu nhập</h2><dl class="payroll-breakdown">
    <div><dt>Lương giờ/ca × đơn giá</dt><dd>{{ number_format($payroll->base_amount,0,',','.') }} đ</dd></div>
    <div><dt>Phụ cấp</dt><dd>+ {{ number_format($payroll->allowance,0,',','.') }} đ</dd></div><div><dt>Thưởng</dt><dd>+ {{ number_format($payroll->bonus,0,',','.') }} đ</dd></div><div><dt>Khấu trừ</dt><dd>− {{ number_format($payroll->deduction,0,',','.') }} đ</dd></div><div class="net"><dt>Thực nhận</dt><dd>{{ number_format($payroll->net_amount,0,',','.') }} đ</dd></div>
    @if($payroll->paid_at)<div><dt>Ghi nhận thanh toán</dt><dd>{{ $payroll->paid_at->format('d/m/Y H:i') }}<br><small>{{ $payroll->paidBy?->name }}</small></dd></div>@endif
    @if($payroll->payment_reference)<div><dt>Mã chứng từ</dt><dd>{{ $payroll->payment_reference }}</dd></div>@endif
</dl>
@if($payroll->note)<p class="payroll-note"><strong>Ghi chú:</strong> {{ $payroll->note }}</p>@endif
@if($payroll->adjustment_reason)<p class="payroll-note"><strong>Lý do điều chỉnh gần nhất:</strong> {{ $payroll->adjustment_reason }}</p>@endif
</section>
<section class="panel payroll-payment"><h2>Trạng thái thanh toán</h2>
@if($adminView)
<p class="payroll-hint">Đánh dấu sau khi đã chi trả thực tế. Thao tác này ghi nhận trạng thái trong hệ thống.</p>
<form method="post" action="{{ route('admin.payroll.payment',$payroll) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $payroll->version }}"><input type="hidden" name="status" value="{{ $payroll->status==='PAID'?'UNPAID':'PAID' }}">
@if($payroll->status!=='PAID')<label>Mã chứng từ / giao dịch (nếu có)<input name="reference" maxlength="200" value="{{ old('reference') }}"></label>@endif
<label>{{ $payroll->status==='PAID'?'Lý do chuyển về chưa thanh toán':'Nội dung xác nhận chi trả' }}<textarea name="reason" required maxlength="1000" rows="2">{{ old('reason') }}</textarea></label><button>{{ $payroll->status==='PAID'?'Chuyển về chưa thanh toán':'Xác nhận đã thanh toán' }}</button></form>
@else<p>{{ $payroll->status==='PAID'?'Admin đã ghi nhận thanh toán bảng lương này.':'Bảng lương này chưa được admin ghi nhận thanh toán.' }}</p><p class="payroll-hint">Nếu số công hoặc số tiền chưa đúng, hãy liên hệ admin để kiểm tra và điều chỉnh.</p>@endif
</section>
</div>
@if($adminView && $payroll->status==='UNPAID')
<section class="panel payroll-edit"><h2>Điều chỉnh số công và tiền lương</h2><p class="payroll-hint">Cách tính giữ {{ $payroll->basisLabel() }}. Sửa đơn giá tại đây chỉ áp dụng cho bảng lương tháng này. Giờ/ca lấy từ lịch cần được đối chiếu với thời gian làm thực tế.</p>
<form method="post" action="{{ route('admin.payroll.update',$payroll) }}">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $payroll->version }}">
<div class="form-grid"><label>Đơn giá (VNĐ / {{ $payroll->pay_basis==='HOUR'?'giờ':'ca' }})<input name="unit_rate" type="number" min="1000" max="100000000" step="1000" required value="{{ old('unit_rate',$payroll->unit_rate) }}"></label>
@if($payroll->pay_basis==='HOUR')
<label>Giờ thực tế<input name="hours" type="number" min="0" max="744" step="1" required value="{{ old('hours',intdiv($payroll->worked_minutes,60)) }}"></label><label>Phút lẻ<input name="minutes" type="number" min="0" max="59" step="1" required value="{{ old('minutes',$payroll->worked_minutes%60) }}"></label><input type="hidden" name="worked_shifts" value="{{ $payroll->worked_shifts }}">
@else
<label>Số ca thực tế<input name="worked_shifts" type="number" min="0" max="1000" step="1" required value="{{ old('worked_shifts',$payroll->worked_shifts) }}"></label><input type="hidden" name="hours" value="{{ intdiv($payroll->worked_minutes,60) }}"><input type="hidden" name="minutes" value="{{ $payroll->worked_minutes%60 }}">
@endif
<label>Phụ cấp (VNĐ)<input name="allowance" type="number" min="0" max="1000000000" step="1" required value="{{ old('allowance',$payroll->allowance) }}"></label><label>Thưởng (VNĐ)<input name="bonus" type="number" min="0" max="1000000000" step="1" required value="{{ old('bonus',$payroll->bonus) }}"></label><label>Khấu trừ (VNĐ)<input name="deduction" type="number" min="0" max="1000000000" step="1" required value="{{ old('deduction',$payroll->deduction) }}"></label></div>
<label>Lý do điều chỉnh<textarea name="reason" required maxlength="1000" rows="2">{{ old('reason') }}</textarea></label><label>Ghi chú cho nhân viên<textarea name="note" maxlength="1000" rows="2">{{ old('note',$payroll->note) }}</textarea></label><button>Lưu và tính lại tiền lương</button></form>
<details><summary>Cập nhật giờ/ca từ lịch làm mới nhất</summary><p class="payroll-hint">Thay số công đã điều chỉnh bằng số công từ các ca đã kết thúc. Giữ đơn giá, phụ cấp, thưởng và khấu trừ của bảng lương này.</p><form method="post" action="{{ route('admin.payroll.recalculate',$payroll) }}">@csrf<input type="hidden" name="version" value="{{ $payroll->version }}"><label>Lý do cập nhật<input name="reason" required maxlength="1000"></label><button class="ghost">Lấy lại số công từ lịch</button></form></details>
</section>
@elseif($adminView)<p class="alert">Bảng lương đã thanh toán được khóa chỉnh số tiền. Chuyển về chưa thanh toán kèm lý do nếu cần sửa.</p>@endif
<section class="panel"><details><summary>Các ca dùng để tính ban đầu · {{ count($payroll->source_shifts['shifts']) }} ca</summary>
<p class="payroll-hint">Cập nhật nguồn gần nhất: {{ $payroll->calculated_at->format('d/m/Y H:i') }} (giờ Việt Nam). Đây là lịch làm đã kết thúc, không phải dữ liệu chấm công. Bác sĩ: chỉ ca mở, không trùng lịch nghỉ. Nhân viên: chỉ ca đã chốt. Ca tương lai/đang diễn ra chưa được tính. Lương theo giờ làm tròn đến đồng gần nhất; số công admin điều chỉnh được thể hiện phía trên.</p>
@if($payroll->source_shifts['excluded_leave_shifts'])<p>Đã bỏ qua {{ $payroll->source_shifts['excluded_leave_shifts'] }} ca bác sĩ trùng lịch nghỉ.</p>@endif
<div class="table-wrap"><table class="payroll-source-table"><thead><tr><th>Ngày làm</th><th>Cơ sở</th><th>Giờ làm</th><th>Thời lượng</th></tr></thead><tbody>@forelse($payroll->source_shifts['shifts'] as $shift)<tr><td>{{ \Carbon\Carbon::parse($shift['date'])->format('d/m/Y') }}</td><td>{{ $shift['branch'] }}</td><td>{{ $shift['start'] }}–{{ $shift['end'] }}</td><td>{{ intdiv($shift['minutes'],60) }} giờ {{ $shift['minutes']%60 }} phút</td></tr>@empty<tr><td colspan="4" class="empty">Không có ca đủ điều kiện tại thời điểm lấy dữ liệu.</td></tr>@endforelse</tbody></table></div>
</details></section>
@endsection
