<?php

namespace App\Http\Controllers;

use App\Models\DeviceMeasurement;
use App\Models\ServiceOrderItem;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationController extends Controller
{
    public function receive(Request $request, string $type)
    {
        abort_unless(in_array($type, ['lis', 'device']), 404);
        $system = DB::table('integration_systems')->where('code', $request->header('X-System-Code'))->where('is_active', true)->first();
        abort_unless($system && strtoupper($type) === $system->type && $request->bearerToken()
            && hash_equals($system->token_hash, hash('sha256', $request->bearerToken())), 401);
        $rules = ['external_message_id' => ['required', 'string', 'max:100']];
        $rules += $type === 'lis' ? ['external_order_code' => ['required', 'string', 'max:60'], 'patient_id' => ['required', 'integer'], 'visit_id' => ['required', 'integer'],
            'value_text' => ['required', 'string', 'max:10000'], 'unit' => ['nullable', 'string', 'max:40']] : [
                'visit_id' => ['required', 'integer'], 'device_code' => ['required', 'string', 'max:60'], 'measurement_type' => ['required', 'in:temperature,pulse,weight,systolic,diastolic'],
                'value' => ['required', 'numeric', 'min:0', 'max:1000'], 'unit' => ['required', 'string', 'max:20'], 'measured_at' => ['required', 'date', 'before_or_equal:now']];
        $data = $request->validate($rules);
        ksort($data);
        $hash = hash('sha256', json_encode($data));
        $response = DB::transaction(function () use ($system, $data, $hash, $type) {
            DB::table('integration_systems')->where('id', $system->id)->update(['updated_at' => DB::raw('updated_at')]);
            $query = DB::table('integration_messages')->where('integration_system_id', $system->id)->where('external_message_id', $data['external_message_id']);
            if ($old = $query->first()) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409, 'Message ID đã dùng với nội dung khác.');

                return ['status' => $old->status, 'resource_id' => $old->resource_id, 'error_code' => $old->error_code, 'duplicate' => true];
            }
            $messageId = DB::table('integration_messages')->insertGetId(['integration_system_id' => $system->id, 'external_message_id' => $data['external_message_id'],
                'payload_hash' => $hash, 'status' => 'RECEIVED', 'created_at' => now(), 'updated_at' => now()]);
            $error = null;
            $resource = null;
            if ($type === 'lis') {
                $item = ServiceOrderItem::where('external_order_code', $data['external_order_code'])->lockForUpdate()->first();
                if (! $item || $item->order->visit->branch_id !== $system->branch_id || $item->order->visit_id !== (int) $data['visit_id'] || $item->order->visit->patient_id !== (int) $data['patient_id']) {
                    $error = 'ORDER_MAPPING_INVALID';
                } elseif (in_array($item->status, ['COMPLETED', 'CANCELLED']) || in_array($item->order->visit->status, ['COMPLETED', 'CLOSED', 'CANCELLED'])) {
                    $error = 'ORDER_CLOSED';
                } else {
                    $result = $item->results()->create(['value_text' => $data['value_text'], 'unit' => $data['unit'] ?? null, 'source' => 'MOCK_LIS', 'status' => 'RECEIVED']);
                    $resource = $result->id;
                    $item->update(['status' => 'RESULT_PENDING']);
                    $item->order->update(['status' => 'RESULT_PENDING']);
                }
            } else {
                $visit = Visit::whereKey($data['visit_id'])->where('branch_id', $system->branch_id)->first();
                $units = ['temperature' => 'Cel', 'pulse' => '/min', 'weight' => 'kg', 'systolic' => 'mmHg', 'diastolic' => 'mmHg'];
                if (! $visit || in_array($visit->status, ['CLOSED', 'CANCELLED', 'COMPLETED'])) {
                    $error = 'VISIT_MAPPING_INVALID';
                } elseif ($units[$data['measurement_type']] !== $data['unit']) {
                    $error = 'UNIT_INVALID';
                } else {
                    $resource = DeviceMeasurement::create(collect($data)->except('external_message_id')->all())->id;
                }
            }
            $status = $error ? 'FAILED' : 'PROCESSED';
            DB::table('integration_messages')->where('id', $messageId)->update(['status' => $status, 'resource_id' => $resource, 'error_code' => $error, 'updated_at' => now()]);
            \App\Models\AuditLog::record('integration.'.$status, \App\Models\Branch::findOrFail($system->branch_id), [], ['system_id' => $system->id, 'message_id' => $messageId, 'resource_id' => $resource, 'error_code' => $error]);

            return ['status' => $status, 'resource_id' => $resource, 'error_code' => $error, 'duplicate' => false];
        }, 5);

        return response()->json($response, $response['status'] === 'FAILED' ? 422 : ($response['duplicate'] ? 200 : 201));
    }
}
