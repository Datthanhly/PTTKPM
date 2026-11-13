<?php

namespace App\Http\Controllers;

use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DoctorScheduleController extends Controller
{
    public function create()
    {
        $doctor = auth()->user()->doctor;
        abort_unless($doctor?->is_active, 403, 'Hồ sơ bác sĩ chưa hoạt động.');

        return redirect()->to(route('staff.appointments').'#dang-ky-ca');
    }

    public function store(Request $request, ScheduleService $schedules)
    {
        $data = $request->validateWithBag('doctorSchedule', [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'work_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(90)->format('Y-m-d')],
            'session' => ['required', 'in:morning,afternoon,both,custom'],
            'start_time' => ['exclude_unless:session,custom', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_unless:session,custom', 'required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'in:15,20,30,45,60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'repeat_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:work_date', 'before_or_equal:'.today()->addDays(90)->format('Y-m-d')],
        ]);
        try {
            $count = $schedules->registerOwn($data);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('doctorSchedule');
        }

        return redirect()->route('staff.appointments', ['date' => $data['work_date'], 'doctor_id' => $request->user()->doctor->id, 'branch_id' => $data['branch_id']])
            ->with('success', 'Đã đăng ký '.$count.' ca làm việc. Ca mới đã hiển thị trong Lịch của tôi và sẵn sàng nhận lịch hẹn.');
    }
}
