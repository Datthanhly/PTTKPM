<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Phòng khám An Tâm')</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body>
<header class="site-header">
    <a class="brand" href="{{ route('home') }}"><span>✚</span> Phòng khám An Tâm</a>
    <nav>
        <a href="{{ route('home') }}">Trang chủ</a>
        @auth
            <a href="{{ route('dashboard') }}">Bảng điều khiển</a>
            @if(auth()->user()->hasRole('patient'))
                <a href="{{ route('appointments.index') }}">Lịch của tôi</a>
                <a class="button small" href="{{ route('appointments.create') }}">Đặt lịch</a>
            @endif
            @if(auth()->user()->hasAnyRole(['receptionist','doctor','cashier','admin']))
                <a href="{{ route('staff.appointments') }}">Quản lý lịch</a>
            @endif
            @if(auth()->user()->hasRole('admin'))<a href="{{ route('admin.catalogs') }}">Danh mục</a>@endif
            <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">Đăng xuất</button></form>
        @else
            <a href="{{ route('login') }}">Đăng nhập</a>
            <a class="button small" href="{{ route('register') }}">Đăng ký</a>
        @endauth
    </nav>
</header>
<main class="container">
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error"><strong>Vui lòng kiểm tra lại:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer>© 2026 — Nhóm 3, lớp N05 · Đồ án Phân tích và Thiết kế Phần mềm</footer>
</body>
</html>
