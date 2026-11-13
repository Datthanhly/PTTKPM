<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Specialty;

class DoctorController extends Controller
{
    public function index()
    {
        $query = Doctor::where('is_active', true)->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE'))->with(['user', 'specialty', 'specialties']);
        if (request()->filled('specialty_id')) {
            $query->where(fn ($q) => $q->where('specialty_id', request()->integer('specialty_id'))->orWhereHas('specialties', fn ($s) => $s->where('specialties.id', request()->integer('specialty_id'))));
        }
        if (request()->filled('branch_id')) {
            $query->whereHas('user.branches', fn ($q) => $q->where('branches.id', request()->integer('branch_id'))->where('is_active', true));
        }

        return view('doctors.index', ['doctors' => $query->paginate(12)->withQueryString(), 'branches' => Branch::where('is_active', true)->get(), 'specialties' => Specialty::where('is_active', true)->get()]);
    }

    public function show(Doctor $doctor)
    {
        abort_unless($doctor->is_active && $doctor->user->is_active && $doctor->user->status === 'ACTIVE', 404);
        $doctor->load(['specialties', 'schedules' => fn ($q) => $q->where('is_open', true)->whereDate('work_date', '>=', today())->whereHas('branch', fn ($b) => $b->where('is_active', true))->orderBy('work_date')->limit(30), 'schedules.branch']);

        return view('doctors.show', compact('doctor'));
    }
}
