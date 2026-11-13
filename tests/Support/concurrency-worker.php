<?php

// Invoked only by tests/concurrency.php against an isolated MySQL test database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_starts_with(config('database.connections.mysql.database'), 'clinic_spec_')) {
    exit(2);
}
$scenario = $argv[1];
$data = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$barrier = $argv[3];
$index = $argv[4];
auth()->setUser(App\Models\User::where('email', $scenario === 'dispense' ? 'bacsi.an@clinic.test' : 'thungan@clinic.test')->firstOrFail());
touch($barrier.'.'.$index.'.ready');
$deadline = microtime(true) + 30;
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        exit(3);
    } usleep(10000);
    clearstatcache();
}
try {
    if ($scenario === 'book') {
        app(App\Services\AppointmentBookingService::class)->book(App\Models\Patient::findOrFail($data['patient_id']), App\Models\Doctor::findOrFail($data['doctor_id']), $data['date'], '10:00', 'Race demo', null, $data['branch_id'], $data['key']);
    } elseif ($scenario === 'dispense') {
        app(App\Services\InventoryService::class)->dispense(App\Models\Prescription::findOrFail($data['prescription_id']), [$data['item_id'] => 10], $data['key']);
    } else {
        app(App\Services\BillingService::class)->pay(App\Models\Invoice::findOrFail($data['invoice_id']), ['amount' => '100.00', 'method' => 'CASH', 'idempotency_key' => $data['key']]);
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($e instanceof Illuminate\Validation\ValidationException || $e instanceof Symfony\Component\HttpKernel\Exception\HttpException) {
        echo json_encode(['ok' => false, 'rejected' => true]);
    } else {
        fwrite(STDERR, get_class($e).': '.$e->getMessage());
        exit(1);
    }
}
