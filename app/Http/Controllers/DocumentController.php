<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Prescription;
use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentController extends Controller
{
    public function invoice(Invoice $invoice)
    {
        $u = auth()->user();
        abort_unless(($u->hasRole('patient') && $u->patient?->id === $invoice->visit?->patient_id) || $u->hasPermission('INVOICE_ISSUE', $invoice->branch_id), 403);
        abort_unless($invoice->status === 'ISSUED', 422);
        $invoice->load(['items', 'payments', 'branch', 'visit.patient']);
        AuditLog::record('invoice.pdf_downloaded', $invoice);

        return $this->pdf(view('documents.invoice', compact('invoice'))->render(), $invoice->invoice_code.'.pdf');
    }

    public function prescription(Prescription $prescription)
    {
        $v = $prescription->visit;
        $u = auth()->user();
        abort_unless(($u->hasRole('patient') && $u->patient?->id === $v->patient_id) || ($u->hasRole('doctor') && $u->doctor?->id === $v->doctor_id && $u->canAccessBranch($v->branch_id)) || $u->hasPermission('MEDICINE_DISPENSE', $v->branch_id), 403);
        abort_unless($prescription->status === 'SIGNED', 422, 'Chỉ tải đơn đã ký.');
        $prescription->load(['items.medicine', 'visit.branch', 'visit.patient', 'visit.doctor.user']);
        AuditLog::record('prescription.pdf_downloaded', $v, [], ['prescription_id' => $prescription->id]);

        return $this->pdf(view('documents.prescription', compact('prescription'))->render(), 'don-thuoc-'.$prescription->id.'.pdf');
    }

    private function pdf(string $html, string $filename)
    {
        $options = new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false,
            'chroot' => [resource_path('views/documents'), base_path('vendor/dompdf/dompdf/lib/fonts')], 'fontCache' => storage_path('fonts')]);
        if (! is_dir(storage_path('fonts'))) {
            mkdir(storage_path('fonts'), 0755, true);
        }
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output())->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
