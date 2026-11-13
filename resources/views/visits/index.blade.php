@extends('layouts.app')
@section('title', 'Lượt khám và danh sách chờ')
@section('content')
<div class="page-head"><h1>Lượt khám và danh sách chờ</h1></div>
<form class="filters" method="get"><x-branch-filter :branches="$branches"/><label>Ngày tiếp nhận<input type="date" name="date" value="{{ request('date') }}"></label><label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach(['WAITING','IN_PROGRESS','WAITING_RESULT','COMPLETED','CLOSED','CANCELLED'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select></label><button>Lọc</button></form>
<section class="panel table-wrap"><table><thead><tr><th>Mã lượt / Chi nhánh</th><th>Bệnh nhân</th><th>Bác sĩ</th><th>Tiếp nhận</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($visits as $v)<tr><td>{{ $v->visit_code }}<br><small>{{ $v->branch->name }}</small></td><td>{{ $v->patient->full_name }}</td><td>{{ $v->doctor->user->name }}</td><td>{{ $v->checked_in_at->format('H:i d/m/Y') }}</td><td>{{ $v->status }}</td><td>
@if(auth()->user()->hasRole('doctor'))<a class="button tiny" href="{{ route('visits.show',$v) }}">Mở lượt khám</a>@endif
@if(auth()->user()->hasPermission('INVOICE_ISSUE',$v->branch_id) && in_array($v->status,['COMPLETED','CLOSED']))<a class="button tiny" href="{{ route('billing.show',$v) }}">Hóa đơn</a>@endif
</td></tr>@empty<tr><td colspan="6">Chưa có lượt khám.</td></tr>@endforelse</tbody></table></section>{{ $visits->links() }}
@endsection
