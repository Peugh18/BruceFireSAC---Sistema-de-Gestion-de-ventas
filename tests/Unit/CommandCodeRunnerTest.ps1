#requires -Version 7.0
$ErrorActionPreference = 'Stop'
$taskRunner = Join-Path $PSScriptRoot '../../scripts/Invoke-CommandCode.ps1'
$taskLogs = [System.Collections.Generic.List[string]]::new()
$global:commandCodeTestState = @{ Calls = 0; Exit = 0; Arguments = @() }
function global:command-code {
    $global:commandCodeTestState.Calls++
    $global:commandCodeTestState.Arguments = $args
    $global:LASTEXITCODE = $global:commandCodeTestState.Exit
    if ($global:commandCodeTestState.Exit -eq 0) {
        '{"type":"result","subtype":"success","finalText":"Parche propuesto","usage":{"inputTokens":20}}'
    } else {
        '{"type":"result","subtype":"max_turns","finalText":"Avance parcial","usage":{"inputTokens":30}}'
    }
}
try {
    $taskResult = & $taskRunner -Objective 'Revisar espacio inferior' -Files 'resources/js/layouts/tecnico-planta-layout.tsx' -Acceptance 'Proponer parche mínimo'
    $taskLogs.Add($taskResult.ProgressLog)
    if (!$taskResult.Succeeded -or $taskResult.Summary -ne 'Parche propuesto') { throw "No devuelve el resultado exitoso: $($taskResult | ConvertTo-Json -Compress)" }
    if ($global:commandCodeTestState.Calls -ne 1 -or $global:commandCodeTestState.Arguments -contains '--yolo' -or $global:commandCodeTestState.Arguments -notcontains 'deepseek/deepseek-v4-flash') { throw 'Ejecutor o permisos incorrectos.' }
    $global:commandCodeTestState.Exit = 8
    $taskResult = & $taskRunner -Objective 'Revisar espacio inferior' -Files 'resources/js/layouts/tecnico-planta-layout.tsx' -Acceptance 'Proponer parche mínimo'
    $taskLogs.Add($taskResult.ProgressLog)
    if ($taskResult.Succeeded -or $taskResult.ExitCode -ne 8 -or $taskResult.Summary -ne 'Avance parcial' -or $global:commandCodeTestState.Calls -ne 2) { throw 'No conserva el límite y avance o reintenta.' }
    $taskRejected = $false
    try { & $taskRunner -Objective 'Fuera del proyecto' -Files $taskRunner -Acceptance 'No llamar al modelo' -MaxTurns 9 } catch { $taskRejected = $true }
    if (!$taskRejected -or $global:commandCodeTestState.Calls -ne 2) { throw 'No rechaza un límite inválido antes de ejecutar.' }
    $taskOutside = Join-Path ([IO.Path]::GetTempPath()) "command-code-test-$([guid]::NewGuid().ToString('N')).txt"
    Set-Content -LiteralPath $taskOutside -Value 'Fixture pública'
    try {
        $taskRejected = $false
        try { & $taskRunner -Objective 'Fuera del proyecto' -Files $taskOutside -Acceptance 'No llamar al modelo' } catch { $taskRejected = $true }
        if (!$taskRejected -or $global:commandCodeTestState.Calls -ne 2) { throw 'No rechaza archivos externos antes de llamar al modelo.' }
    } finally { Remove-Item -LiteralPath $taskOutside }
    'PASS: éxito, ejecutor único/permisos, límite sin reintento y rechazo de archivos externos.'
} finally {
    Remove-Item Function:\command-code -ErrorAction SilentlyContinue
    Remove-Variable commandCodeTestState -Scope Global
    foreach ($taskLog in $taskLogs) { Remove-Item -LiteralPath $taskLog -ErrorAction SilentlyContinue }
}
