@extends('layouts.app')
@section('content')
<h1>Lịch sử khám đã công bố</h1>
@forelse($visits as $v)<section class="panel"><h2>{{ $v->checked_in_at->format('d/m/Y') }} · {{ $v->branch->name }}</h2><p>{{ $v->doctor->user->name }} · {{ $v->visit_code }}</p><p>Chẩn đoán: {{ $v->medicalRecord->diagnosis }}</p><p>Hướng xử trí: {{ $v->medicalRecord->conclusion }}</p>
@foreach($v->prescriptions as $p)<h3>Đơn #{{ $p->id }}</h3><ul>@foreach($p->items as $i)<li>{{ $i->medicine->name }} · {{ $i->quantity }} {{ $i->medicine->unit }} · {{ $i->dosage }} · {{ $i->route }} · {{ $i->frequency }} · {{ $i->duration_days }} ngày · {{ $i->instructions }}</li>@endforeach</ul><a href="{{ route('documents.prescription',$p) }}">Tải đơn PDF</a>@endforeach
@foreach($v->serviceOrders as $o)@foreach($o->items as $i)@foreach($i->results as $r)<p>{{ $i->service->name }}: {{ $r->value_text }} {{ $r->unit }} @if($r->attachment_path)<a href="{{ route('diagnostics.download',$r) }}">Tệp kết quả đã duyệt</a>@endif</p>@endforeach @endforeach @endforeach
@if($v->invoice?->status==='ISSUED')<p>Hóa đơn: {{ number_format($v->invoice->total) }}đ · còn nợ {{ number_format($v->invoice->outstanding_cents/100) }}đ · <a href="{{ route('documents.invoice',$v->invoice) }}">Tải PDF</a></p>@endif</section>@empty<p>Chưa có hồ sơ được ký và công bố.</p>@endforelse{{ $visits->links() }}
@endsection
