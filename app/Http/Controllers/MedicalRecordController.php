<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\ClinicAccess;

class MedicalRecordController extends Controller
{
    public function edit(Appointment $appointment)
    {
        abort_unless($appointment->visit, 404);
        ClinicAccess::treat($appointment->visit);

        return redirect()->route('visits.show', $appointment->visit);
    }

    public function update(Appointment $appointment)
    {
        abort_unless($appointment->visit, 404);
        ClinicAccess::treat($appointment->visit);
        abort(409, 'Hãy tải lại trang lượt khám để lưu hồ sơ có kiểm tra phiên bản.');
    }
}
