<?php

namespace App\Http\Controllers;

use App\Models\DoctorSchedule;
use App\Models\ShiftTransferRequest;
use App\Services\ShiftTransferService;
use Illuminate\Http\Request;

class ShiftTransferController extends Controller
{
    public function store(Request $request, DoctorSchedule $schedule, ShiftTransferService $transfers)
    {
        abort_unless($request->user()->doctor?->id === $schedule->doctor_id, 403);
        $data = $request->validate(['receiver_doctor_id' => ['required', 'integer', 'exists:doctors,id'], 'note' => ['required', 'string', 'max:200']]);
        $transfers->request($schedule, (int) $data['receiver_doctor_id'], $data['note']);
        $message = 'Đã gửi đề nghị nhận ca. Ca và lịch hẹn chỉ chuyển sau khi bác sĩ được chọn đồng ý trong Thông báo.';

        return $request->expectsJson() ? response()->json(['message' => $message]) : redirect()->route('staff.appointments', ['date' => $schedule->work_date->format('Y-m-d')])->with('success', $message);
    }

    public function respond(Request $request, ShiftTransferRequest $transfer, ShiftTransferService $transfers)
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,reject,cancel']]);
        $transfers->respond($transfer, $data['decision']);
        $message = match ($data['decision']) {
            'accept' => 'Đã nhận ca và cập nhật các lịch hẹn sang bác sĩ mới.',
            'reject' => 'Đã từ chối đề nghị nhận ca. Ca làm và lịch hẹn được giữ nguyên.',
            default => 'Đã hủy đề nghị đổi ca. Ca làm và lịch hẹn được giữ nguyên.',
        };

        return redirect()->route('dashboard')->with('success', $message);
    }
}
