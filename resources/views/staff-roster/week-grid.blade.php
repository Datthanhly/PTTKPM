@php
    $weekQuery = array_filter(['branch_id' => $branchId]);
@endphp
<section class="panel roster-week" aria-label="Lịch làm nhân viên theo tuần{{ isset($rosterBranch) ? ' — '.$rosterBranch->shortLabel() : '' }}" @isset($rosterBranch) data-roster-branch="{{ $rosterBranch->id }}" @endisset>
    @isset($rosterBranch)
        <div class="roster-branch-heading"><div><span class="eyebrow">{{ $rosterBranch->shortLabel() }}</span><h2>{{ $rosterBranch->name }}</h2></div>@if($showBranchLink ?? false)<a class="button ghost small" href="{{ route($baseRoute,['date'=>$date,'branch_id'=>$rosterBranch->id]) }}">Xếp lịch {{ $rosterBranch->shortLabel() }}</a>@endif</div>
    @endisset
    <div class="roster-week-heading">
        <div class="roster-week-nav">
            <a class="button ghost small" href="{{ route($baseRoute, [...$weekQuery, 'date' => $weekStart->copy()->subWeek()->format('Y-m-d')]) }}" aria-label="Tuần trước">←</a>
            <a class="button ghost small" href="{{ route($baseRoute, [...$weekQuery, 'date' => today()->format('Y-m-d')]) }}">Tuần này</a>
            <a class="button ghost small" href="{{ route($baseRoute, [...$weekQuery, 'date' => $weekStart->copy()->addWeek()->format('Y-m-d')]) }}" aria-label="Tuần sau">→</a>
        </div>
        <h2>{{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</h2>
        <span>{{ $shifts->count() }} ca {{ $adminView ? 'đã xếp' : 'đã chốt' }}</span>
    </div>
    @if($shifts->isEmpty())<p class="roster-empty">{{ $adminView ? 'Chưa có ca được xếp trong tuần/cơ sở này. Chọn đăng ký bên dưới để xếp bằng tay hoặc dùng Xếp lịch tự động.' : 'Bạn chưa có ca đã chốt trong tuần này. Đăng ký mới ở trạng thái WAITING và cần admin xếp, chốt trước khi hiển thị tại đây.' }}</p>@endif
    <p class="roster-scroll-hint">Cuộn ngang bảng để xem đủ các ngày trên màn hình nhỏ.</p>
    <div class="roster-table-wrap">
        <table class="roster-grid">
            <thead><tr><th scope="col">Ca làm</th>
                @foreach(range(0,6) as $offset)
                    @php
                        $day = $weekStart->copy()->addDays($offset);
                    @endphp
                    <th scope="col" @class(['is-today' => $day->isToday()])>{{ config('staff_roster.weekdays')[$day->dayOfWeekIso] }}<strong>{{ $day->format('d/m') }}</strong></th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach(['morning'=>'Ca sáng','afternoon'=>'Ca chiều'] as $period => $label)
                    <tr><th scope="row">{{ $label }}<small>Khung chuẩn<br>{{ implode('–', config('clinic.working_hours.'.$period)) }}</small></th>
                    @foreach(range(0,6) as $offset)
                        @php
                            $day = $weekStart->copy()->addDays($offset);
                            $cellShifts = $shifts->filter(fn ($s) => $s->work_date->isSameDay($day) && ((int)substr($s->start_time,0,2) < 12 ? 'morning' : 'afternoon') === $period);
                        @endphp
                        <td @class(['is-today' => $day->isToday()])>
                            @forelse($cellShifts as $shift)
                                <article class="roster-shift {{ strtolower($shift->status) }}" data-roster-shift-id="{{ $shift->id }}">
                                    <strong>{{ $shift->user->name }}</strong>
                                    <span>{{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }}</span>
                                    <small>{{ config('staff_roster.roles.'.$shift->role_code) }} · {{ $shift->branch->name }}</small>
                                    @if($adminView && (!$shift->user->is_active || $shift->user->status !== 'ACTIVE'))<small>Tài khoản đã ngừng hoạt động; cần điều chỉnh ca.</small>@endif
                                    <span class="badge {{ $shift->status === 'CONFIRMED' ? 'confirmed' : 'pending' }}">{{ $shift->status === 'CONFIRMED' ? 'Đã chốt' : 'Dự kiến' }}</span>
                                    @if($adminView)<a href="{{ route('admin.roster.index', [...$weekQuery, 'date' => $day->format('Y-m-d'), 'focus' => $shift->request_id]) }}#registration-{{ $shift->request_id }}">{{ $shift->status === 'DRAFT' ? 'Chỉnh ca' : 'Xem / mở chốt' }}</a>@endif
                                </article>
                            @empty<span class="roster-no-shift">Chưa xếp</span>@endforelse
                        </td>
                    @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
