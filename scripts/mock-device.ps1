param(
    [Parameter(Mandatory=$true)][int] $VisitId,
    [string] $BaseUrl = 'http://127.0.0.1:8000',
    [string] $Unit = 'Cel',
    [string] $MessageId = ('DEVICE-' + [guid]::NewGuid().ToString()),
    [switch] $Duplicate
)
$ErrorActionPreference = 'Stop'
if (-not $env:CLINIC_DEVICE_TOKEN -or -not $env:CLINIC_DEVICE_SYSTEM) { throw 'Set CLINIC_DEVICE_TOKEN and CLINIC_DEVICE_SYSTEM for a registered DEVICE source.' }
$body = @{ external_message_id=$MessageId; visit_id=$VisitId; device_code='MOCK-01'; measurement_type='temperature'; value=36; unit=$Unit; measured_at=[DateTimeOffset]::Now.ToString('o') } | ConvertTo-Json
$headers = @{ Authorization=('Bearer ' + $env:CLINIC_DEVICE_TOKEN); 'X-System-Code'=$env:CLINIC_DEVICE_SYSTEM; Accept='application/json' }
Invoke-RestMethod -Method Post -Uri ($BaseUrl.TrimEnd('/') + '/api/v1/integrations/device/results') -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $body
if ($Duplicate) { Invoke-RestMethod -Method Post -Uri ($BaseUrl.TrimEnd('/') + '/api/v1/integrations/device/results') -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $body }
