param(
    [Parameter(Mandatory=$true)][string] $ExternalOrderCode,
    [Parameter(Mandatory=$true)][int] $PatientId,
    [Parameter(Mandatory=$true)][int] $VisitId,
    [string] $BaseUrl = 'http://127.0.0.1:8000',
    [string] $MessageId = ('LIS-' + [guid]::NewGuid().ToString()),
    [switch] $Duplicate
)
$ErrorActionPreference = 'Stop'
if (-not $env:CLINIC_LIS_TOKEN -or -not $env:CLINIC_LIS_SYSTEM) { throw 'Set CLINIC_LIS_TOKEN and CLINIC_LIS_SYSTEM for a source registered in admin settings.' }
$body = @{ external_message_id=$MessageId; external_order_code=$ExternalOrderCode; patient_id=$PatientId; visit_id=$VisitId; value_text='Ket qua LIS gia lap'; unit='demo_unit' } | ConvertTo-Json
$headers = @{ Authorization=('Bearer ' + $env:CLINIC_LIS_TOKEN); 'X-System-Code'=$env:CLINIC_LIS_SYSTEM; Accept='application/json' }
Invoke-RestMethod -Method Post -Uri ($BaseUrl.TrimEnd('/') + '/api/v1/integrations/lis/results') -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $body
if ($Duplicate) { Invoke-RestMethod -Method Post -Uri ($BaseUrl.TrimEnd('/') + '/api/v1/integrations/lis/results') -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $body }
