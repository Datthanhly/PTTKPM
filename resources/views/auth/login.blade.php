@extends('layouts.app')
@section('title', 'Đăng nhập')
@section('content')
<div class="auth-wrap"><section class="form-card"><span class="eyebrow">CHÀO MỪNG TRỞ LẠI</span><h1>Đăng nhập</h1><p>Truy cập hồ sơ và lịch khám của bạn.</p><form method="post" action="{{ route('login.submit') }}">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus></label><label>Mật khẩu<input type="password" name="password" required></label><label class="check"><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label><button class="button wide">Đăng nhập</button></form><p class="muted">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký bệnh nhân</a></p></section></div>
@endsection
