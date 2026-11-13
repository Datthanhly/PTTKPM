<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function range(): array
    {
        request()->merge(['from' => request('from') ?: today()->startOfMonth()->format('Y-m-d'), 'to' => request('to') ?: today()->format('Y-m-d')]);
        request()->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'status' => ['nullable', 'in:ISSUED,VOID']]);

        return [request('from', today()->startOfMonth()->format('Y-m-d')), request('to', today()->format('Y-m-d'))];
    }

    public function payments(string $from, string $to)
    {
        return Payment::where('status', 'SUCCEEDED')->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)
            ->whereHas('invoice', fn ($q) => ClinicAccess::scope($q)->when(request()->filled('status'), fn ($i) => $i->where('status', request('status'))))->with('invoice.branch');
    }

    public function refunds(string $from, string $to)
    {
        $ids = ClinicAccess::scope(Invoice::query())->when(request()->filled('status'), fn ($q) => $q->where('status', request('status')))->select('id');

        return DB::table('refunds as r')->join('payments as p', 'p.id', '=', 'r.payment_id')->whereIn('p.invoice_id', $ids)
            ->whereDate('r.created_at', '>=', $from)->whereDate('r.created_at', '<=', $to)->select('r.*', 'p.invoice_id');
    }

    public function summary(string $from, string $to): array
    {
        $payments = $this->payments($from, $to)->get();
        $refunds = $this->refunds($from, $to)->get();
        $invoices = ClinicAccess::scope(Invoice::where('status', 'ISSUED'))->get();
        $daily = [];
        foreach ($payments as $p) {
            $day = $p->paid_at->format('Y-m-d');
            $daily[$day] = ($daily[$day] ?? 0) + Money::cents($p->amount);
        }
        foreach ($refunds as $r) {
            $day = substr($r->created_at, 0, 10);
            $daily[$day] = ($daily[$day] ?? 0) - Money::cents($r->amount);
        }
        ksort($daily);
        $visits = ClinicAccess::scope(Visit::whereIn('status', ['COMPLETED', 'CLOSED']))->whereDate('completed_at', '>=', $from)->whereDate('completed_at', '<=', $to);

        return ['today' => ClinicAccess::scope(Appointment::query())->whereDate('appointment_date', today())->count(),
            'completed' => $visits->count(), 'patients' => (auth()->user()->hasRole('admin') ? Patient::query() : ClinicAccess::patients())->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->count(),
            'revenue_cents' => $payments->sum(fn ($p) => Money::cents($p->amount)) - $refunds->sum(fn ($r) => Money::cents($r->amount)),
            'unpaid_cents' => $invoices->sum(fn ($i) => $i->outstanding_cents), 'daily' => $daily,
            'specialties' => (clone $visits)->with('doctor.specialty')->get()->groupBy(fn ($v) => $v->doctor->specialty->name)->map->count()->all()];
    }
}
