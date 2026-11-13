<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\ClinicAccess;
use App\Services\Money;
use App\Services\ReportService;
use App\Services\XlsxExporter;

class ReportController extends Controller
{
    public function index(ReportService $reports)
    {
        abort_unless(auth()->user()->hasPermission('REPORT_VIEW'), 403);
        [$from, $to] = $reports->range();
        $query = ClinicAccess::scope(Invoice::with(['branch', 'visit.patient'])->latest('issued_at'));
        $query->whereDate('issued_at', '>=', $from)->whereDate('issued_at', '<=', $to);
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        return view('reports.index', ['stats' => $reports->summary($from, $to), 'invoices' => $query->paginate(20)->withQueryString(), 'branches' => ClinicAccess::branches(), 'from' => $from, 'to' => $to]);
    }

    public function export(string $type, ReportService $reports, XlsxExporter $xlsx)
    {
        abort_unless(auth()->user()->hasPermission('REPORT_EXPORT'), 403);
        abort_unless(in_array($type, ['revenue', 'appointments', 'visits', 'patients', 'inventory']), 404);
        if (auth()->user()->hasRole('cashier')) {
            abort_unless($type === 'revenue', 403);
        }
        [$from, $to] = $reports->range();
        $rows = [['Báo cáo '.$type, $from, $to], ['Người xuất', auth()->user()->name, now()->format('d/m/Y H:i')], ['Chi nhánh', request('branch_id', 'Trong phạm vi được cấp')]];
        if ($type === 'revenue') {
            $rows[] = ['Ngày', 'Hóa đơn', 'Chi nhánh', 'Loại', 'Tiền thu/hoàn'];
            foreach ($reports->payments($from, $to)->limit(5000)->get() as $p) {
                $rows[] = [$p->paid_at->format('Y-m-d H:i'), $p->invoice->invoice_code, $p->invoice->branch->name, 'THU', Money::cents($p->amount) / 100];
            }
            foreach ($reports->refunds($from, $to)->limit(5000)->get() as $r) {
                $i = Invoice::with('branch')->findOrFail($r->invoice_id);
                $rows[] = [$r->created_at, $i->invoice_code, $i->branch->name, 'HOAN', -Money::cents($r->amount) / 100];
            }
        } elseif ($type === 'appointments') {
            $rows[] = ['Mã lịch', 'Ngày', 'Giờ', 'Chi nhánh', 'Bác sĩ', 'Trạng thái'];
            foreach (ClinicAccess::scope(Appointment::with(['branch', 'doctor.user']))->whereBetween('appointment_date', [$from, $to])->limit(5000)->get() as $a) {
                $rows[] = [$a->appointment_code, $a->appointment_date->format('Y-m-d'), $a->start_time, $a->branch->name, $a->doctor->user->name, $a->status];
            }
        } elseif ($type === 'visits') {
            $rows[] = ['Mã lượt', 'Tiếp nhận', 'Chi nhánh', 'Trạng thái'];
            foreach (ClinicAccess::scope(Visit::with('branch'))->whereDate('checked_in_at', '>=', $from)->whereDate('checked_in_at', '<=', $to)->limit(5000)->get() as $v) {
                $rows[] = [$v->visit_code, $v->checked_in_at->format('Y-m-d H:i'), $v->branch->name, $v->status];
            }
        } elseif ($type === 'patients') {
            $rows[] = ['Mã bệnh nhân', 'Họ tên', 'Ngày đăng ký', 'Trạng thái'];
            $q = Patient::query();
            if (request()->filled('branch_id')) {
                $q->where('registered_branch_id', request()->integer('branch_id'));
            }
            foreach ($q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->limit(5000)->get() as $p) {
                $rows[] = [$p->patient_code, $p->full_name, $p->created_at->format('Y-m-d'), $p->is_active ? 'ACTIVE' : 'INACTIVE'];
            }
        } else {
            $rows[] = ['Chi nhánh', 'Mã thuốc', 'Tên thuốc', 'Lô', 'Hạn dùng', 'Tồn'];
            $q = ClinicAccess::scope(MedicineBatch::with(['branch', 'medicine']));
            if (request()->boolean('low')) {
                $q->where('available_qty', '<=', 10);
            }
            if (request()->boolean('expiring')) {
                $q->whereDate('expiry_date', '<=', today()->addDays(30));
            }
            foreach ($q->limit(5000)->get() as $b) {
                $rows[] = [$b->branch->name, $b->medicine->code, $b->medicine->name, $b->lot_number, $b->expiry_date->format('Y-m-d'), (int) $b->available_qty];
            }
        }
        AuditLog::record('report.exported', auth()->user(), [], ['type' => $type, 'from' => $from, 'to' => $to, 'branch_id' => request('branch_id')]);

        return response()->download($xlsx->write($rows), 'bao-cao-'.$type.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
