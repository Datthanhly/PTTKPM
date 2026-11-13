<?php

use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AdminRosterController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeScheduleController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftTransferController;
use App\Http\Controllers\StaffAppointmentController;
use App\Http\Controllers\VisitController;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'serviceGroups' => config('clinic_services.groups'),
        'specialties' => Specialty::where('is_active', true)->withCount('doctors')->get(),
        'doctors' => Doctor::with(['user', 'specialty'])->where('is_active', true)->limit(6)->get(),
    ]);
})->name('home');
Route::get('/bac-si', [\App\Http\Controllers\DoctorController::class, 'index'])->name('doctors.index');
Route::get('/bac-si/{doctor}', [\App\Http\Controllers\DoctorController::class, 'show'])->name('doctors.show');
Route::get('/dang-nhap-phien-rieng', [AuthController::class, 'separateSession'])->name('login.separate');

Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->middleware('throttle:5,1,auth-login:')->name('login.submit');
    Route::get('/dang-ky', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->middleware('throttle:5,1,auth-register:')->name('register.submit');
    Route::get('/quen-mat-khau', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/quen-mat-khau', [AuthController::class, 'forgot'])->middleware('throttle:password-recovery')->name('password.email');
    Route::get('/dat-lai-mat-khau/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [AuthController::class, 'reset'])->middleware('throttle:5,1,auth-password-reset:')->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout');
    Route::get('/bang-dieu-khien', DashboardController::class)->name('dashboard');
    Route::get('/doi-mat-khau', [AuthController::class, 'passwordForm'])->name('password.change');
    Route::put('/doi-mat-khau', [AuthController::class, 'changePassword'])->name('password.change.submit');
    Route::get('/lich-kham/dat-lich', [AppointmentController::class, 'create'])->name('appointments.create');

    Route::middleware('role:patient,receptionist')->group(function () {
        Route::post('/lich-kham', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/lich-kham/kha-dung', [AppointmentController::class, 'availability'])->name('appointments.availability');
        Route::get('/lich-kham/{appointment}/doi', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::patch('/lich-kham/{appointment}/doi', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::patch('/lich-kham/{appointment}/huy', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    });

    Route::middleware('role:patient')->group(function () {
        Route::get('/lich-kham', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/ho-so', [PatientController::class, 'profile'])->name('patients.profile');
        Route::put('/ho-so', [PatientController::class, 'saveProfile'])->name('patients.profile.save');
        Route::get('/lich-su-kham', [PatientController::class, 'history'])->name('patients.history');
        Route::get('/tai-kham', [FollowUpController::class, 'index'])->name('follow-ups.index');
        Route::post('/tai-kham/{plan}', [FollowUpController::class, 'respond'])->name('follow-ups.respond');
    });

    Route::middleware('role:doctor,receptionist,cashier')->group(function () {
        Route::get('/luong-cua-toi', [PayrollController::class, 'mine'])->name('payroll.mine');
        Route::get('/luong-cua-toi/{payroll}', [PayrollController::class, 'mineShow'])->name('payroll.mine.show');
    });

    Route::middleware('role:receptionist')->group(function () {
        Route::get('/benh-nhan', [PatientController::class, 'index'])->name('patients.index');
        Route::post('/benh-nhan', [PatientController::class, 'store'])->name('patients.store');
        Route::put('/benh-nhan/{patient}', [PatientController::class, 'update'])->name('patients.update');
        Route::post('/tiep-nhan/vang-lai', [PatientController::class, 'walkIn'])->name('visits.walk-in');
    });
    Route::get('/luot-kham', [VisitController::class, 'index'])->name('visits.index');
    Route::get('/luot-kham/{visit}', [VisitController::class, 'show'])->name('visits.show');
    Route::patch('/luot-kham/{visit}/trang-thai', [VisitController::class, 'transition'])->name('visits.transition');
    Route::put('/luot-kham/{visit}/ho-so', [VisitController::class, 'record'])->name('visits.record');
    Route::post('/luot-kham/{visit}/don-thuoc', [VisitController::class, 'prescription'])->name('prescriptions.store');
    Route::post('/don-thuoc/{prescription}/ky', [VisitController::class, 'sign'])->name('prescriptions.sign');
    Route::post('/luot-kham/{visit}/dich-vu', [VisitController::class, 'order'])->name('service-orders.store');
    Route::post('/luot-kham/{visit}/tai-kham', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::get('/chan-doan', [DiagnosticController::class, 'index'])->name('diagnostics.index');
    Route::post('/chi-dinh/{item}/ket-qua', [DiagnosticController::class, 'enter'])->name('diagnostics.enter');
    Route::post('/ket-qua/{result}/duyet', [DiagnosticController::class, 'verify'])->name('diagnostics.verify');
    Route::get('/ket-qua/{result}/tep', [DiagnosticController::class, 'download'])->name('diagnostics.download');
    Route::post('/so-do/{measurement}/duyet', [DiagnosticController::class, 'measurement'])->name('measurements.verify');
    Route::get('/kho', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/kho/thuoc', [InventoryController::class, 'medicine'])->name('medicines.store');
    Route::put('/kho/thuoc/{medicine}', [InventoryController::class, 'medicine'])->name('medicines.update');
    Route::post('/kho/nhap', [InventoryController::class, 'receive'])->name('inventory.receive');
    Route::post('/kho/lo/{batch}/dieu-chinh', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::post('/kho/don/{prescription}/cap-phat', [InventoryController::class, 'dispense'])->name('inventory.dispense');
    Route::get('/hoa-don/{invoice}/pdf', [DocumentController::class, 'invoice'])->name('documents.invoice');
    Route::get('/don-thuoc/{prescription}/pdf', [DocumentController::class, 'prescription'])->name('documents.prescription');
    Route::get('/bao-cao', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/bao-cao/{type}/excel', [ReportController::class, 'export'])->name('reports.export');

    Route::middleware('role:receptionist,doctor,cashier,admin')->group(function () {
        Route::get('/nhan-vien/lich-hen', [StaffAppointmentController::class, 'index'])->name('staff.appointments');
        Route::get('/nhan-vien/ca-lam/{schedule}', [StaffAppointmentController::class, 'shift'])->name('staff.shifts.show');
        Route::patch('/nhan-vien/lich-hen/{appointment}/trang-thai', [StaffAppointmentController::class, 'transition'])->name('staff.appointments.transition');
    });

    Route::middleware('role:doctor')->group(function () {
        Route::post('/nhan-vien/ca-lam/{schedule}/doi-bac-si', [ShiftTransferController::class, 'store'])->name('doctor-shifts.transfer');
        Route::patch('/nhan-vien/doi-ca/{transfer}', [ShiftTransferController::class, 'respond'])->name('doctor-shifts.respond');
        Route::get('/nhan-vien/dang-ky-lich-lam', [DoctorScheduleController::class, 'create'])->name('doctor-schedules.create');
        Route::post('/nhan-vien/dang-ky-lich-lam', [DoctorScheduleController::class, 'store'])->name('doctor-schedules.store');
        Route::get('/nhan-vien/kham/{appointment}', [MedicalRecordController::class, 'edit'])->name('medical-records.edit');
        Route::put('/nhan-vien/kham/{appointment}', [MedicalRecordController::class, 'update'])->name('medical-records.update');
    });

    Route::middleware('role:receptionist,cashier')->group(function () {
        Route::get('/nhan-vien/lich-lam-viec', [EmployeeScheduleController::class, 'index'])->name('employee-schedules.index');
        Route::post('/nhan-vien/lich-lam-viec', [EmployeeScheduleController::class, 'store'])->name('employee-schedules.store');
        Route::patch('/nhan-vien/dang-ky-ca/{registration}/rut', [EmployeeScheduleController::class, 'withdraw'])->name('employee-schedules.withdraw');
    });

    Route::middleware('role:cashier,admin')->group(function () {
        Route::get('/thu-ngan/luot/{visit}', [BillingController::class, 'show'])->name('billing.show');
        Route::post('/thu-ngan/luot/{visit}/hoa-don', [BillingController::class, 'issue'])->name('billing.issue');
        Route::post('/thu-ngan/giao-dich/{payment}/hoan-tien', [BillingController::class, 'refund'])->name('billing.refund');
        Route::post('/thu-ngan/hoa-don/{invoice}/huy', [BillingController::class, 'void'])->name('billing.void');
        Route::get('/thu-ngan/lich-hen/{appointment}', [BillingController::class, 'create'])->name('billing.create');
        Route::post('/thu-ngan/lich-hen/{appointment}/hoa-don', [BillingController::class, 'store'])->name('billing.store');
        Route::post('/thu-ngan/hoa-don/{invoice}/thanh-toan', [BillingController::class, 'pay'])->name('billing.pay');
    });

    Route::middleware('role:admin')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/bang-luong', [PayrollController::class, 'index'])->name('payroll.index');
        Route::put('/don-gia-luong/{employee}', [PayrollController::class, 'profile'])->name('payroll.profile');
        Route::post('/bang-luong/nhan-vien/{employee}', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::get('/bang-luong/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::put('/bang-luong/{payroll}', [PayrollController::class, 'update'])->name('payroll.update');
        Route::post('/bang-luong/{payroll}/tinh-lai', [PayrollController::class, 'recalculate'])->name('payroll.recalculate');
        Route::patch('/bang-luong/{payroll}/thanh-toan', [PayrollController::class, 'payment'])->name('payroll.payment');
        Route::get('/lich-nhan-vien', [AdminRosterController::class, 'index'])->name('roster.index');
        Route::post('/lich-nhan-vien/tu-dong', [AdminRosterController::class, 'autoArrange'])->name('roster.auto');
        Route::post('/lich-nhan-vien/chot', [AdminRosterController::class, 'publish'])->name('roster.publish');
        Route::post('/lich-nhan-vien/{registration}/xep', [AdminRosterController::class, 'arrange'])->name('roster.arrange');
        Route::patch('/lich-nhan-vien/{registration}/tu-choi', [AdminRosterController::class, 'reject'])->name('roster.reject');
        Route::patch('/ca-nhan-vien/{shift}/mo-chot', [AdminRosterController::class, 'reopen'])->name('roster.reopen');
        Route::get('/danh-muc', [AdminCatalogController::class, 'index'])->name('catalogs');
        Route::post('/chuyen-khoa', [AdminCatalogController::class, 'specialty'])->name('specialties.store');
        Route::post('/dich-vu', [AdminCatalogController::class, 'service'])->name('services.store');
        Route::post('/bac-si', [AdminCatalogController::class, 'doctor'])->name('doctors.store');
        Route::post('/lich-lam-viec', [AdminCatalogController::class, 'schedule'])->name('schedules.store');
        Route::post('/lich-nghi', [AdminCatalogController::class, 'timeOff'])->name('time-off.store');
        Route::put('/danh-muc/{type}/{id}', [AdminCatalogController::class, 'update'])->name('catalogs.update');
        Route::get('/cau-hinh', [AdminSettingsController::class, 'index'])->name('settings');
        Route::post('/chi-nhanh', [AdminSettingsController::class, 'branch'])->name('branches.store');
        Route::put('/chi-nhanh/{branch}', [AdminSettingsController::class, 'branch'])->name('branches.update');
        Route::post('/nhan-vien', [AdminSettingsController::class, 'staff'])->name('staff.store');
        Route::put('/nhan-vien/{user}', [AdminSettingsController::class, 'updateStaff'])->name('staff.update');
        Route::post('/tich-hop', [AdminSettingsController::class, 'integration'])->name('integrations.store');
        Route::delete('/tich-hop/{id}', [AdminSettingsController::class, 'disableIntegration'])->name('integrations.disable');
    });
});
