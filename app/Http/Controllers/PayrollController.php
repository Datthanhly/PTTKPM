<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PayrollEntry;
use App\Models\SalaryProfile;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function mine(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $entries = PayrollEntry::where('user_id', $request->user()->id)
            ->when($request->filled('month'), fn ($q) => $q->whereDate('pay_month', $request->input('month').'-01'))
            ->orderByDesc('pay_month')->paginate(12)->withQueryString();
        $profile = $request->user()->salaryProfile;

        return response()->view('payroll.mine', compact('entries', 'profile'))->header('Cache-Control', 'no-store, private');
    }

    public function mineShow(PayrollEntry $payroll)
    {
        abort_unless((int) $payroll->user_id === (int) auth()->id(), 403);

        return $this->detail($payroll, false);
    }

    public function index(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:'.implode(',', SalaryProfile::ROLES)], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', 'in:PAID,UNPAID']]);
        $month = $request->input('month') ?: today()->format('Y-m');
        $employees = User::whereHas('role', fn ($q) => $q->whereIn('code', SalaryProfile::ROLES))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('role', fn ($role) => $role->where('code', $request->input('role'))))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('branches', fn ($branch) => $branch->where('branches.id', $request->integer('branch_id'))))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')->orWhere('email', 'like', '%'.$request->input('q').'%')));
        $entries = PayrollEntry::whereDate('pay_month', $month.'-01')
            ->when($request->filled('role'), fn ($q) => $q->whereHas('user.role', fn ($role) => $role->where('code', $request->input('role'))))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('user.branches', fn ($branch) => $branch->where('branches.id', $request->integer('branch_id'))))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($search) => $search->where('employee_name', 'like', '%'.$request->input('q').'%')
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$request->input('q').'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->orderBy('employee_name')->paginate(12, ['*'], 'payroll_page')->withQueryString();
        $employees = $employees->with(['role', 'branches', 'salaryProfile'])->orderBy('name')->paginate(12, ['*'], 'staff_page')->withQueryString();
        $branches = Branch::orderBy('name')->get();

        return response()->view('payroll.admin', compact('month', 'employees', 'entries', 'branches'))->header('Cache-Control', 'no-store, private');
    }

    public function show(PayrollEntry $payroll)
    {
        return $this->detail($payroll, true);
    }

    private function detail(PayrollEntry $payroll, bool $adminView)
    {
        $payroll->load('paidBy');

        return response()->view('payroll.show', compact('payroll', 'adminView'))->header('Cache-Control', 'no-store, private');
    }

    public function profile(Request $request, User $employee, PayrollService $service)
    {
        $data = $request->validate(['pay_basis' => ['required', 'in:HOUR,SHIFT'], 'unit_rate' => ['required', 'integer', 'min:1', 'max:100000000'], 'version' => ['required', 'integer', 'min:0']]);
        $service->saveProfile($employee, $data);

        return back()->with('success', 'Đã lưu đơn giá riêng. Các bảng lương đã tạo giữ nguyên đơn giá tại thời điểm tính.');
    }

    public function generate(Request $request, User $employee, PayrollService $service)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:'.today()->format('Y-m')], 'profile_version' => ['required', 'integer', 'min:1']]);
        $entry = $service->generate($employee, $data);

        return redirect()->route('admin.payroll.show', $entry)->with('success', 'Đã tính lương từ các ca đã kết thúc. Hãy đối chiếu giờ/ca thực tế trước khi đánh dấu đã thanh toán.');
    }

    public function update(Request $request, PayrollEntry $payroll, PayrollService $service)
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'], 'unit_rate' => ['required', 'integer', 'min:1', 'max:100000000'],
            'hours' => ['required', 'integer', 'min:0', 'max:744'], 'minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'worked_shifts' => ['required', 'integer', 'min:0', 'max:1000'],
            'allowance' => ['required', 'integer', 'min:0', 'max:1000000000'], 'bonus' => ['required', 'integer', 'min:0', 'max:1000000000'], 'deduction' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:1000'], 'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->update($payroll, $data);

        return redirect()->route('admin.payroll.show', $payroll)->with('success', 'Đã cập nhật số công và tính lại số tiền.');
    }

    public function recalculate(Request $request, PayrollEntry $payroll, PayrollService $service)
    {
        $service->recalculate($payroll, $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000']]));

        return redirect()->route('admin.payroll.show', $payroll)->with('success', 'Đã lấy lại giờ/ca từ lịch mới nhất; giữ đơn giá, phụ cấp, thưởng và khấu trừ hiện có.');
    }

    public function payment(Request $request, PayrollEntry $payroll, PayrollService $service)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'status' => ['required', 'in:PAID,UNPAID'],
            'reason' => ['required', 'string', 'max:1000'], 'reference' => ['nullable', 'string', 'max:200']]);
        $service->payment($payroll, $data);

        return redirect()->route('admin.payroll.show', $payroll)->with('success', $data['status'] === 'PAID' ? 'Đã ghi nhận trạng thái đã thanh toán lương.' : 'Đã chuyển bảng lương về chưa thanh toán.');
    }
}
