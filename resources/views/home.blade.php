@extends('layouts.app')
@section('title', 'Phòng khám An Tâm')
@section('content')
<section class="hero">
    <div><span class="eyebrow">CHĂM SÓC TẬN TÂM · ĐẶT LỊCH DỄ DÀNG</span><h1>Sức khỏe của bạn,<br><em>ưu tiên của chúng tôi</em></h1><p>Chọn chuyên khoa, bác sĩ và khung giờ phù hợp. Theo dõi lịch khám ngay trên một hệ thống an toàn, rõ ràng.</p><div class="actions">@auth<a class="button" href="{{ route('appointments.create') }}">Đặt lịch khám</a>@else<a class="button" href="{{ route('register') }}">Đăng ký đặt lịch</a><a class="button ghost" href="{{ route('login') }}">Đăng nhập</a>@endauth</div></div>
    <div class="hero-card"><div class="pulse">♥</div><h3>Tiện lợi và minh bạch</h3><p>Lịch làm việc cập nhật theo ngày, không trùng giờ và có mã lịch hẹn riêng.</p><dl><div><dt>{{ $specialties->count() }}</dt><dd>Chuyên khoa</dd></div><div><dt>{{ $doctors->count() }}</dt><dd>Bác sĩ</dd></div></dl></div>
</section>
<section><div class="section-heading"><span class="eyebrow">CHUYÊN MÔN</span><h2>Chuyên khoa nổi bật</h2></div><div class="grid three">@forelse($specialties as $specialty)<article class="card"><div class="icon">✚</div><h3>{{ $specialty->name }}</h3><p>{{ $specialty->description }}</p><small>{{ $specialty->doctors_count }} bác sĩ</small></article>@empty<p>Chưa có chuyên khoa.</p>@endforelse</div></section>
<section><div class="section-heading"><span class="eyebrow">ĐỘI NGŨ</span><h2>Bác sĩ của phòng khám</h2></div><div class="grid three">@foreach($doctors as $doctor)<article class="card doctor"><div class="avatar">BS</div><div><h3>{{ $doctor->user->name }}</h3><p>{{ $doctor->specialty->name }} · {{ $doctor->room }}</p><small>{{ $doctor->qualification }}</small></div></article>@endforeach</div></section>
@endsection
