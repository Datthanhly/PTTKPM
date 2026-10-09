<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Role;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
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
        return view('admin.catalogs', compact('specialties', 'doctors', 'services', 'schedules'));
    }

    public function specialty(Request $request)
    {
        Specialty::create($request->validate(['code' => ['required', 'max:20', 'unique:specialties'], 'name' => ['required', 'max:255', 'unique:specialties'], 'description' => ['nullable', 'string']]));
        return back()->with('success', 'Đã thêm chuyên khoa.');
    }

    public function service(Request $request)
    {
        Service::create($request->validate(['code' => ['required', 'max:20', 'unique:services'], 'name' => ['required', 'max:255', 'unique:services'], 'price' => ['required', 'numeric', 'min:0'], 'description' => ['nullable', 'string']]));
        return back()->with('success', 'Đã thêm dịch vụ.');
    }

    public function doctor(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'], 'email' => ['required', 'email', 'unique:users'], 'phone' => ['nullable', 'max:20', 'unique:users'],
            'password' => ['required', 'min:8'], 'specialty_id' => ['required', 'exists:specialties,id'],
            'doctor_code' => ['required', 'max:20', 'unique:doctors'], 'license_number' => ['required', 'max:40', 'unique:doctors'],
            'qualification' => ['nullable', 'max:255'], 'room' => ['nullable', 'max:30'],
        ]);
        DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'role_id' => Role::where('code', 'doctor')->value('id')]);
            $user->doctor()->create($data);
        });
        return back()->with('success', 'Đã thêm bác sĩ.');
    }

    public function schedule(Request $request)
    {
        DoctorSchedule::create($request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'], 'work_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'in:15,20,30,45,60'],
        ]));
        return back()->with('success', 'Đã thêm ca làm việc.');
    }
}
