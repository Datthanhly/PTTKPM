@php($transferLabels = ['PENDING'=>'Chờ trả lời', 'ACCEPTED'=>'Đã đồng ý', 'REJECTED'=>'Đã từ chối', 'CANCELLED'=>'Đã hủy đề nghị'])
<section class="panel" aria-labelledby="incoming-transfers-title">
    <h2 id="incoming-transfers-title">Đề nghị nhận ca gửi đến bạn</h2>
    <p>Chỉ khi bạn đồng ý, ca làm và các lịch hẹn chưa tiếp nhận mới được chuyển sang bạn. Hệ thống kiểm tra lại lịch trống khi xác nhận.</p>
    <div class="transfer-notices">
        @forelse($incomingTransfers as $transfer)
            <article class="transfer-notice" data-transfer-id="{{ $transfer->id }}">
                <div class="transfer-notice-heading"><strong>{{ $transfer->sender->user->name }} nhờ bạn nhận ca</strong><span class="badge {{ $transfer->status === 'ACCEPTED' ? 'confirmed' : '' }}">{{ $transfer->status === 'PENDING' && !$transfer->canRespond() ? 'Đã quá giờ ca' : $transferLabels[$transfer->status] }}</span></div>
                <p>{{ $transfer->work_date->format('d/m/Y') }} · {{ substr($transfer->start_time,0,5) }}–{{ substr($transfer->end_time,0,5) }} · {{ $transfer->branch->name }}</p>
                <p>Lý do: {{ $transfer->note }}</p>
                @if($transfer->canRespond())
                    <form method="post" action="{{ route('doctor-shifts.respond', $transfer) }}" class="transfer-actions">@csrf @method('PATCH')<button name="decision" value="accept">Đồng ý nhận ca</button><button name="decision" value="reject" class="button ghost">Từ chối</button></form>
                @elseif($transfer->status === 'ACCEPTED')
                    <a href="{{ route('staff.appointments', ['date' => $transfer->work_date->format('Y-m-d')]) }}">Mở Lịch của tôi →</a>
                @endif
            </article>
        @empty<p>Bạn chưa có đề nghị nhận ca nào.</p>@endforelse
    </div>
</section>
<section class="panel" aria-labelledby="outgoing-transfers-title">
    <h2 id="outgoing-transfers-title">Đề nghị đổi ca bạn đã gửi</h2>
    <div class="transfer-notices">
        @forelse($outgoingTransfers as $transfer)
            <article class="transfer-notice" data-transfer-id="{{ $transfer->id }}">
                <div class="transfer-notice-heading"><strong>Gửi đến {{ $transfer->receiver->user->name }}</strong><span class="badge {{ $transfer->status === 'ACCEPTED' ? 'confirmed' : '' }}">{{ $transfer->status === 'PENDING' && !$transfer->canRespond() ? 'Đã quá giờ ca' : $transferLabels[$transfer->status] }}</span></div>
                <p>{{ $transfer->work_date->format('d/m/Y') }} · {{ substr($transfer->start_time,0,5) }}–{{ substr($transfer->end_time,0,5) }} · {{ $transfer->branch->name }}</p><p>Lý do: {{ $transfer->note }}</p>
                @if($transfer->status === 'PENDING')<form method="post" action="{{ route('doctor-shifts.respond', $transfer) }}">@csrf @method('PATCH')<button class="button ghost small" name="decision" value="cancel">Hủy đề nghị</button></form>@endif
            </article>
        @empty<p>Bạn chưa gửi đề nghị đổi ca nào.</p>@endforelse
    </div>
</section>
