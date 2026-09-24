$ErrorActionPreference = "Stop"

if (-not (Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
    throw "WSL is not installed. Install WSL Ubuntu, then run this launcher again."
}

$Distro = if ($env:MICRO_SERVICE_WSL_DISTRO) { $env:MICRO_SERVICE_WSL_DISTRO } else { "Ubuntu" }

# WSL accepts either a Windows drive path or a \\wsl$ path for --cd.
# All setup stays in run.sh so the Windows and Ubuntu launchers behave alike.
& wsl.exe -d $Distro --cd $PSScriptRoot -- bash ./run.sh
if ($LASTEXITCODE -ne 0) {
    throw "run.sh failed in WSL distribution '$Distro' (exit code $LASTEXITCODE)."
}
