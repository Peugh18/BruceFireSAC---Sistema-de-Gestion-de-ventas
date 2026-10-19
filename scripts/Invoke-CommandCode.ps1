#requires -Version 7.0
[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidateNotNullOrEmpty()][string]$Objective,
    [Parameter(Mandatory)][ValidateCount(1, 4)][string[]]$Files,
    [Parameter(Mandatory)][ValidateNotNullOrEmpty()][string]$Acceptance,
    [ValidateRange(1, 8)][int]$MaxTurns = 8
)

$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskFiles = foreach ($taskFile in $Files) {
    $taskPath = if ([IO.Path]::IsPathRooted($taskFile)) { $taskFile } else { Join-Path $taskRoot $taskFile }
    $taskResolved = (Resolve-Path -LiteralPath $taskPath).Path
    $taskRelative = [IO.Path]::GetRelativePath($taskRoot, $taskResolved)
    if ([IO.Path]::IsPathRooted($taskRelative) -or $taskRelative -eq '..' -or $taskRelative.StartsWith("..$([IO.Path]::DirectorySeparatorChar)") -or !(Test-Path -LiteralPath $taskResolved -PathType Leaf)) {
        throw "Archivo fuera del proyecto o no es un archivo: $taskFile"
    }
    if ((Split-Path $taskResolved -Leaf) -match '^\.env($|\.(?!example$))') {
        throw 'No se envían archivos de entorno privados.'
    }
    $taskRelative.Replace('\', '/')
}

$taskPrompt = @"
Objetivo: $Objective
Archivos relevantes: $($taskFiles -join ', ')
Restricciones: Respeta AGENTS.md y .ai/rules/index.md con sus reglas aplicables. Lee solo esos archivos y las instrucciones necesarias. Solo lectura: devuelve el diagnóstico y, si corresponde, un diff mínimo para que Codex lo revise y aplique. No edites archivos ni ejecutes comandos. No leas .env ni secretos. No crees agentes, no cambies modelos, permisos, dependencias o facturación. No hagas commit ni push. No uses herramientas para eludir un bloqueo. Si algo falla, termina con el error y el avance.
Criterio de aceptación: $Acceptance
"@
$taskArguments = @(
    '-p', $taskPrompt,
    '--model', 'deepseek/deepseek-v4-flash',
    '--max-turns', $MaxTurns.ToString(),
    '--permission-mode', 'standard',
    '--output-format', 'json',
    '--no-session', '--no-skills', '--no-auto-update'
)
$taskLog = Join-Path $taskRoot "storage/logs/command-code-$([guid]::NewGuid().ToString('N')).jsonl"
$taskResult = $null
$taskError = $null
$taskExit = 1
Push-Location $taskRoot
try {
    $taskExecutor = Get-Command command-code -ErrorAction Stop
    & $taskExecutor @taskArguments 2>&1 | ForEach-Object {
        $taskLine = "$_"
        Add-Content -LiteralPath $taskLog -Value $taskLine -Encoding utf8
        try {
            $taskFrame = ConvertFrom-Json -InputObject $taskLine -AsHashtable -ErrorAction Stop
            if ($taskFrame['type'] -eq 'result') { $taskResult = $taskFrame }
        } catch { }
    }
    $taskExit = $LASTEXITCODE
} catch {
    $taskError = $_.Exception.Message
    Add-Content -LiteralPath $taskLog -Value $taskError -Encoding utf8
} finally {
    Pop-Location
}

$taskSucceeded = $taskExit -eq 0 -and $null -ne $taskResult -and $taskResult['subtype'] -eq 'success'
if (!$taskSucceeded -and $taskExit -eq 0) { $taskExit = 1 }
if (!$taskSucceeded -and !$taskError) {
    $taskError = if ($taskResult -and $taskResult['error']) { $taskResult['error'] } else { "Command Code terminó con código $taskExit; consulta el registro para ver el avance." }
}
[pscustomobject]@{
    Succeeded = $taskSucceeded
    ExitCode = $taskExit
    Model = 'deepseek/deepseek-v4-flash'
    MaxTurns = $MaxTurns
    Summary = if ($taskResult) { $taskResult['finalText'] } else { '' }
    Usage = if ($taskResult) { $taskResult['usage'] } else { $null }
    Error = $taskError
    ProgressLog = $taskLog
}
