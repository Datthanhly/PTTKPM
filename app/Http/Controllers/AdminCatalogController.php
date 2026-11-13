<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Role;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCatalogController extends Controller
{
    public function index()
    {
        $specialties = Specialty::withCount('doctors')->orderBy('name')->get();
        $doctors = Doctor::with(['user', 'specialty'])->orderBy('doctor_code')->get();
        $services = Service::orderBy('name')->get();
        $schedules = DoctorSchedule::with('doctor.user')->whereDate('work_date', '>=', today())->orderBy('work_date')->limit(30)->get();
        $branches = \App\Models\Branch::where('is_active', true)->get();

        return view('admin.catalogs', compact('specialties', 'doctors', 'services', 'schedules', 'branches'));
    }

    public function specialty(Request $request)
    {
        Specialty::create($request->validate(['code' => ['required', 'max:20', 'unique:specialties'], 'name' => ['required', 'max:255', 'unique:specialties'], 'description' => ['nullable', 'string']]));

        return back()->with('success', 'Đã thêm chuyên khoa.');
    }

    public function service(Request $request)
    {
        Service::create($request->validate(['code' => ['required', 'max:20', 'unique:services'], 'name' => ['required', 'max:255', 'unique:services'], 'price' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'], 'description' => ['nullable', 'string']]));

        return back()->with('success', 'Đã thêm dịch vụ.');
    }

    public function doctor(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'], 'email' => ['required', 'email', 'unique:users'], 'phone' => ['nullable', 'max:20', 'unique:users'],
            'password' => ['required', 'min:8'], 'specialty_id' => ['required', 'exists:specialties,id'],
            'doctor_code' => ['required', 'max:20', 'unique:doctors'], 'license_number' => ['required', 'max:40', 'unique:doctors'],
            'qualification' => ['nullable', 'max:255'], 'room' => ['nullable', 'max:30'],
            'branch_ids' => ['required', 'array', 'min:1'], 'branch_ids.*' => ['exists:branches,id'],
            'specialty_ids' => ['nullable', 'array'], 'specialty_ids.*' => ['exists:specialties,id'],
        ]);
        DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'role_id' => Role::where('code', 'doctor')->value('id')]);
            $doctor = $user->doctor()->create($data);
            $user->branches()->sync($data['branch_ids']);
            $doctor->specialties()->sync(array_unique([$data['specialty_id'], ...($data['specialty_ids'] ?? [])]));
            \App\Models\AuditLog::record('doctor.created', $doctor);
        });

        return back()->with('success', 'Đã thêm bác sĩ.');
    }

    public function schedule(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'], 'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'doctor_id' => ['required', 'exists:doctors,id'], 'work_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'in:15,20,30,45,60'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:work_date', 'before_or_equal:'.today()->addDays(90)->format('Y-m-d')],
        ]);
        $until = $data['repeat_until'] ?? $data['work_date'];
        unset($data['repeat_until']);
        DB::transaction(function () use ($data, $until) {
            for ($date = \Carbon\Carbon::parse($data['work_date']); $date->lte(\Carbon\Carbon::parse($until)); $date->addWeek()) {
                app(ScheduleService::class)->create([...$data, 'work_date' => $date->format('Y-m-d')]);
            }
        });

        return back()->with('success', 'Đã thêm ca làm việc.');
    }

    public function timeOff(Request $request, ScheduleService $schedules)
    {
        $data = $request->validate(['doctor_id' => ['required', 'exists:doctors,id'], 'starts_at' => ['required', 'date', 'after_or_equal:now'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'reason' => ['required', 'string', 'max:255'], 'cancel_affected' => ['nullable', 'boolean']]);
        $schedules->timeOff($data);

        return back()->with('success', 'Đã lưu lịch nghỉ và xử lý lịch bị ảnh hưởng.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $models = ['specialties' => Specialty::class, 'services' => Service::class, 'doctors' => Doctor::class];
        abort_unless(isset($models[$type]), 404);
        $item = $models[$type]::findOrFail($id);
        $rules = ['is_active' => ['required', 'boolean']];
        if ($type !== 'doctors') {
            $rules += ['name' => ['required', 'max:255', \Illuminate\Validation\Rule::unique($type)->ignore($id)], 'description' => ['nullable', 'string', 'max:3000']];
        }
        if ($type === 'services') {
            $rules['price'] = ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'];
        }
        if ($type === 'doctors') {
            $rules += ['qualification' => ['nullable', 'max:255'], 'room' => ['nullable', 'max:30'], 'bio' => ['nullable', 'string', 'max:3000'], 'specialty_ids' => ['required', 'array', 'min:1'], 'specialty_ids.*' => ['exists:specialties,id']];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($item, $data, $type) {
            if ($type === 'doctors') {
                $data['specialty_id'] = (int) $data['specialty_ids'][0];
            }
            $item->update(collect($data)->except('specialty_ids')->all());
            if ($type === 'doctors') {
                $item->specialties()->sync($data['specialty_ids']);
            }
            \App\Models\AuditLog::record('catalog.updated', $item, [], ['type' => $type, 'is_active' => $item->is_active]);
        });

        return back()->with('success', 'Đã cập nhật danh mục, giữ nguyên lịch sử.');
    }
}
