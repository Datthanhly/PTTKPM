<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DiagnosticResult;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\NotificationJob;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentBookingService;
use App\Services\BillingService;
use App\Services\FollowUpService;
use App\Services\NotificationService;
use App\Services\ReportService;
use App\Services\VisitWorkflow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SpecificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loginAs(string $email): User
    {
        $u = User::where('email', $email)->firstOrFail();
        $this->actingAs($u)->withSession(['password_hash_web' => $u->password]);

        return $u;
    }

    private function visit(): Visit
    {
        $this->loginAs('letan@clinic.test');
        $v = app(VisitWorkflow::class)->walkIn(Patient::firstOrFail(), Doctor::where('doctor_code', 'BS0001')->firstOrFail(), Branch::where('code', 'CN01')->value('id'), 'Lý do mô phỏng');
        $this->loginAs('bacsi.an@clinic.test');
        app(VisitWorkflow::class)->transition($v, 'IN_PROGRESS');

        return $v->fresh();
    }

    private function items(int $quantity = 5): array
    {
        return [['medicine_id' => Medicine::firstOrFail()->id, 'dosage' => 'Liều demo', 'route' => 'Đường dùng demo', 'frequency' => 'Tần suất demo', 'duration_days' => 3, 'quantity' => $quantity, 'instructions' => 'Không dùng điều trị']];
    }

    private function completed(): Visit
    {
        $v = $this->visit();
        app(VisitWorkflow::class)->record($v, ['version' => 0, 'action' => 'sign', 'diagnosis' => 'Chẩn đoán mô phỏng']);
        app(VisitWorkflow::class)->transition($v, 'COMPLETED');

        return $v->fresh();
    }

    public function test_role_pages_and_operational_forms_render(): void
    {
        foreach (['benhnhan@clinic.test' => ['/bang-dieu-khien', '/ho-so', '/lich-su-kham', '/tai-kham', '/doi-mat-khau'],
            'letan@clinic.test' => ['/benh-nhan', '/luot-kham', '/lich-kham/dat-lich'],
            'bacsi.an@clinic.test' => ['/luot-kham', '/chan-doan', '/kho'], 'thungan@clinic.test' => ['/bao-cao', '/luot-kham'],
            'admin@clinic.test' => ['/bang-dieu-khien', '/quan-tri/cau-hinh', '/quan-tri/danh-muc', '/kho', '/bao-cao']] as $email => $pages) {
            $this->loginAs($email);
            foreach ($pages as $page) {
                $this->get($page)->assertOk();
            }
        }
        $v = $this->visit();
        $this->get('/luot-kham/'.$v->id)->assertOk()->assertSee('Hồ sơ');
    }

    public function test_check_in_is_unique_and_creates_a_waiting_visit(): void
    {
        $a = Appointment::firstOrFail();
        $a->update(['status' => 'CONFIRMED', 'appointment_date' => today()]);
        $this->loginAs('letan@clinic.test');
        $this->patch('/nhan-vien/lich-hen/'.$a->id.'/trang-thai', ['status' => 'CHECKED_IN'])->assertRedirect();
        $this->assertDatabaseHas('visits', ['appointment_id' => $a->id, 'status' => 'WAITING']);
        $this->patchJson('/nhan-vien/lich-hen/'.$a->id.'/trang-thai', ['status' => 'CHECKED_IN'])->assertStatus(409);
        $this->assertSame(1, Visit::where('appointment_id', $a->id)->count());
    }

    public function test_walk_in_has_no_fake_appointment_and_doctor_scope_is_enforced(): void
    {
        $v = $this->visit();
        $this->assertNull($v->appointment_id);
        $this->loginAs('bacsi.ha@clinic.test');
        $this->get('/luot-kham/'.$v->id)->assertForbidden();
        $this->loginAs('admin@clinic.test');
        $this->get('/luot-kham/'.$v->id)->assertForbidden();
        $this->putJson('/luot-kham/'.$v->id.'/ho-so', ['action' => 'sign', 'version' => 0, 'diagnosis' => 'x'])->assertForbidden();
    }

    public function test_signed_record_requires_amendment_and_version_conflicts_are_rejected(): void
    {
        $v = $this->visit();
        $this->put('/luot-kham/'.$v->id.'/ho-so', ['action' => 'draft', 'version' => 0, 'symptoms' => 'Nháp'])->assertRedirect();
        $this->assertDatabaseHas('medical_records', ['visit_id' => $v->id, 'status' => 'DRAFT', 'version' => 1]);
        $this->putJson('/luot-kham/'.$v->id.'/ho-so', ['action' => 'sign', 'version' => 0, 'diagnosis' => 'x'])->assertStatus(409);
        $this->put('/luot-kham/'.$v->id.'/ho-so', ['action' => 'sign', 'version' => 1, 'diagnosis' => 'Đã ký'])->assertRedirect();
        $this->putJson('/luot-kham/'.$v->id.'/ho-so', ['action' => 'draft', 'version' => 2, 'diagnosis' => 'Ghi đè'])->assertStatus(422);
        $this->put('/luot-kham/'.$v->id.'/ho-so', ['action' => 'sign', 'version' => 2, 'diagnosis' => 'Điều chỉnh', 'amendment_reason' => 'Bổ sung demo'])->assertRedirect();
        $this->assertDatabaseHas('medical_record_revisions', ['medical_record_id' => $v->medicalRecord->id, 'version' => 2, 'reason' => 'Bổ sung demo']);
    }

    public function test_prescription_signing_does_not_change_stock_and_reception_cannot_sign(): void
    {
        $v = $this->visit();
        $stock = MedicineBatch::sum('available_qty');
        $p = app(VisitWorkflow::class)->prescription($v, $this->items());
        $this->loginAs('letan@clinic.test');
        $this->post('/don-thuoc/'.$p->id.'/ky')->assertForbidden();
        $this->loginAs('bacsi.an@clinic.test');
        $this->post('/don-thuoc/'.$p->id.'/ky')->assertRedirect();
        $this->assertSame('SIGNED', $p->fresh()->status);
        $this->assertEquals($stock, MedicineBatch::sum('available_qty'));
    }

    public function test_dispensing_rolls_back_on_shortage_rejects_expired_lots_and_retries_once(): void
    {
        $v = $this->visit();
        $p = app(VisitWorkflow::class)->prescription($v, $this->items(20));
        app(VisitWorkflow::class)->signPrescription($p);
        $batch = MedicineBatch::where('medicine_id', $p->items->first()->medicine_id)->firstOrFail();
        $batch->update(['available_qty' => 10]);
        $url = '/kho/don/'.$p->id.'/cap-phat';
        $itemId = $p->items->first()->id;
        $this->postJson($url, ['quantities' => [$itemId => 20], 'idempotency_key' => 'shortage'])->assertStatus(422);
        $this->assertEquals(10, $batch->fresh()->available_qty);
        $this->assertDatabaseCount('dispensations', 0);
        $batch->update(['expiry_date' => today()->subDay()]);
        $this->postJson($url, ['quantities' => [$itemId => 5], 'idempotency_key' => 'expired'])->assertStatus(422);
        $this->assertEquals(10, $batch->fresh()->available_qty);
        $batch->update(['expiry_date' => today()->addMonth()]);
        $request = ['quantities' => [$itemId => 5], 'idempotency_key' => 'dispense-once'];
        $this->post($url, $request)->assertRedirect();
        $this->post($url, $request)->assertRedirect();
        $this->assertEquals(5, $batch->fresh()->available_qty);
        $this->assertDatabaseCount('dispensations', 1);
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $batch->id, 'quantity_delta' => -5, 'type' => 'DISPENSE']);
    }

    public function test_service_result_is_pending_until_authorized_verification(): void
    {
        $v = $this->visit();
        app(VisitWorkflow::class)->order($v, [Service::firstOrFail()->id]);
        $item = ServiceOrderItem::firstOrFail();
        $this->post('/chi-dinh/'.$item->id.'/ket-qua', ['value_text' => 'Kết quả demo', 'unit' => 'demo_unit'])->assertRedirect();
        $r = DiagnosticResult::firstOrFail();
        $this->assertSame('RECEIVED', $r->status);
        $this->loginAs('benhnhan@clinic.test');
        $this->get('/lich-su-kham')->assertDontSee('Kết quả demo');
        $this->loginAs('admin@clinic.test');
        $this->post('/ket-qua/'.$r->id.'/duyet', ['status' => 'VERIFIED'])->assertForbidden();
        $this->loginAs('bacsi.an@clinic.test');
        $this->post('/ket-qua/'.$r->id.'/duyet', ['status' => 'VERIFIED'])->assertRedirect();
        $this->assertSame('VERIFIED', $r->fresh()->status);
        $this->assertSame('COMPLETED', $item->order->fresh()->status);
    }

    public function test_invoice_snapshot_partial_payment_refund_and_idempotency(): void
    {
        $v = $this->completed();
        $this->loginAs('thungan@clinic.test');
        $this->post('/thu-ngan/luot/'.$v->id.'/hoa-don')->assertRedirect();
        $i = $v->invoice;
        $this->assertSame('ISSUED', $i->status);
        $this->assertEquals(20000000, $i->outstanding_cents);
        $this->post('/thu-ngan/luot/'.$v->id.'/hoa-don')->assertStatus(409);
        $data = ['amount' => '50000.25', 'method' => 'CASH', 'idempotency_key' => 'pay-once'];
        $this->post('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', $data)->assertRedirect();
        $this->post('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', $data)->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(14999975, $i->fresh()->outstanding_cents);
        $this->postJson('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', [...$data, 'amount' => '60000'])->assertStatus(409);
        $this->postJson('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', [...$data, 'idempotency_key' => 'too-much', 'amount' => '999999'])->assertStatus(422);
        $this->postJson('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', [...$data, 'idempotency_key' => 'transfer', 'method' => 'TRANSFER'])->assertStatus(422);
        $this->post('/thu-ngan/giao-dich/'.$i->payments->first()->id.'/hoan-tien', ['amount' => '10000.25', 'reason' => 'Hoàn demo', 'idempotency_key' => 'refund-once'])->assertRedirect();
        $this->assertEquals(16000000, $i->fresh()->outstanding_cents);
        $stats = app(ReportService::class)->summary('2026-11-12', '2026-11-12');
        $this->assertSame(4000000, $stats['revenue_cents']);
        Service::query()->update(['price' => 999999]);
        $this->assertSame('200000.00', $i->fresh()->total);
        $this->get('/thu-ngan/luot/'.$v->id)->assertOk()->assertSee('PARTIAL');
    }

    public function test_branch_scope_applies_to_billing_staff_patient_search_and_exports(): void
    {
        $v = $this->completed();
        $this->loginAs('thungan@clinic.test');
        $i = app(BillingService::class)->issue($v);
        $this->loginAs('thungan.cn2@clinic.test');
        $this->get('/thu-ngan/luot/'.$v->id)->assertForbidden();
        $this->post('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', ['amount' => 1, 'method' => 'CASH', 'idempotency_key' => 'cross-branch'])->assertForbidden();
        $this->get('/bao-cao?branch_id='.$v->branch_id)->assertOk()->assertDontSee($i->invoice_code);
        $response = $this->get('/bao-cao/revenue/excel?branch_id='.$v->branch_id);
        $response->assertOk();
        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertStringNotContainsString($i->invoice_code, $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $this->loginAs('letan.cn2@clinic.test');
        $this->get('/benh-nhan')->assertDontSee('BN00001');
        $this->loginAs('admin@clinic.test');
        $this->post('/kho/don/999/cap-phat')->assertNotFound();
    }

    public function test_schedule_overlap_leave_and_reschedule_history(): void
    {
        Carbon::setTestNow('2026-11-12 07:00:00'); // More than 24 hours before the seeded appointment.
        $a = Appointment::firstOrFail();
        $doctor = $a->doctor;
        $other = Branch::where('code', 'CN02')->firstOrFail();
        $doctor->user->branches()->attach($other->id);
        $this->loginAs('admin@clinic.test');
        $this->postJson('/quan-tri/lich-lam-viec', ['doctor_id' => $doctor->id, 'branch_id' => $other->id, 'work_date' => $a->appointment_date->format('Y-m-d'), 'start_time' => '08:15', 'end_time' => '10:00', 'slot_minutes' => 30, 'capacity' => 1])->assertStatus(422);
        $this->postJson('/quan-tri/lich-nghi', ['doctor_id' => $doctor->id, 'starts_at' => $a->appointment_date->format('Y-m-d').' 08:00', 'ends_at' => $a->appointment_date->format('Y-m-d').' 09:00', 'reason' => 'Nghỉ demo'])->assertStatus(422);
        $this->loginAs('benhnhan@clinic.test');
        $this->patch('/lich-kham/'.$a->id.'/doi', ['appointment_date' => $a->appointment_date->format('Y-m-d'), 'start_time' => '09:30', 'reason' => 'Đổi giờ demo'])->assertRedirect();
        $this->assertDatabaseHas('appointment_history', ['appointment_id' => $a->id, 'reason' => 'Đổi giờ demo']);
        $this->loginAs('admin@clinic.test');
        $this->post('/quan-tri/lich-nghi', ['doctor_id' => $doctor->id, 'starts_at' => $a->appointment_date->format('Y-m-d').' 08:00', 'ends_at' => $a->appointment_date->format('Y-m-d').' 09:00', 'reason' => 'Nghỉ demo'])->assertRedirect();
        $slots = app(AppointmentBookingService::class)->availability($doctor, $a->branch_id, $a->appointment_date->format('Y-m-d'));
        $this->assertNotContains('08:00', array_column($slots, 'start_time'));
    }

    public function test_lis_de_duplication_bad_mapping_and_device_unit_validation(): void
    {
        $v = $this->visit();
        app(VisitWorkflow::class)->order($v, [Service::firstOrFail()->id]);
        $item = ServiceOrderItem::firstOrFail();
        $token = str_repeat('x', 40);
        DB::table('integration_systems')->insert(['code' => 'LIS-DEMO', 'type' => 'LIS', 'branch_id' => $v->branch_id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
        $body = ['external_message_id' => 'msg-1', 'external_order_code' => $item->external_order_code, 'patient_id' => $v->patient_id, 'visit_id' => $v->id, 'value_text' => 'Kết quả LIS demo', 'unit' => 'demo_unit'];
        $headers = ['X-System-Code' => 'LIS-DEMO', 'Authorization' => 'Bearer '.$token];
        $this->postJson('/api/v1/integrations/lis/results', $body)->assertUnauthorized();
        $this->postJson('/api/v1/integrations/lis/results', $body, $headers)->assertCreated();
        $this->postJson('/api/v1/integrations/lis/results', $body, $headers)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertDatabaseCount('diagnostic_results', 1);
        $this->postJson('/api/v1/integrations/lis/results', [...$body, 'external_message_id' => 'bad-code', 'external_order_code' => 'WRONG'], $headers)->assertStatus(422)->assertJson(['error_code' => 'ORDER_MAPPING_INVALID']);
        DB::table('integration_systems')->insert(['code' => 'DEVICE-DEMO', 'type' => 'DEVICE', 'branch_id' => $v->branch_id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
        $device = ['external_message_id' => 'd-1', 'visit_id' => $v->id, 'device_code' => 'MOCK-1', 'measurement_type' => 'temperature', 'value' => 36, 'unit' => 'WRONG', 'measured_at' => now()->toIso8601String()];
        $headers['X-System-Code'] = 'DEVICE-DEMO';
        $this->postJson('/api/v1/integrations/device/results', $device, $headers)->assertStatus(422)->assertJson(['error_code' => 'UNIT_INVALID']);
        $this->postJson('/api/v1/integrations/device/results', [...$device, 'external_message_id' => 'd-2', 'unit' => 'Cel'], $headers)->assertCreated();
        $this->assertDatabaseHas('device_measurements', ['visit_id' => $v->id, 'status' => 'RECEIVED']);
    }

    public function test_follow_up_never_auto_books_without_valid_consent_and_only_books_once(): void
    {
        $v = $this->completed();
        $p = $v->followUps()->create(['suggested_from' => '2026-11-14', 'suggested_to' => '2026-11-16', 'reason' => 'Theo dõi mô phỏng']);
        $before = Appointment::count();
        app(FollowUpService::class)->process();
        $this->assertSame($before, Appointment::count());
        $v->patient->update(['auto_booking_consented_at' => now(), 'auto_booking_expires_at' => now()->addMonths(6)]);
        app(FollowUpService::class)->process();
        app(FollowUpService::class)->process();
        $this->assertSame($before + 1, Appointment::count());
        $this->assertSame('SCHEDULED', $p->fresh()->status);
        $this->loginAs('benhnhan@clinic.test');
        $this->put('/ho-so', ['auto_booking' => 0, 'email_enabled' => 1])->assertRedirect();
        $this->assertNull($v->patient->fresh()->auto_booking_consented_at);
    }

    public function test_email_failure_preserves_booking_and_retries_with_backoff(): void
    {
        $a = Appointment::firstOrFail();
        app(NotificationService::class)->appointment($a, 'CREATED');
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Temporary SMTP error'));
        app(NotificationService::class)->process();
        $job = NotificationJob::firstOrFail();
        $this->assertSame('RETRY', $job->status);
        $this->assertSame(1, $job->retry_count);
        $this->assertTrue($job->scheduled_at->isFuture());
        $this->assertNotNull($a->fresh());
    }

    public function test_email_job_is_sent_once(): void
    {
        $a = Appointment::firstOrFail();
        app(NotificationService::class)->appointment($a, 'CREATED');
        Mail::shouldReceive('raw')->once();
        $service = app(NotificationService::class);
        $service->process();
        $service->process();
        $this->assertDatabaseHas('notification_jobs', ['status' => 'SENT']);
    }

    public function test_pdfs_use_vietnamese_font_and_enforce_ownership(): void
    {
        $v = $this->visit();
        $p = app(VisitWorkflow::class)->prescription($v, $this->items());
        app(VisitWorkflow::class)->signPrescription($p);
        $response = $this->get('/don-thuoc/'.$p->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        if (getenv('CLINIC_RENDER_PDFS')) {
            if (! is_dir(storage_path('framework/testing/pdf'))) {
                mkdir(storage_path('framework/testing/pdf'), 0755, true);
            }
            file_put_contents(storage_path('framework/testing/pdf/prescription.pdf'), $response->getContent());
        }
        $this->loginAs('admin@clinic.test');
        $this->get('/don-thuoc/'.$p->id.'/pdf')->assertForbidden();
        $this->loginAs('bacsi.an@clinic.test');
        app(VisitWorkflow::class)->record($v, ['version' => 0, 'action' => 'sign', 'diagnosis' => 'Mô phỏng']);
        app(VisitWorkflow::class)->transition($v, 'COMPLETED');
        $this->loginAs('thungan@clinic.test');
        $i = app(BillingService::class)->issue($v);
        $response = $this->get('/hoa-don/'.$i->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        if (getenv('CLINIC_RENDER_PDFS')) {
            file_put_contents(storage_path('framework/testing/pdf/invoice.pdf'), $response->getContent());
        }
        $this->loginAs('benhnhan@clinic.test');
        $this->get('/don-thuoc/'.$p->id.'/pdf')->assertOk();
    }

    public function test_locked_account_is_denied_even_with_an_existing_session(): void
    {
        $u = $this->loginAs('letan@clinic.test');
        $u->update(['status' => 'LOCKED']);
        $this->get('/benh-nhan')->assertForbidden();
        $this->assertGuest();
    }

    public function test_five_role_end_to_end_booking_to_payment_dispensing_and_reports(): void
    {
        $patient = $this->loginAs('benhnhan@clinic.test');
        $doctor = Doctor::where('doctor_code', 'BS0001')->firstOrFail();
        $branch = Branch::where('code', 'CN01')->value('id');
        $date = '2026-11-13';
        $this->get('/bac-si?branch_id='.$branch)->assertOk()->assertSee($doctor->user->name);
        $this->get('/bac-si/'.$doctor->id)->assertOk();
        $booking = ['branch_id' => $branch, 'doctor_id' => $doctor->id, 'appointment_date' => $date, 'start_time' => '10:00', 'reason' => 'Khám mô phỏng E2E', 'idempotency_key' => 'e2e-book'];
        $this->post('/lich-kham', $booking)->assertRedirect();
        $this->post('/lich-kham', $booking)->assertRedirect();
        $a = Appointment::where('idempotency_key', 'e2e-book')->firstOrFail();
        $this->assertSame($patient->patient->id, $a->patient_id);
        Carbon::setTestNow('2026-11-13 09:00:00');
        $this->loginAs('letan@clinic.test');
        $this->patch('/nhan-vien/lich-hen/'.$a->id.'/trang-thai', ['status' => 'CONFIRMED'])->assertRedirect();
        $this->patch('/nhan-vien/lich-hen/'.$a->id.'/trang-thai', ['status' => 'CHECKED_IN'])->assertRedirect();
        $v = $a->visit;
        $this->loginAs('bacsi.an@clinic.test');
        $this->patch('/luot-kham/'.$v->id.'/trang-thai', ['status' => 'IN_PROGRESS'])->assertRedirect();
        $this->post('/luot-kham/'.$v->id.'/dich-vu', ['service_ids' => [Service::where('code', 'DV004')->value('id')]])->assertRedirect();
        $item = ServiceOrderItem::firstOrFail();
        $this->post('/chi-dinh/'.$item->id.'/ket-qua', ['value_text' => 'Đã kiểm tra mô phỏng'])->assertRedirect();
        $this->post('/ket-qua/'.DiagnosticResult::firstOrFail()->id.'/duyet', ['status' => 'VERIFIED'])->assertRedirect();
        $this->put('/luot-kham/'.$v->id.'/ho-so', ['action' => 'sign', 'version' => 0, 'diagnosis' => 'Kết luận E2E mô phỏng'])->assertRedirect();
        $this->post('/luot-kham/'.$v->id.'/don-thuoc', ['items' => $this->items(), 'bill_at_clinic' => 1])->assertRedirect();
        $p = Prescription::firstOrFail();
        $this->post('/don-thuoc/'.$p->id.'/ky')->assertRedirect();
        $this->patch('/luot-kham/'.$v->id.'/trang-thai', ['status' => 'COMPLETED'])->assertRedirect();
        $this->assertSame('COMPLETED', $a->fresh()->status);
        $this->loginAs('thungan@clinic.test');
        $this->post('/thu-ngan/luot/'.$v->id.'/hoa-don', ['discount' => '20000', 'discount_reason' => 'Ưu đãi demo'])->assertRedirect();
        $i = $v->invoice;
        $this->assertSame('435000.00', $i->total);
        Service::where('code', 'DV004')->update(['price' => 999999]);
        Medicine::query()->update(['price' => 9999]);
        $this->assertSame('435000.00', $i->fresh()->total);
        $this->post('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', ['amount' => '50000', 'method' => 'CASH', 'idempotency_key' => 'e2e-cash'])->assertRedirect();
        $this->post('/thu-ngan/hoa-don/'.$i->id.'/thanh-toan', ['amount' => '385000', 'method' => 'TRANSFER', 'confirmed' => 1, 'reference' => 'DEMO', 'idempotency_key' => 'e2e-transfer'])->assertRedirect();
        $this->assertSame('PAID', $i->fresh()->payment_status);
        $this->assertSame('ISSUED', $i->fresh()->status);
        $this->loginAs('bacsi.an@clinic.test');
        $this->post('/kho/don/'.$p->id.'/cap-phat', ['quantities' => [$p->items->first()->id => 5], 'idempotency_key' => 'e2e-dispense'])->assertRedirect();
        $this->assertEquals(95, MedicineBatch::where('medicine_id', $p->items->first()->medicine_id)->value('available_qty'));
        $this->put('/luot-kham/'.$v->id.'/ho-so', ['action' => 'sign', 'version' => 1, 'diagnosis' => 'Bổ sung E2E', 'amendment_reason' => 'Bổ sung sau hoàn tất'])->assertRedirect();
        $this->loginAs('benhnhan@clinic.test');
        $this->get('/lich-su-kham')->assertOk()->assertSee('Bổ sung E2E')->assertSee('Đã kiểm tra mô phỏng');
        $this->loginAs('admin@clinic.test');
        $this->get('/bao-cao?from=2026-11-13&to=2026-11-13')->assertOk()->assertSee('435,000');
        $this->assertDatabaseCount('invoice_items', 3);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_capacity_and_patient_double_booking_are_both_checked(): void
    {
        $a = Appointment::firstOrFail();
        $a->schedule->update(['capacity' => 2]);
        $p = Patient::create(['patient_code' => 'CAPACITY', 'full_name' => 'Bệnh nhân capacity demo']);
        $booking = app(AppointmentBookingService::class);
        $booking->book($p, $a->doctor, $a->appointment_date->format('Y-m-d'), '08:00', 'Demo', null, $a->branch_id, 'capacity-second');
        $slots = $booking->availability($a->doctor, $a->branch_id, $a->appointment_date->format('Y-m-d'));
        $this->assertNotContains('08:00', array_column($slots, 'start_time'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $booking->book($p, $a->doctor, $a->appointment_date->format('Y-m-d'), '08:00', 'Duplicate patient', null, $a->branch_id);
    }

    public function test_xlsx_treats_untrusted_strings_as_text(): void
    {
        $path = app(\App\Services\XlsxExporter::class)->write([['=1+1', '+formula', '@SUM(A1)', 123.45]]);
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('t="inlineStr"', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertStringContainsString('<v>123.45</v>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
        $zip->close();
        unlink($path);
    }
}
