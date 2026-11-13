<details class="transfer-panel">
    <summary>Đổi ca với bác sĩ đang trống</summary>
    @if($pendingTransfer)
        <p>Đang chờ <strong>{{ $pendingTransfer->receiver->user->name }}</strong> đồng ý nhận ca.</p>
        <a href="{{ route('dashboard') }}">Xem hoặc hủy đề nghị trong Thông báo →</a>
    @elseif($transferError)
        <p>{{ $transferError }}</p>
    @elseif($transferCandidates->isEmpty())
        <p>Chưa có bác sĩ cùng chuyên khoa, được phân công tại chi nhánh này và trống toàn bộ khung giờ của ca. Hãy chọn ca khác hoặc liên hệ quản trị viên.</p>
    @else
        <p>Chọn bác sĩ nhận thay ca này. Giờ làm và lịch hẹn được giữ nguyên; bác sĩ phụ trách chỉ đổi sau khi người nhận đồng ý.</p>
        <form method="post" action="{{ route('doctor-shifts.transfer', $schedule) }}" data-shift-transfer>
            @csrf
            <label>Bác sĩ đang trống<select name="receiver_doctor_id" required>
                <option value="">Chọn bác sĩ nhận ca</option>
                @foreach($transferCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->user->name }} · {{ $candidate->doctor_code }}</option>@endforeach
            </select></label>
            <label>Lý do đổi ca<textarea name="note" rows="2" required maxlength="200" placeholder="Nhập lý do nhờ bác sĩ khác nhận ca"></textarea></label>
            <div class="transfer-message" role="status"></div>
            <button type="submit">Gửi đề nghị nhận ca</button>
        </form>
    @endif
</details>
