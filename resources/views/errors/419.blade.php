@extends('layouts.app')
@section('title', 'Biểu mẫu đã hết hạn')
@section('content')
<section class="form-card booking-card">
    <span class="eyebrow">BIỂU MẪU ĐÃ HẾT HẠN</span>
    <h1>Vui lòng mở lại trang</h1>
    <p>Phiên làm việc hoặc mã xác nhận của biểu mẫu đã hết hạn. Thao tác vừa gửi chưa được thực hiện.</p>
    <p>Mở lại trang và kiểm tra thông tin trước khi gửi lại.</p>
    <div class="actions">
        @auth
            <a class="button" href="{{ route('dashboard') }}">Về thông báo</a>
        @else
            <a class="button" href="{{ route('login') }}">Đăng nhập lại</a>
        @endauth
        <a class="button ghost" href="{{ route('home') }}">Trang chủ</a>
    </div>
</section>
@endsection
