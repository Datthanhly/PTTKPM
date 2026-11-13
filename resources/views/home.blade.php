@extends('layouts.app')
@section('title', 'Phòng khám An Tâm')
@section('content')
<section class="hero">
    <div><span class="eyebrow">CHĂM SÓC TẬN TÂM · ĐẶT LỊCH DỄ DÀNG</span><h1>Sức khỏe của bạn,<br><em>ưu tiên của chúng tôi</em></h1><p>Chọn chuyên khoa, bác sĩ và khung giờ phù hợp. Theo dõi lịch khám ngay trên một hệ thống an toàn, rõ ràng.</p>
        <div class="actions">
            @auth
                @if(auth()->user()->hasAnyRole(['patient', 'receptionist']))
                    <a class="button" href="{{ route('appointments.create') }}">{{ auth()->user()->hasRole('patient') ? 'Đặt lịch khám' : 'Đặt lịch hộ bệnh nhân' }}</a>
                @else
                    <a class="button" href="{{ route('dashboard') }}">Xem thông báo</a>
                @endif
            @else
                <a class="button" href="{{ route('register') }}">Đăng ký đặt lịch</a>
                <a class="button ghost" href="{{ route('login') }}">Đăng nhập</a>
            @endauth
        </div>
    </div>
    <div class="hero-card"><div class="pulse">♥</div><h3>Tiện lợi và minh bạch</h3><p>Lịch làm việc cập nhật theo ngày, không trùng giờ và có mã lịch hẹn riêng.</p><dl><div><dt>{{ $specialties->count() }}</dt><dd>Chuyên khoa</dd></div><div><dt>{{ $doctors->count() }}</dt><dd>Bác sĩ</dd></div></dl></div>
</section>
<section aria-labelledby="clinic-services-heading">
    <div class="section-heading"><span class="eyebrow">DỊCH VỤ PHÒNG KHÁM</span><h2 id="clinic-services-heading">Chăm sóc sức khỏe toàn diện</h2><p>Bốn nhóm dịch vụ đồng hành cùng sức khỏe thể chất và tinh thần của bạn.</p></div>
    <div class="grid two clinic-service-grid">
        @foreach($serviceGroups as $group)
            <article class="card clinic-service-card">
                <span class="service-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div><h3>{{ $group['name'] }}</h3><p>{{ $group['description'] }}</p></div>
            </article>
        @endforeach
    </div>
</section>
<section aria-labelledby="specialties-heading">
    <div class="section-heading"><span class="eyebrow">CHUYÊN MÔN</span><h2 id="specialties-heading">Tìm bác sĩ theo chuyên khoa</h2></div>
    <div class="specialty-links">
        @forelse($specialties as $specialty)
            <a href="{{ route('doctors.index', ['specialty_id' => $specialty->id]) }}">{{ $specialty->name }} <span>{{ $specialty->doctors_count }} bác sĩ</span></a>
        @empty
            <p>Chưa có chuyên khoa.</p>
        @endforelse
    </div>
</section>
<section><div class="section-heading"><span class="eyebrow">ĐỘI NGŨ</span><h2>Bác sĩ của phòng khám</h2></div><div class="grid three">@foreach($doctors as $doctor)<article class="card doctor"><div class="avatar">BS</div><div><h3>{{ $doctor->user->name }}</h3><p>{{ $doctor->specialty->name }} · {{ $doctor->room }}</p><small>{{ $doctor->qualification }}</small></div></article>@endforeach</div></section>
@endsection
