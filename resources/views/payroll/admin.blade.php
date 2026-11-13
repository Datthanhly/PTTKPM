@extends('layouts.app')
@section('title','Quản lý bảng lương')
@section('content')
@include('payroll.styles')
<div class="page-head"><div><span class="eyebrow">NHÂN SỰ & TIỀN LƯƠNG</span><h1>Quản lý bảng lương</h1><p>Thiết lập đơn giá riêng, tính lương theo giờ hoặc ca và theo dõi thanh toán.</p></div></div>
<form method="get" class="panel payroll-filter-grid">
    <label>Tháng lương<input type="month" name="month" required value="{{ $month }}"></label>
    <label>Cơ sở<select name="branch_id"><option value="">Tất cả cơ sở</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
    <label>Vai trò<select name="role"><option value="">Tất cả nhân viên</option>@foreach(['doctor'=>'Bác sĩ','receptionist'=>'Lễ tân','cashier'=>'Thu ngân'] as $code=>$name)<option value="{{ $code }}" @selected(request('role')===$code)>{{ $name }}</option>@endforeach</select></label>
    <label>Tên / email<input name="q" maxlength="100" value="{{ request('q') }}"></label>
    <label>Thanh toán<select name="status"><option value="">Tất cả trạng thái</option><option value="UNPAID" @selected(request('status')==='UNPAID')>Chưa thanh toán</option><option value="PAID" @selected(request('status')==='PAID')>Đã thanh toán</option></select></label><button>Lọc</button>
</form>
<section class="panel"><div class="payroll-section-head"><h2>Bảng lương tháng {{ \Carbon\Carbon::createFromFormat('!Y-m',$month)->format('m/Y') }}</h2><p>Tiền lương = số giờ/ca × đơn giá + phụ cấp + thưởng − khấu trừ.</p></div><div class="table-wrap"><table class="payroll-table"><thead><tr><th>Nhân viên</th><th>Số công / đơn giá</th><th>Thực nhận</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td><strong>{{ $entry->employee_name }}</strong><br><small>{{ $entry->role_name }}</small></td><td>{{ $entry->pay_basis === 'HOUR' ? intdiv($entry->worked_minutes,60).' giờ '.($entry->worked_minutes%60).' phút' : $entry->worked_shifts.' ca' }}<br><small>{{ number_format($entry->unit_rate,0,',','.') }} đ/{{ $entry->pay_basis === 'HOUR' ? 'giờ' : 'ca' }}</small></td><td class="payroll-money">{{ number_format($entry->net_amount,0,',','.') }} đ</td><td><span class="badge {{ strtolower($entry->status) }}">{{ $entry->statusLabel() }}</span></td><td><a href="{{ route('admin.payroll.show',$entry) }}">Xem / chỉnh sửa</a></td></tr>
@empty<tr><td colspan="5" class="empty">Chưa có bảng lương phù hợp. Thiết lập đơn giá và bấm Tính lương ở danh sách bên dưới.</td></tr>@endforelse
</tbody></table></div>{{ $entries->links() }}</section>
<section class="panel"><div class="payroll-section-head"><h2>Đơn giá từng nhân viên</h2><p>Đơn giá do admin nhập. Thay đổi ở đây áp dụng khi tạo bảng lương mới, không sửa bảng lương cũ.</p></div>
@foreach($employees as $employee)
<details class="salary-person" id="salary-{{ $employee->id }}" @if(old('_employee')==$employee->id) open @endif>
    <summary><span>{{ $employee->name }}<small>{{ $employee->role->name }} · {{ $employee->branches->pluck('name')->join(', ') }}</small></span><span class="salary-rate">{{ $employee->salaryProfile ? number_format($employee->salaryProfile->unit_rate,0,',','.').' đ / '.($employee->salaryProfile->pay_basis==='HOUR'?'giờ':'ca') : 'Chưa có đơn giá' }}</span></summary>
    <form class="salary-profile-form payroll-inline-form" method="post" action="{{ route('admin.payroll.profile',$employee) }}">@csrf @method('PUT')
        <input type="hidden" name="_employee" value="{{ $employee->id }}"><input type="hidden" name="version" value="{{ $employee->salaryProfile?->version ?? 0 }}">
        <label>Cách tính<select name="pay_basis"><option value="HOUR" @selected((old('_employee')==$employee->id ? old('pay_basis') : $employee->salaryProfile?->pay_basis)!=='SHIFT')>Theo giờ làm</option><option value="SHIFT" @selected((old('_employee')==$employee->id ? old('pay_basis') : $employee->salaryProfile?->pay_basis)==='SHIFT')>Theo ca làm</option></select></label>
        <label>Đơn giá (VNĐ / giờ hoặc ca)<input type="number" name="unit_rate" min="1000" max="100000000" step="1000" required value="{{ old('_employee')==$employee->id ? old('unit_rate') : $employee->salaryProfile?->unit_rate }}"></label><button>Lưu đơn giá</button>
    </form>
    @if($employee->salaryProfile)
    <form class="salary-generate-form payroll-inline-form" method="post" action="{{ route('admin.payroll.generate',$employee) }}">@csrf<input type="hidden" name="profile_version" value="{{ $employee->salaryProfile->version }}"><label>Tháng cần tính<input type="month" name="month" min="2000-01" max="{{ today()->format('Y-m') }}" required value="{{ $month }}"></label><button>Tính lương</button><small>Chỉ lấy ca đã kết thúc. Nếu tháng đã có bảng lương, mở bảng hiện có để cập nhật.</small></form>
    @endif
</details>
@endforeach
@if($employees->isEmpty())<p class="empty">Không tìm thấy nhân viên.</p>@endif
{{ $employees->links() }}</section>
@endsection
