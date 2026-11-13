@extends('layouts.app')
@section('title', 'Khôi phục mật khẩu')
@section('content')
<section class="form-card">
    <h1>Khôi phục mật khẩu</h1>
    <p>Nhập email đăng ký để nhận liên kết đặt lại mật khẩu. Liên kết chỉ dùng một lần và hết hạn sau 60 phút.</p>
    @isset($message)<div class="alert error" role="alert" id="recovery-limit-message">{{ $message }}</div>@endisset
    <form method="post" action="{{ route('password.email') }}" id="password-recovery-form">
        @csrf
        <label>Email<input type="email" name="email" value="{{ $email ?? old('email') }}" autocomplete="email" maxlength="255" required></label>
        <p id="recovery-wait" role="status" aria-live="polite" data-retry-after="{{ $retryAfter ?? 0 }}">@if(($retryAfter ?? 0) > 0)Vui lòng chờ {{ $retryAfter }} giây trước khi gửi lại.@endif</p>
        <button type="submit" @disabled(($retryAfter ?? 0) > 0)>Gửi liên kết đặt lại mật khẩu</button>
        <noscript><p>Nếu đang trong thời gian chờ, hãy tải lại trang sau khi hết thời gian để gửi tiếp.</p></noscript>
    </form>
    <p><a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
</section>
@endsection
@push('scripts')<script src="{{ asset('password-recovery.js') }}" defer></script>@endpush
