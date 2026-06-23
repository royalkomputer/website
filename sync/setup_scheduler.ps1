# Royal Komputer — Windows Task Scheduler Setup
# Run this script as Administrator to create/update the hourly sync task.

$TaskName = "RoyalKomputer Sync"
$ScriptPath = "$PSScriptRoot\sync_and_push.bat"
$RepoDir = "$PSScriptRoot\.."

# Verify the script exists
if (-not (Test-Path $ScriptPath)) {
    Write-Error "sync_and_push.bat not found at: $ScriptPath"
    exit 1
}

# Check admin rights
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Warning "Not running as Administrator. The task will be created for current user only."
}

# Remove existing task if present
$existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Removing existing task '$TaskName'..."
    $existing | Unregister-ScheduledTask -Confirm:$false
}

# Create the task action
$Action = New-ScheduledTaskAction -Execute "cmd.exe" -Argument "/c `"$ScriptPath`"" -WorkingDirectory $PSScriptRoot

# Trigger: every 1 hour, starting immediately
$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1).ToString("HH:mm") -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration (New-TimeSpan -Days 365)

# Run whether user is logged in or not
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew

# Register the task
$Principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description "Sync produk dari IPOS dan push ke GitHub setiap 1 jam" -Force

Write-Host "Task '$TaskName' created successfully."
Write-Host "It will run every 1 hour starting at 00:00."

# Show the task
Get-ScheduledTask -TaskName $TaskName | Format-Table TaskName,State,Description -AutoSize
