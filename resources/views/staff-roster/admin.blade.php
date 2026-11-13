@extends('layouts.app')
@section('title', 'Xếp lịch nhân viên')
@push('scripts')<script src="{{ asset('staff-roster.js') }}" defer></script>@endpush
@section('content')
@include('staff-roster.tabs')
<div class="page-head"><div><span class="eyebrow">ĐIỀU PHỐI NHÂN SỰ</span><h1>Xếp lịch nhân viên</h1><p>Xếp tự động hoặc bằng tay, xem lại các ca dự kiến rồi chốt để công bố.</p></div></div>
<form method="get" class="filters roster-filters"><label>Cơ sở<select name="branch_id"><option value="">Tất cả cơ sở</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId===$branch->id)>{{ $branch->shortLabel() }} — {{ $branch->name }}</option>@endforeach</select></label><label>Tuần có ngày<input type="date" name="date" value="{{ $date }}" required></label><button>Xem tuần / cơ sở</button></form>
<nav class="roster-branch-tabs" aria-label="Chọn cơ sở xếp lịch">
    <a href="{{ route('admin.roster.index',['date'=>$date]) }}" @class(['is-active'=>!$branchId]) @if(!$branchId) aria-current="page" @endif>Tất cả cơ sở</a>
    @foreach($branches as $branch)<a href="{{ route('admin.roster.index',['date'=>$date,'branch_id'=>$branch->id]) }}" @class(['is-active'=>$branchId===$branch->id]) @if($branchId===$branch->id) aria-current="page" @endif>{{ $branch->shortLabel() }}</a>@endforeach
