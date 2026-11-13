@php($statusLabels = \App\Models\Appointment::STATUS_LABELS)
<div class="shift-summary" data-shift-content>
    <div><h3>{{ $schedule->doctor->user->name }}</h3><p>{{ $schedule->work_date->format('d/m/Y') }} · {{ substr($schedule->start_time,0,5) }}–{{ substr($schedule->end_time,0,5) }}<br>{{ $schedule->branch->name }} · {{ $schedule->doctor->room }}</p><small>{{ $schedule->slot_minutes }} phút/lượt · {{ $schedule->capacity }} chỗ/khung giờ @if(!$schedule->is_open) · Ca đã đóng @endif</small></div>
    <span class="badge">{{ $appointments->total() }} lịch hẹn{{ request()->filled('q') || request()->filled('status') ? ' phù hợp' : '' }}</span>
</div>
@if(auth()->user()->hasRole('doctor')) @include('shift-transfers.form') @endif
<form class="filters shift-filters" method="get" action="{{ route('staff.shifts.show', $schedule) }}" data-shift-filter>
    <label>Mã / tên / điện thoại<input name="q" value="{{ request('q') }}" maxlength="255" placeholder="Tìm trong ca này"></label>
    <label>Trạng thái<select name="status"><option value="">Tất cả trạng thái</option>@foreach($statusLabels as $code=>$label)<option value="{{ $code }}" @selected(request('status')===$code)>{{ $label }}</option>@endforeach</select></label>
    <button type="submit">Lọc lịch hẹn</button>
</form>
<div class="table-wrap"><table class="shift-appointments-table"><thead><tr><th>Giờ hẹn</th><th>Bệnh nhân</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($appointments as $item)
    <tr><td><strong>{{ substr($item->start_time,0,5) }}–{{ substr($item->end_time,0,5) }}</strong><br><small>{{ $item->appointment_code }}</small></td><td>{{ $item->patient->full_name }}<br><small>{{ $item->patient->patient_code }} · {{ $item->patient->phone }}</small></td><td>@if(auth()->user()->hasAnyRole(['doctor','receptionist'])){{ $item->reason }}@else — @endif</td><td><span class="badge {{ strtolower($item->status) }}">{{ $statusLabels[$item->status] ?? $item->status }}</span></td><td><div class="table-actions">
        <x-reception-appointment-actions :appointment="$item" />
        @if(auth()->user()->hasRole('doctor'))
            @if($item->status==='CHECKED_IN')<x-status-button :appointment="$item" status="IN_PROGRESS" label="Bắt đầu khám" />@endif
            @if($item->visit)<a class="button tiny" href="{{ route('visits.show',$item->visit) }}">Mở lượt khám</a>@endif
        @endif
        @if(auth()->user()->hasPermission('INVOICE_ISSUE',$item->branch_id) && $item->status==='COMPLETED')<a class="button tiny" href="{{ route('billing.create',$item) }}">Hóa đơn</a>@endif
    </div></td></tr>
@empty
    <tr><td colspan="5" class="empty">{{ request()->filled('q') || request()->filled('status') ? 'Không có lịch hẹn phù hợp trong ca này.' : 'Ca làm này chưa có lịch hẹn.' }}</td></tr>
@endforelse
</tbody></table></div>
@if($appointments->hasPages())
    <nav class="shift-pagination" aria-label="Phân trang lịch hẹn">
        @if($appointments->previousPageUrl())<a class="button ghost small" href="{{ $appointments->previousPageUrl() }}">← Trang trước</a>@endif
        <span>Trang {{ $appointments->currentPage() }}/{{ $appointments->lastPage() }}</span>
        @if($appointments->nextPageUrl())<a class="button ghost small" href="{{ $appointments->nextPageUrl() }}">Trang sau →</a>@endif
    </nav>
@endif
