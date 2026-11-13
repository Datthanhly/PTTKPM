<?php

return [
    'cancellation_hours' => (int) env('CLINIC_CANCELLATION_HOURS', 24),
    'booking_status' => env('CLINIC_BOOKING_STATUS', 'PENDING'),
    'consultation_fee' => (int) env('CLINIC_CONSULTATION_FEE', 200000),
    'reminder_hours' => [24, 2],
    'working_hours' => [
        'morning' => ['08:00', '11:30'],
        'afternoon' => ['13:30', '17:00'],
    ],
    'slot_minutes' => 30,
    // Permissions are explicit: administration never grants clinical privileges.
    'permissions' => [
        'patient' => ['APPOINTMENT_CREATE'],
        'receptionist' => ['PATIENT_ADMIN_WRITE', 'APPOINTMENT_CREATE', 'APPOINTMENT_RESCHEDULE', 'VISIT_CHECK_IN'],
        'doctor' => ['VISIT_TREAT', 'RECORD_SIGN', 'PRESCRIPTION_SIGN', 'SERVICE_ORDER_CREATE'],
        'cashier' => ['INVOICE_ISSUE', 'PAYMENT_COLLECT', 'REPORT_VIEW', 'REPORT_EXPORT'],
        'admin' => ['BRANCH_MANAGE', 'SCHEDULE_MANAGE', 'INVENTORY_MANAGE', 'REPORT_VIEW', 'REPORT_EXPORT', 'INTEGRATION_MANAGE'],
    ],
    'extra_permissions' => ['LAB_RESULT_ENTER', 'LAB_RESULT_VERIFY', 'MEDICINE_DISPENSE', 'INVENTORY_MANAGE'],
];
