@if($rosterNotice)
<section class="panel">
    <h2>{{ $user->hasRole('admin') ? 'Đăng ký lịch làm nhân viên' : 'Lịch làm việc của bạn' }}</h2>
    <p><strong>{{ $rosterNotice['waiting'] }}</strong> đăng ký WAITING · <strong>{{ $rosterNotice['arranged'] }}</strong> ca dự kiến chưa chốt · <strong>{{ $rosterNotice['confirmed'] }}</strong> ca chính thức sắp tới.</p>
    <a class="button small" href="{{ route($user->hasRole('admin') ? 'admin.roster.index' : 'employee-schedules.index', ['date' => \Carbon\Carbon::parse($rosterNotice['date'])->format('Y-m-d')]) }}">{{ $user->hasRole('admin') ? 'Xếp và chốt lịch nhân viên' : 'Xem / đăng ký lịch làm' }}</a>
</section>
@endif