</nav>
<p class="roster-scope-summary">Phạm vi xếp và chốt lịch: <strong>{{ $branchId ? $branches->firstWhere('id',$branchId)->shortLabel() : 'Tất cả cơ sở' }}</strong>.</p>
<div class="roster-metrics"><span><strong>{{ $waitingCount }}</strong> WAITING</span><span><strong>{{ $drafts->count() }}</strong> ca dự kiến</span><span><strong>{{ $shifts->where('status','CONFIRMED')->count() }}</strong> ca đã chốt</span></div>
@if(session('rosterResult'))
    <div class="alert" role="status">Còn <strong>{{ session('rosterResult.unfilled') }}</strong> vị trí ca chưa đủ người theo số lượng yêu cầu. Những đăng ký chưa được chọn vẫn WAITING; có thể xếp thêm bằng tay.
        @if(session('rosterResult.skipped'))<ul>@foreach(array_slice(session('rosterResult.skipped'),0,5) as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
    </div>
@endif
<div class="roster-admin-actions">
    <section class="panel"><h2>Xếp lịch tự động</h2><p>Chỉ dùng ca đã đăng ký trong tuần/cơ sở đang lọc. Ưu tiên nhân viên ít giờ làm hơn trong tuần, giữ ca đã xếp, chặn trùng giờ và giới hạn {{ config('staff_roster.max_daily_minutes')/60 }} giờ/ngày.</p>
        <form method="post" action="{{ route('admin.roster.auto') }}" id="roster-auto-form">@csrf<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="branch_id" value="{{ $branchId }}">
            <div class="form-grid"><label>Lễ tân mỗi ca / cơ sở<input type="number" name="receptionist_count" min="1" max="10" value="{{ config('staff_roster.default_staff_per_shift.receptionist') }}" required></label><label>Thu ngân mỗi ca / cơ sở<input type="number" name="cashier_count" min="1" max="10" value="{{ config('staff_roster.default_staff_per_shift.cashier') }}" required></label></div>
            <button>Xếp lịch tự động</button>
        </form>
    </section>
    <section class="panel"><h2>Chốt lịch</h2><p>Chốt {{ $drafts->count() }} ca dự kiến trong tuần/cơ sở đang lọc. Ca đã chốt được giữ nguyên; phần chưa đủ người có thể tiếp tục bổ sung sau.</p>
        <form method="post" action="{{ route('admin.roster.publish') }}" id="roster-publish-form">@csrf<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="branch_id" value="{{ $branchId }}"><input type="hidden" name="plan_version" value="{{ $planVersion }}"><button @disabled($drafts->isEmpty())>Chốt {{ $drafts->count() }} ca dự kiến</button></form>
    </section>
</div>
@foreach($branches->filter(fn ($branch) => !$branchId || $branch->id===$branchId) as $rosterBranch)
    @include('staff-roster.week-grid', ['baseRoute'=>'admin.roster.index', 'adminView'=>true, 'shifts'=>$shifts->where('branch_id',$rosterBranch->id)->values(), 'branchId'=>$rosterBranch->id, 'rosterBranch'=>$rosterBranch, 'showBranchLink'=>!$branchId])
@endforeach
<section class="panel"><h2>Đăng ký và xếp bằng tay</h2><p>Mở từng đăng ký để chọn cơ sở, ngày, giờ làm dự kiến hoặc ghi lý do từ chối.</p>
@if(request('focus'))<p><a href="{{ route('admin.roster.index',['date'=>$date,'branch_id'=>$branchId]) }}">← Hiện tất cả đăng ký của tuần</a></p>@endif
@forelse($registrations as $registration)
    @php
        $plan = $registration->shift;
        $posted = (int)old('request_id') === $registration->id;
        $value = fn ($field,$default) => $posted ? old($field,$default) : $default;
    @endphp
    <details class="roster-request" id="registration-{{ $registration->id }}" @if(request('focus')==$registration->id || $posted) open @endif>
        <summary>{{ $registration->user->name }} · {{ config('staff_roster.roles.'.$registration->role_code) }} · {{ ($plan && in_array($plan->status,['DRAFT','CONFIRMED']) ? $plan->branch : $registration->branch)->shortLabel() }} · {{ $registration->work_date->format('d/m/Y') }} {{ substr($registration->start_time,0,5) }}–{{ substr($registration->end_time,0,5) }} <span class="badge {{ $registration->status==='CONFIRMED' ? 'confirmed' : 'pending' }}">{{ $registration->label() }}</span></summary>
        <p>Đăng ký tại {{ $registration->branch->name }}. @if($registration->note)Ghi chú: {{ $registration->note }}@endif</p>
        @if($registration->decision_note)<p>Phản hồi: {{ $registration->decision_note }}</p>@endif
        @if(in_array($registration->status,['WAITING','ARRANGED']))
            <form method="post" action="{{ route('admin.roster.arrange',$registration) }}" class="roster-manual-form">@csrf<input type="hidden" name="request_id" value="{{ $registration->id }}"><input type="hidden" name="plan_version" value="{{ $plan?->version() ?? 'new' }}">
                <div class="form-grid">
                    <label>Cơ sở xếp ca<select name="branch_id" required>@foreach($registration->user->branches as $branch)<option value="{{ $branch->id }}" @selected($value('branch_id',$plan?->branch_id ?? $registration->branch_id)==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                    <label>Ngày làm dự kiến<input type="date" name="work_date" value="{{ $value('work_date',($plan?->work_date ?? $registration->work_date)->format('Y-m-d')) }}" min="{{ today()->format('Y-m-d') }}" max="{{ today()->addDays(config('staff_roster.max_registration_days'))->format('Y-m-d') }}" required></label>
                    <label>Giờ bắt đầu<input type="time" name="start_time" value="{{ $value('start_time',substr($plan?->start_time ?? $registration->start_time,0,5)) }}" required></label>
                    <label>Giờ kết thúc<input type="time" name="end_time" value="{{ $value('end_time',substr($plan?->end_time ?? $registration->end_time,0,5)) }}" required></label>
                </div>
                <label>Ghi chú xếp ca<textarea name="note" rows="2" maxlength="1000">{{ $value('note',$plan?->note) }}</textarea></label><button>Lưu ca dự kiến</button>
            </form>
            <form method="post" action="{{ route('admin.roster.reject',$registration) }}" class="roster-reject-form">@csrf @method('PATCH')<input type="hidden" name="request_id" value="{{ $registration->id }}"><label>Lý do từ chối<input name="reason" required maxlength="1000"></label><button class="danger">Từ chối đăng ký</button></form>
        @elseif($registration->status==='CONFIRMED' && $plan)
            <p>Chính thức: {{ $plan->work_date->format('d/m/Y') }} · {{ substr($plan->start_time,0,5) }}–{{ substr($plan->end_time,0,5) }} · {{ $plan->branch->name }}</p>
            @if($plan->startsAt()->isFuture())<form method="post" action="{{ route('admin.roster.reopen',$plan) }}">@csrf @method('PATCH')<input type="hidden" name="plan_version" value="{{ $plan->version() }}"><label>Lý do mở chốt để điều chỉnh<input name="reason" required maxlength="1000"></label><button class="button ghost">Mở chốt ca này</button></form>@endif
        @endif
    </details>
@empty<p>Chưa có đăng ký nhân viên trong tuần/cơ sở đã chọn.</p>@endforelse
{{ $registrations->links() }}
</section>
@endsection
