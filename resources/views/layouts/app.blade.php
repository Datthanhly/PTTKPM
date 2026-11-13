@php
    $staffShell = auth()->check() && auth()->user()->hasAnyRole(['doctor','receptionist','cashier','admin']);
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Phòng khám An Tâm')</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}">
    <link rel="stylesheet" href="{{ asset('pagination.css') }}?v={{ filemtime(public_path('pagination.css')) }}">
    @if($staffShell)<link rel="stylesheet" href="{{ asset('staff-shell.css') }}?v={{ filemtime(public_path('staff-shell.css')) }}">@endif
    @stack('styles')
</head>
<body @class(['staff-shell' => $staffShell])>
<header class="site-header">
    <a class="brand" href="{{ route('home') }}"><span>✚</span> Phòng khám An Tâm</a>
    @if($staffShell)
        <button type="button" class="sidebar-toggle" aria-controls="main-navigation" aria-expanded="false" hidden>☰ Menu</button>
        <p class="sidebar-caption">Không gian làm việc</p>
    @endif
    <nav id="main-navigation" aria-label="Điều hướng chính">
        <x-nav-link :href="route('home')" :active="request()->routeIs('home')">Trang chủ</x-nav-link>
        <x-nav-link :href="route('doctors.index')" :active="request()->routeIs('doctors.*')">Bác sĩ</x-nav-link>
        @auth
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard', 'doctor-shifts.respond')">Thông báo</x-nav-link>
            @if(auth()->user()->hasRole('patient'))
                <x-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.index', 'appointments.edit')">Lịch của tôi</x-nav-link>
                <x-nav-link :href="route('patients.profile')" :active="request()->routeIs('patients.profile*')">Hồ sơ</x-nav-link>
                <x-nav-link :href="route('patients.history')" :active="request()->routeIs('patients.history')">Lịch sử khám</x-nav-link>
                <x-nav-link :href="route('follow-ups.index')" :active="request()->routeIs('follow-ups.*')">Tái khám</x-nav-link>
                <x-nav-link class="button small" :href="route('appointments.create')" :active="request()->routeIs('appointments.create')">Đặt lịch</x-nav-link>
            @endif
            @if(auth()->user()->hasAnyRole(['receptionist','doctor','cashier','admin']))
                <x-nav-link :href="route(auth()->user()->hasRole('admin') ? 'admin.roster.index' : (auth()->user()->hasRole('doctor') ? 'staff.appointments' : 'employee-schedules.index'))" :active="request()->routeIs('staff.*', 'doctor-schedules.*', 'employee-schedules.*', 'admin.roster.*') || (auth()->user()->hasRole('receptionist') && request()->routeIs('appointments.edit'))">{{ auth()->user()->hasRole('doctor') ? 'Lịch của tôi' : 'Quản lý thời gian' }}</x-nav-link>
            @endif
            @if(auth()->user()->hasRole('receptionist'))
                <x-nav-link :href="route('patients.index')" :active="request()->routeIs('patients.index')">Bệnh nhân</x-nav-link>
                <x-nav-link :href="route('appointments.create')" :active="request()->routeIs('appointments.create')">Đặt hộ</x-nav-link>
            @endif
            @if(auth()->user()->hasAnyRole(['doctor','receptionist','cashier']))<x-nav-link :href="route('visits.index')" :active="request()->routeIs('visits.*', 'medical-records.*', 'billing.*')">Lượt khám</x-nav-link>@endif
            @if(auth()->user()->hasAnyRole(['doctor','receptionist','cashier']))<x-nav-link :href="route('payroll.mine')" :active="request()->routeIs('payroll.*')">Lương của tôi</x-nav-link>@endif
            @if(auth()->user()->hasPermission('LAB_RESULT_ENTER') || auth()->user()->hasPermission('LAB_RESULT_VERIFY'))<x-nav-link :href="route('diagnostics.index')" :active="request()->routeIs('diagnostics.*', 'measurements.*')">Kết quả</x-nav-link>@endif
            @if(auth()->user()->hasPermission('INVENTORY_MANAGE') || auth()->user()->hasPermission('MEDICINE_DISPENSE'))<x-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*', 'medicines.*')">Kho / cấp thuốc</x-nav-link>@endif
            @if(auth()->user()->hasPermission('REPORT_VIEW'))<x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Báo cáo</x-nav-link>@endif
            @if(auth()->user()->hasRole('admin'))
                <x-nav-link :href="route('admin.payroll.index')" :active="request()->routeIs('admin.payroll.*')">Bảng lương</x-nav-link>
                <x-nav-link :href="route('admin.catalogs')" :active="request()->routeIs('admin.catalogs', 'admin.specialties.*', 'admin.services.*', 'admin.doctors.*', 'admin.schedules.*', 'admin.time-off.*')">Danh mục</x-nav-link>
                <x-nav-link :href="route('admin.settings')" :active="request()->routeIs('admin.settings', 'admin.staff.*', 'admin.branches.*', 'admin.integrations.*')">Cấu hình</x-nav-link>
            @endif
            @if(!$staffShell)
                <x-nav-link :href="route('password.change')" :active="request()->routeIs('password.change*')">Đổi mật khẩu</x-nav-link>
                @if(\App\Services\LocalAccountSession::available(request()))
                    <a href="{{ route('login.separate') }}" target="_blank" rel="noopener">Đăng nhập tài khoản khác</a>
                @endif
                <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">Đăng xuất</button></form>
            @endif
        @else
            <x-nav-link :href="route('login')" :active="request()->routeIs('login', 'login.submit', 'password.*')">Đăng nhập</x-nav-link>
            <x-nav-link class="button small" :href="route('register')" :active="request()->routeIs('register*')">Đăng ký</x-nav-link>
        @endauth
    </nav>
    @if($staffShell)
        <details class="sidebar-account">
            <summary aria-label="Tài khoản: {{ auth()->user()->name }}"><span>{{ auth()->user()->name }}</span><span class="account-chevron" aria-hidden="true">⌃</span></summary>
            <div class="sidebar-account-menu" role="group" aria-label="Tùy chọn tài khoản">
                <x-nav-link :href="route('password.change')" :active="request()->routeIs('password.change*')">Đổi mật khẩu</x-nav-link>
                @if(\App\Services\LocalAccountSession::available(request()))
                    <a href="{{ route('login.separate') }}" target="_blank" rel="noopener">Đăng nhập tài khoản khác</a>
                @endif
                <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">Đăng xuất</button></form>
            </div>
        </details>
    @endif
</header>
<main class="container">
    @auth
        @if(!$staffShell)<p class="account-summary">Đang đăng nhập: <strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->role?->name }}</p>@endif
    @endauth
    @if(session('info'))<div class="alert" role="status">{{ session('info') }}</div>@endif
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error"><strong>Vui lòng kiểm tra lại:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer>© 2026 — Nhóm 3, lớp N05 · Đồ án Phân tích và Thiết kế Phần mềm</footer>
@stack('scripts')
@if($staffShell)<script src="{{ asset('staff-shell.js') }}?v={{ filemtime(public_path('staff-shell.js')) }}" defer></script>@endif
</body>
</html>
