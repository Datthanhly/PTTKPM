@extends('layouts.app')
@section('content')
<h1>Kho thuốc và cấp phát</h1><form class="filters" method="get"><x-branch-filter :branches="$branches"/><label>Tên / mã thuốc<input name="q" value="{{ request('q') }}"></label><label class="check"><input type="checkbox" name="low" value="1" @checked(request('low'))>Tồn ≤ 10</label><label class="check"><input type="checkbox" name="expiring" value="1" @checked(request('expiring'))>Hết/sắp hết hạn 30 ngày</label>@if(auth()->user()->hasPermission('INVENTORY_MANAGE'))<label class="check"><input type="checkbox" name="inactive" value="1" @checked(request('inactive'))>Hiện thuốc ngừng sử dụng</label>@endif<button>Lọc</button></form>
@if(auth()->user()->hasPermission('INVENTORY_MANAGE'))<div class="grid two"><section class="form-card"><h2>Thêm thuốc</h2><form method="post" action="{{ route('medicines.store') }}">@csrf<label>Mã<input name="code" required maxlength="30"></label><label>Tên<input name="name" required></label><label>Đơn vị<input name="unit" required maxlength="30"></label><label>Hàm lượng / dạng<input name="strength"></label><label>Giá bán<input type="number" name="price" min="0" step="0.01" required></label><input type="hidden" name="is_active" value="1"><button>Lưu thuốc</button></form></section>
<section class="form-card"><h2>Nhập lô thuốc</h2><form method="post" action="{{ route('inventory.receive') }}">@csrf<label>Chi nhánh<select name="branch_id">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label><label>Thuốc<select name="medicine_id">@foreach($medicines->where('is_active',true) as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></label><label>Số phiếu nhập duy nhất<input name="reference" required maxlength="100"></label><label>Số lô<input name="lot_number" required maxlength="60"></label><label>Hạn dùng<input type="date" name="expiry_date" min="{{ today()->format('Y-m-d') }}" required></label><label>Số lượng<input type="number" name="quantity" min="1" required></label><button>Xác nhận nhập kho</button></form></section></div>@endif
<section class="panel table-wrap" data-inventory-batches><h2>Tồn theo lô / chi nhánh</h2>
@if($batches->getCollection()->contains(fn ($batch) => str_starts_with($batch->lot_number, 'KHOITAO-')))<p>Các lô có mã KHOITAO là dữ liệu tồn đầu; cần đối chiếu số lô, hạn dùng và số lượng theo phiếu nhập thực tế.</p>@endif
<table><thead><tr><th>Chi nhánh</th><th>Thuốc / Lô</th><th>Hạn dùng</th><th>Tồn</th><th>Điều chỉnh</th></tr></thead><tbody>@forelse($batches as $b)<tr><td>{{ $b->branch->name }}</td><td>{{ $b->medicine->name }}<br><small>{{ $b->medicine->strength }}</small><br>{{ $b->lot_number }} @if(!$b->medicine->is_active)<span class="badge">Ngừng sử dụng</span>@endif</td><td>{{ $b->expiry_date->format('d/m/Y') }} @if($b->expiry_date->lt(today()))<strong>HẾT HẠN</strong>@endif</td><td>{{ $b->available_qty }} {{ $b->medicine->unit }}</td><td>@if(auth()->user()->hasPermission('INVENTORY_MANAGE',$b->branch_id))<details><summary>Lập chứng từ điều chỉnh</summary><form method="post" action="{{ route('inventory.adjust',$b) }}">@csrf<label>Tăng (+) / giảm (-)<input type="number" name="delta" required></label><label>Lý do<input name="reason" required maxlength="255"></label><button>Ghi biến động</button></form></details>@endif</td></tr>@empty<tr><td colspan="5">Chưa có lô thuốc.</td></tr>@endforelse</tbody></table></section>{{ $batches->links() }}
@if(auth()->user()->hasPermission('MEDICINE_DISPENSE'))<section class="panel"><h2>Cấp phát từ đơn đã ký</h2><p>Tự chọn lô FEFO còn hạn trong cùng chi nhánh. Nhập lượng thực cấp; thiếu tồn sẽ hoàn tác toàn bộ lần cấp.</p>@forelse($prescriptions as $p)@if(auth()->user()->hasPermission('MEDICINE_DISPENSE',$p->visit->branch_id))<details><summary>Đơn #{{ $p->id }} · {{ $p->visit->patient->full_name }} · {{ $p->visit->branch->name }}</summary><form method="post" action="{{ route('inventory.dispense',$p) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">@foreach($p->items as $i)@php($remaining=$i->quantity-$i->dispensations->sum('quantity'))<label>{{ $i->medicine->name }} · kê {{ $i->quantity }} · còn {{ $remaining }}<input type="number" name="quantities[{{ $i->id }}]" value="0" min="0" max="{{ $remaining }}"></label>@endforeach<button>Xác nhận cấp phát</button><a href="{{ route('documents.prescription',$p) }}">Xem đơn PDF</a></form></details>@endif @empty<p>Chưa có đơn đã ký.</p>@endforelse</section>@endif
@if(auth()->user()->hasPermission('INVENTORY_MANAGE'))
<section class="panel" data-medicine-catalog>
    <h2>Danh mục thuốc</h2>
    <p>Giá ban đầu là giá bán lẻ tham khảo theo nguồn tra cứu; quản trị viên có thể cập nhật giá áp dụng tại phòng khám.</p>
    @foreach($medicines as $m)
        <details>
            <summary>{{ $m->code }} · {{ $m->name }} · {{ $m->is_active ? 'Hoạt động' : 'Ngừng sử dụng' }}</summary>
            @php($source = $medicineSources[$m->code] ?? null)
            @if($source && $m->name === $source['name'] && $m->strength === $source['strength'] && $m->unit === $source['unit'])
                <p><a href="{{ $source['source_url'] }}" target="_blank" rel="noopener noreferrer">Thông tin từ nhà sản xuất</a> · <a href="{{ $source['price_source_url'] }}" target="_blank" rel="noopener noreferrer">Nguồn giá tham khảo ban đầu</a></p>
            @endif
            <form class="form-grid" method="post" action="{{ route('medicines.update',$m) }}">
                @csrf @method('PUT')
                <label>Mã<input name="code" value="{{ $m->code }}" required></label>
                <label>Tên<input name="name" value="{{ $m->name }}" required></label>
                <label>Đơn vị<input name="unit" value="{{ $m->unit }}" required></label>
                <label>Hàm lượng<input name="strength" value="{{ $m->strength }}"></label>
                <label>Giá bán<input type="number" name="price" value="{{ $m->price }}" step="0.01" min="0" required></label>
                <label>Trạng thái<select name="is_active"><option value="1" @selected($m->is_active)>Hoạt động</option><option value="0" @selected(!$m->is_active)>Ngừng sử dụng</option></select></label>
                <button>Lưu</button>
            </form>
        </details>
    @endforeach
</section>
@endif
<section class="panel table-wrap"><h2>Biến động gần nhất</h2><table><thead><tr><th>Thời gian</th><th>ID lô</th><th>Loại</th><th>Tăng/giảm</th><th>Chứng từ</th><th>Người thao tác</th></tr></thead><tbody>@foreach($movements as $m)<tr><td>{{ $m->created_at }}</td><td>{{ $m->batch_id }}</td><td>{{ $m->type }}</td><td>{{ $m->quantity_delta }}</td><td>{{ $m->reference_type }} #{{ $m->reference_id }} · {{ $m->reason }}</td><td>{{ $m->actor_id }}</td></tr>@endforeach</tbody></table></section>
@endsection
