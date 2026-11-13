<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NotificationJob;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', ['branches' => Branch::orderBy('code')->get(), 'users' => User::with(['role', 'branches'])->whereHas('role', fn ($q) => $q->where('code', '!=', 'patient'))->paginate(15),
            'roles' => Role::whereNotIn('code', ['patient', 'doctor'])->get(), 'jobs' => NotificationJob::select('id', 'template', 'status', 'retry_count', 'error_code', 'scheduled_at')->latest()->limit(30)->get(),
            'systems' => DB::table('integration_systems')->select('id', 'code', 'type', 'branch_id', 'is_active')->get(),
            'permissions' => DB::table('user_permissions')->get()->groupBy('user_id')]);
    }

    public function branch(Request $request, ?Branch $branch = null)
    {
        $data = $request->validate(['code' => ['required', 'max:20', \Illuminate\Validation\Rule::unique('branches')->ignore($branch?->id)],
            'name' => ['required', 'max:255'], 'address' => ['nullable', 'max:255'], 'phone' => ['nullable', 'max:20'],
            'opening_hours' => ['nullable', 'max:255'], 'is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($branch, $data) {
            $roster = app(StaffRosterService::class);
            $roster->lock();
            if ($branch && ! $data['is_active']) {
                if ($roster->confirmedUpcoming()->where('branch_id', $branch->id)->exists()) {
                    throw ValidationException::withMessages(['is_active' => 'Cần mở chốt và xử lý các ca nhân viên sắp tới trước khi vô hiệu hóa cơ sở.']);
                }
                abort_if(\App\Models\Appointment::where('branch_id', $branch->id)->whereIn('status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])->exists()
                    || \App\Models\Visit::where('branch_id', $branch->id)->whereNotIn('status', ['CLOSED', 'CANCELLED', 'COMPLETED'])->exists(), 422, 'Cần xử lý lịch hẹn/lượt đang mở trước khi vô hiệu hóa chi nhánh.');
            }
            $saved = $branch ?: new Branch;
            $saved->fill($data)->save();
            AuditLog::record('branch.saved', $saved);
        });

        return back()->with('success', 'Đã lưu chi nhánh.');
    }

    public function staff(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:255'], 'email' => ['required', 'email', 'unique:users', 'max:255'],
            'password' => ['required', 'min:8'], 'role_id' => ['required', 'exists:roles,id'], 'branch_ids' => ['required', 'array', 'min:1'], 'branch_ids.*' => ['exists:branches,id']]);
        abort_unless(Role::findOrFail($data['role_id'])->code !== 'patient' && Role::findOrFail($data['role_id'])->code !== 'doctor', 422, 'Tạo bác sĩ tại trang danh mục.');
        DB::transaction(function () use ($data) {
            $u = User::create(collect($data)->only(['name', 'email', 'password', 'role_id'])->all());
            $u->branches()->sync($data['branch_ids']);
            AuditLog::record('staff.created', $u);
        });

        return back()->with('success', 'Đã tạo nhân viên.');
    }

    public function updateStaff(Request $request, User $user)
    {
        abort_if($user->hasRole('patient'), 422);
        $data = $request->validate(['status' => ['required', 'in:ACTIVE,LOCKED,DISABLED'], 'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['exists:branches,id'], 'permission_codes' => ['nullable', 'array'], 'permission_codes.*' => ['in:'.implode(',', config('clinic.extra_permissions'))]]);
        abort_if($user->id === auth()->id() && $data['status'] !== 'ACTIVE', 422, 'Không tự khóa tài khoản đang sử dụng.');
        DB::transaction(function () use ($user, $data) {
            $roster = app(StaffRosterService::class);
            $roster->lock();
            $removedBranches = $user->branches()->whereNotIn('branches.id', $data['branch_ids'])->pluck('branches.id');
            if ($roster->confirmedUpcoming()->where('user_id', $user->id)->whereIn('branch_id', $removedBranches)->exists()) {
                throw ValidationException::withMessages(['branch_ids' => 'Nhân viên còn ca đã chốt sắp tới tại cơ sở bị bỏ phân công. Hãy mở chốt và điều chỉnh lịch làm trước.']);
            }
            if ($user->doctor) {
                $removed = $user->branches()->whereNotIn('branches.id', $data['branch_ids'])->pluck('branches.id');
                abort_if($user->doctor->appointments()->whereIn('branch_id', $removed)->whereIn('status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])->exists(), 422, 'Hãy xử lý lịch hẹn trước khi bỏ phân công bác sĩ.');
            }
            $permissions = $data['permission_codes'] ?? [];
            abort_if(! $user->hasRole('doctor') && array_intersect($permissions, ['LAB_RESULT_VERIFY', 'MEDICINE_DISPENSE']), 422, 'Quyền chuyên môn demo chỉ cấp cho tài khoản bác sĩ.');
            $user->update(['status' => $data['status'], 'is_active' => $data['status'] === 'ACTIVE']);
            $user->branches()->sync($data['branch_ids']);
            DB::table('user_permissions')->where('user_id', $user->id)->delete();
            foreach ($data['branch_ids'] as $branch) {
                foreach ($permissions as $permission) {
                    DB::table('user_permissions')->insert(['user_id' => $user->id, 'branch_id' => $branch, 'permission_code' => $permission, 'granted_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            if ($data['status'] !== 'ACTIVE') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            AuditLog::record('staff.access_updated', $user, [], ['status' => $data['status'], 'branch_ids' => $data['branch_ids'], 'permissions' => $permissions]);
        });

        return back()->with('success', 'Đã cập nhật trạng thái, phân công và quyền.');
    }

    public function integration(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'max:40', 'unique:integration_systems'], 'type' => ['required', 'in:LIS,DEVICE'], 'branch_id' => ['required', 'exists:branches,id'], 'token' => ['required', 'string', 'min:32', 'max:255']]);
        DB::table('integration_systems')->insert(['code' => $data['code'], 'type' => $data['type'], 'branch_id' => $data['branch_id'], 'token_hash' => hash('sha256', $data['token']), 'created_at' => now(), 'updated_at' => now()]);
        AuditLog::record('integration.registered', Branch::findOrFail($data['branch_id']), [], ['code' => $data['code']]);

        return back()->with('success', 'Đã đăng ký nguồn mock; token chỉ lưu dạng băm.');
    }

    public function disableIntegration(int $id)
    {
        $system = DB::table('integration_systems')->find($id);
        abort_unless($system, 404);
        DB::table('integration_systems')->where('id', $id)->update(['is_active' => false]);
        AuditLog::record('integration.disabled', Branch::findOrFail($system->branch_id), [], ['code' => $system->code]);

        return back()->with('success', 'Đã tắt nguồn tích hợp.');
    }
}
