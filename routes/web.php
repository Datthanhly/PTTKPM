<?php

use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\StaffAppointmentController;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'specialties' => Specialty::where('is_active', true)->withCount('doctors')->get(),
        'doctors' => Doctor::with(['user', 'specialty'])->where('is_active', true)->limit(6)->get(),
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/dang-ky', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->name('register.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout');
    Route::get('/bang-dieu-khien', DashboardController::class)->name('dashboard');

    Route::middleware('role:patient')->group(function () {
        Route::get('/lich-kham', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/lich-kham/dat-lich', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/lich-kham', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/lich-kham/{appointment}/huy', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    });

    Route::middleware('role:receptionist,doctor,cashier,admin')->group(function () {
        Route::get('/nhan-vien/lich-hen', [StaffAppointmentController::class, 'index'])->name('staff.appointments');
        Route::patch('/nhan-vien/lich-hen/{appointment}/trang-thai', [StaffAppointmentController::class, 'transition'])->name('staff.appointments.transition');
    });

    Route::middleware('role:doctor,admin')->group(function () {
        Route::get('/nhan-vien/kham/{appointment}', [MedicalRecordController::class, 'edit'])->name('medical-records.edit');
        Route::put('/nhan-vien/kham/{appointment}', [MedicalRecordController::class, 'update'])->name('medical-records.update');
    });

    Route::middleware('role:cashier,admin')->group(function () {
        Route::get('/thu-ngan/lich-hen/{appointment}', [BillingController::class, 'create'])->name('billing.create');
        Route::post('/thu-ngan/lich-hen/{appointment}/hoa-don', [BillingController::class, 'store'])->name('billing.store');
        Route::post('/thu-ngan/hoa-don/{invoice}/thanh-toan', [BillingController::class, 'pay'])->name('billing.pay');
    });

    Route::middleware('role:admin')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/danh-muc', [AdminCatalogController::class, 'index'])->name('catalogs');
        Route::post('/chuyen-khoa', [AdminCatalogController::class, 'specialty'])->name('specialties.store');
        Route::post('/dich-vu', [AdminCatalogController::class, 'service'])->name('services.store');
        Route::post('/bac-si', [AdminCatalogController::class, 'doctor'])->name('doctors.store');
        Route::post('/lich-lam-viec', [AdminCatalogController::class, 'schedule'])->name('schedules.store');
    });
});
