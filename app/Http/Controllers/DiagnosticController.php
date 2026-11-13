<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeviceMeasurement;
use App\Models\DiagnosticResult;
use App\Models\ServiceOrderItem;
use App\Services\ClinicAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DiagnosticController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        abort_unless($u->hasPermission('LAB_RESULT_ENTER') || $u->hasPermission('LAB_RESULT_VERIFY'), 403);
        $branchIds = ClinicAccess::branches()->filter(fn ($b) => $u->hasPermission('LAB_RESULT_ENTER', $b->id) || $u->hasPermission('LAB_RESULT_VERIFY', $b->id))->pluck('id');
        $items = ServiceOrderItem::whereHas('order.visit', fn ($q) => $q->whereIn('branch_id', $branchIds))->with(['order.visit.patient', 'order.visit.branch', 'service', 'results'])->latest()->paginate(20);

        return view('diagnostics.index', compact('items'));
    }

    public function enter(Request $request, ServiceOrderItem $item)
    {
        ClinicAccess::require('LAB_RESULT_ENTER', $item->order->visit->branch_id);
        $data = $request->validate(['value_text' => ['required', 'string', 'max:10000'], 'unit' => ['nullable', 'string', 'max:40'], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        DB::transaction(function () use ($item, $data, $request) {
            $item = ServiceOrderItem::lockForUpdate()->findOrFail($item->id);
            abort_if(in_array($item->status, ['COMPLETED', 'CANCELLED']) || in_array($item->order->visit->status, ['COMPLETED', 'CLOSED', 'CANCELLED']), 409, 'Chỉ định đã đóng.');
            $path = $request->file('attachment')?->store('diagnostics', 'local');
            $item->results()->create(['value_text' => $data['value_text'], 'unit' => $data['unit'] ?? null, 'attachment_path' => $path, 'status' => 'RECEIVED', 'source' => 'MANUAL']);
            $item->update(['status' => 'RESULT_PENDING']);
            $item->order->update(['status' => 'RESULT_PENDING']);
            AuditLog::record('result.entered', $item->order->visit, [], ['item_id' => $item->id]);
        });

        return back()->with('success', 'Đã nhập kết quả chờ xác minh.');
    }

    public function verify(Request $request, DiagnosticResult $result)
    {
        $data = $request->validate(['status' => ['required', 'in:VERIFIED,REJECTED']]);
        ClinicAccess::require('LAB_RESULT_VERIFY', $result->item->order->visit->branch_id);
        DB::transaction(function () use ($result, $data) {
            $item = ServiceOrderItem::lockForUpdate()->findOrFail($result->service_order_item_id);
            $r = DiagnosticResult::lockForUpdate()->findOrFail($result->id);
            abort_unless($r->status === 'RECEIVED' && $item->status !== 'CANCELLED', 409, 'Kết quả đã được xử lý.');
            abort_if($data['status'] === 'VERIFIED' && $item->results()->where('status', 'VERIFIED')->exists(), 409, 'Đã có kết quả được duyệt cho dòng chỉ định.');
            $r->update(['status' => $data['status'], 'verified_by' => auth()->id(), 'verified_at' => now()]);
            if ($data['status'] === 'VERIFIED') {
                $item->update(['status' => 'COMPLETED']);
            }
            $order = $item->order()->lockForUpdate()->first();
            if (! $order->items()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->exists()) {
                $order->update(['status' => 'COMPLETED']);
            }
            AuditLog::record('result.'.$data['status'], $order->visit, [], ['result_id' => $r->id]);
        }, 5);

        return back()->with('success', 'Đã xử lý kết quả.');
    }

    public function download(DiagnosticResult $result)
    {
        $visit = $result->item->order->visit;
        $u = auth()->user();
        $allowed = ($u->hasRole('patient') && $u->patient?->id === $visit->patient_id && $result->status === 'VERIFIED')
            || ($u->hasRole('doctor') && $u->doctor?->id === $visit->doctor_id && $u->canAccessBranch($visit->branch_id))
            || $u->hasPermission('LAB_RESULT_ENTER', $visit->branch_id) || $u->hasPermission('LAB_RESULT_VERIFY', $visit->branch_id);
        abort_unless($allowed, 403);
        abort_unless($result->attachment_path && Storage::disk('local')->exists($result->attachment_path), 404);
        AuditLog::record('result.file_downloaded', $visit, [], ['result_id' => $result->id]);

        return Storage::disk('local')->download($result->attachment_path);
    }

    public function measurement(Request $request, DeviceMeasurement $measurement)
    {
        ClinicAccess::treat($measurement->visit);
        $data = $request->validate(['status' => ['required', 'in:VERIFIED,REJECTED']]);
        abort_unless($measurement->status === 'RECEIVED', 409);
        $measurement->update(['status' => $data['status'], 'verified_by' => auth()->id()]);
        AuditLog::record('measurement.'.$data['status'], $measurement->visit, [], ['measurement_id' => $measurement->id]);

        return back()->with('success', 'Đã xác minh số đo.');
    }
}
