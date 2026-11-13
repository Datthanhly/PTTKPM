@extends('layouts.app')
@section('title', 'Đăng nhập')
@section('content')
<div class="auth-wrap">
    <section class="form-card">
        <span class="eyebrow">CHÀO MỪNG TRỞ LẠI</span>
        <h1>Đăng nhập</h1>
        <p>Truy cập hồ sơ và lịch khám của bạn.</p>
        <form method="post" action="{{ route('login.submit') }}">
            @csrf
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
            <label for="login-password">Mật khẩu</label>
            <input id="login-password" type="password" name="password" required autocomplete="current-password">
            <div class="login-options">
                <label class="check"><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label>
                <button class="password-toggle" type="button" aria-controls="login-password" aria-pressed="false" hidden>Hiện mật khẩu</button>
            </div>
            <button class="button wide" type="submit">Đăng nhập</button>
        </form>
        <p class="muted"><a href="{{ route('password.request') }}">Quên mật khẩu?</a></p>
        <p class="muted">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký bệnh nhân</a></p>
        @if(\App\Services\LocalAccountSession::available(request()))
            <p class="muted"><a href="{{ route('login.separate') }}" target="_blank" rel="noopener">Mở phiên riêng ở tab mới</a> để đăng nhập tài khoản khác.</p>
        @endif
    </section>
</div>
<script src="{{ asset('login.js') }}" defer></script>
@endsection
