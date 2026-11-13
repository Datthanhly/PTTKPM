<?php

/** Run with APP_ENV=testing, DB_CONNECTION=mysql, DB_DATABASE=clinic_spec_race.
 * This standalone integration check resets only a database with that prefix.
 */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    exit(1);
});
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! app()->environment('testing') || config('database.default') !== 'mysql' || ! str_starts_with(config('database.connections.mysql.database'), 'clinic_spec_')) {
    fwrite(STDERR, "Use an isolated clinic_spec_* MySQL database and APP_ENV=testing.\n");
    exit(2);
}
Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
$directory = storage_path('framework/testing/race-'.bin2hex(random_bytes(6)));
mkdir($directory, 0755, true);
function race(string $scenario, array $requests, string $directory): array
{
    $barrier = $directory.'/'.$scenario.'-'.bin2hex(random_bytes(3));
    $children = [];
    foreach ($requests as $i => $request) {
        $process = proc_open([PHP_BINARY, __DIR__.'/Support/concurrency-worker.php', $scenario, json_encode($request), $barrier, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot spawn worker');
        }
        fclose($pipes[0]);
        $children[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 30;
    while (! file_exists($barrier.'.0.ready') || ! file_exists($barrier.'.1.ready')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Worker startup timeout');
        } usleep(10000);
        clearstatcache();
    }
    touch($barrier);
    $results = [];
    foreach ($children as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($error ?: 'Worker failed');
        }
        $results[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    return $results;
}
function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    } echo "PASS: $message\n";
}
$branch = Branch::where('code', 'CN01')->value('id');
$doctor = Doctor::where('doctor_code', 'BS0001')->firstOrFail();
$p1 = Patient::create(['patient_code' => 'RACE-1', 'full_name' => 'Race demo 1', 'registered_branch_id' => $branch]);
$p2 = Patient::create(['patient_code' => 'RACE-2', 'full_name' => 'Race demo 2', 'registered_branch_id' => $branch]);
$date = $doctor->schedules()->orderBy('work_date')->firstOrFail()->work_date->format('Y-m-d');
$results = race('book', [['patient_id' => $p1->id, 'doctor_id' => $doctor->id, 'branch_id' => $branch, 'date' => $date, 'key' => 'race-book-1'], ['patient_id' => $p2->id, 'doctor_id' => $doctor->id, 'branch_id' => $branch, 'date' => $date, 'key' => 'race-book-2']], $directory);
check(count(array_filter($results, fn ($r) => $r['ok'])) === 1 && DB::table('appointments')->where('start_time', '10:00:00')->whereDate('appointment_date', $date)->count() === 1, 'Two bookings, one capacity=1 slot');
$visits = [];
$requests = [];
foreach ([$p1, $p2] as $i => $patient) {
    $v = Visit::create(['visit_code' => 'RACE-VISIT-'.$i, 'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'branch_id' => $branch, 'status' => 'COMPLETED', 'reason' => 'Race demo', 'checked_in_at' => now(), 'completed_at' => now()]);
    $visits[] = $v;
    $p = Prescription::create(['visit_id' => $v->id, 'doctor_id' => $doctor->id, 'status' => 'SIGNED', 'signed_at' => now(), 'signed_by' => $doctor->user_id]);
    $item = $p->items()->create(['medicine_id' => Medicine::firstOrFail()->id, 'dosage' => 'demo', 'route' => 'demo', 'frequency' => 'demo', 'duration_days' => 1, 'quantity' => 10, 'instructions' => 'demo']);
    $requests[] = ['prescription_id' => $p->id, 'item_id' => $item->id, 'key' => 'race-dispense-'.$i];
}
$batch = MedicineBatch::where('medicine_id', Medicine::firstOrFail()->id)->firstOrFail();
$batch->update(['available_qty' => 10]);
$results = race('dispense', $requests, $directory);
check(count(array_filter($results, fn ($r) => $r['ok'])) === 1 && $batch->fresh()->available_qty === 0 && (int) DB::table('stock_movements')->where('type', 'DISPENSE')->sum('quantity_delta') === -10, 'Two prescriptions share one lot, no negative stock');
$invoice = Invoice::create(['invoice_code' => 'RACE-INVOICE-1', 'visit_id' => $visits[0]->id, 'branch_id' => $branch, 'status' => 'ISSUED', 'subtotal' => '100.00', 'total' => '100.00', 'issued_at' => now()]);
$results = race('pay', [['invoice_id' => $invoice->id, 'key' => 'race-pay-1'], ['invoice_id' => $invoice->id, 'key' => 'race-pay-2']], $directory);
check(count(array_filter($results, fn ($r) => $r['ok'])) === 1 && $invoice->fresh()->received_cents === 10000, 'Two payments, no collection above outstanding balance');
$invoice2 = Invoice::create(['invoice_code' => 'RACE-INVOICE-2', 'visit_id' => $visits[1]->id, 'branch_id' => $branch, 'status' => 'ISSUED', 'subtotal' => '100.00', 'total' => '100.00', 'issued_at' => now()]);
$results = race('pay-duplicate', [['invoice_id' => $invoice2->id, 'key' => 'race-pay-same'], ['invoice_id' => $invoice2->id, 'key' => 'race-pay-same']], $directory);
check(count(array_filter($results, fn ($r) => $r['ok'])) === 2 && $invoice2->payments()->count() === 1, 'Concurrent duplicate payment returns the same transaction');
