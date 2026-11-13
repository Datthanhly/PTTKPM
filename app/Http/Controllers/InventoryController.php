<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use App\Services\ClinicAccess;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('INVENTORY_MANAGE') || auth()->user()->hasPermission('MEDICINE_DISPENSE'), 403);
        $permitted = ClinicAccess::branches()->filter(fn ($b) => auth()->user()->hasPermission('INVENTORY_MANAGE', $b->id) || auth()->user()->hasPermission('MEDICINE_DISPENSE', $b->id))->pluck('id');
        $showInactive = $request->boolean('inactive') && auth()->user()->hasPermission('INVENTORY_MANAGE');
        $query = ClinicAccess::scope(MedicineBatch::with(['medicine', 'branch'])->whereIn('branch_id', $permitted)->orderBy('expiry_date'));
        $query->when(! $showInactive, fn ($q) => $q->whereHas('medicine', fn ($m) => $m->where('is_active', true)));
        if ($request->boolean('low')) {
            $query->where('available_qty', '<=', 10);
        }
        if ($request->boolean('expiring')) {
            $query->whereDate('expiry_date', '<=', today()->addDays(30));
        }
        if ($request->filled('q')) {
            $query->whereHas('medicine', fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('code', 'like', '%'.$request->q.'%'));
        }
        $prescriptions = Prescription::where('status', 'SIGNED')->whereHas('visit', fn ($q) => ClinicAccess::scope($q)->whereIn('branch_id', $permitted))->with(['visit.patient', 'visit.branch', 'items.medicine', 'items.dispensations'])->latest()->limit(30)->get();
        $batchIds = ClinicAccess::scope(MedicineBatch::whereIn('branch_id', $permitted))->select('id');

        return view('inventory.index', ['batches' => $query->paginate(20)->withQueryString(), 'medicines' => Medicine::when(! $showInactive, fn ($q) => $q->where('is_active', true))->orderBy('name')->get(), 'branches' => ClinicAccess::branches(), 'prescriptions' => $prescriptions,
            'medicineSources' => config('clinic_medicines.products'),
            'movements' => DB::table('stock_movements')->whereIn('batch_id', $batchIds)->latest('id')->limit(30)->get()]);
    }

    public function medicine(Request $request, ?Medicine $medicine = null)
    {
        abort_unless(auth()->user()->hasPermission('INVENTORY_MANAGE'), 403);
        $data = $request->validate(['code' => ['required', 'max:30', \Illuminate\Validation\Rule::unique('medicines')->ignore($medicine?->id)], 'name' => ['required', 'max:255'], 'unit' => ['required', 'max:30'], 'strength' => ['nullable', 'max:255'], 'price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999'], 'is_active' => ['required', 'boolean']]);
        $medicine ? $medicine->update($data) : $medicine = Medicine::create($data);
        AuditLog::record('medicine.saved', $medicine);

        return back()->with('success', 'Đã lưu thuốc.');
    }

    public function receive(Request $request, InventoryService $inventory)
    {
        $data = $request->validate(['branch_id' => ['required', 'exists:branches,id'], 'medicine_id' => ['required', 'exists:medicines,id'], 'lot_number' => ['required', 'max:60'],
            'expiry_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'reference' => ['required', 'max:100']]);
        $inventory->receive($data);

        return back()->with('success', 'Đã nhập lô và ghi biến động tồn.');
    }

    public function dispense(Request $request, Prescription $prescription, InventoryService $inventory)
    {
        $data = $request->validate(['quantities' => ['required', 'array', 'min:1'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:10000'], 'idempotency_key' => ['required', 'string', 'max:100']]);
        $inventory->dispense($prescription, $data['quantities'], $data['idempotency_key']);

        return back()->with('success', 'Đã cấp phát và ghi nhận lô thuốc.');
    }

    public function adjust(Request $request, MedicineBatch $batch, InventoryService $inventory)
    {
        $data = $request->validate(['delta' => ['required', 'integer', 'between:-1000000,1000000'], 'reason' => ['required', 'string', 'max:255']]);
        $inventory->adjust($batch, $data['delta'], $data['reason']);

        return back()->with('success', 'Đã lập biến động điều chỉnh tồn.');
    }
}
