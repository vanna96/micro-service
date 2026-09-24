$ErrorActionPreference = "Stop"

$NetworkName = "app-network"
$ServiceDirectories = @("database", "backend", "fastapi", "file-storage", "frontend-ui5")
$ProjectRoot = $PSScriptRoot

Set-Location $ProjectRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker is not installed or is not available in PATH."
}

& docker info *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Docker is not running. Start Docker Desktop and try again."
}

$UseComposePlugin = $false
& docker compose version *> $null
if ($LASTEXITCODE -eq 0) {
    $UseComposePlugin = $true
} elseif (-not (Get-Command docker-compose -ErrorAction SilentlyContinue)) {
    throw "Docker Compose is not installed."
}

function Invoke-Compose {
    param(
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]] $ComposeArguments
    )

    if ($UseComposePlugin) {
        & docker compose @ComposeArguments
    } else {
        & docker-compose @ComposeArguments
    }
}

foreach ($ServiceDirectory in $ServiceDirectories) {
    $ServicePath = Join-Path $ProjectRoot $ServiceDirectory
    if (Test-Path -LiteralPath $ServicePath -PathType Container) {
        Write-Host "Cleaning Docker Compose in $ServiceDirectory"
        Push-Location $ServicePath
        try {
            Invoke-Compose down
        } finally {
            Pop-Location
        }
    } else {
        Write-Warning "Skipping missing directory $ServiceDirectory"
    }
}

& docker network inspect $NetworkName *> $null
if ($LASTEXITCODE -eq 0) {
    $ContainerOutput = & docker network inspect $NetworkName --format '{{range .Containers}}{{.Name}} {{end}}'
    if ($LASTEXITCODE -ne 0) {
        throw "Could not inspect Docker network $NetworkName."
    }

    $AttachedContainers = @($ContainerOutput -split '\s+' | Where-Object { $_ })
    if ($AttachedContainers.Count -gt 0) {
        Write-Host "Stopping containers still attached to $NetworkName"
        & docker stop @AttachedContainers
        if ($LASTEXITCODE -ne 0) {
            throw "Could not stop containers attached to $NetworkName."
        }
    }

    & docker network rm $NetworkName
    if ($LASTEXITCODE -ne 0) {
        throw "Could not remove Docker network $NetworkName."
    }
}

Write-Host "Creating network $NetworkName"
& docker network create $NetworkName | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "Could not create Docker network $NetworkName."
}

foreach ($ServiceDirectory in $ServiceDirectories) {
    $ServicePath = Join-Path $ProjectRoot $ServiceDirectory
    if (Test-Path -LiteralPath $ServicePath -PathType Container) {
        Write-Host "Starting Docker Compose in $ServiceDirectory"
        Push-Location $ServicePath
        try {
            Invoke-Compose up -d --build --force-recreate
            if ($LASTEXITCODE -ne 0) {
                throw "Docker Compose failed in $ServiceDirectory."
            }

            if ($ServiceDirectory -eq "database") {
                Write-Host "Ensuring MySQL tenant database permissions"
                & docker exec mysql_db sh /docker-entrypoint-initdb.d/01-grant-tenant-database-access.sh
                if ($LASTEXITCODE -ne 0) {
                    throw "Could not configure MySQL tenant database permissions."
                }
            }
        } finally {
            Pop-Location
        }
    }
}

foreach ($ContainerName in @("laravel_app1", "laravel_app2", "laravel_queue")) {
    $IsRunning = & docker container inspect -f '{{.State.Running}}' $ContainerName 2>$null
    if ($LASTEXITCODE -eq 0 -and $IsRunning -eq "true") {
        & docker exec $ContainerName chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
        if ($LASTEXITCODE -ne 0) {
            throw "Could not update ownership in $ContainerName."
        }

        & docker exec $ContainerName chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache
        if ($LASTEXITCODE -ne 0) {
            throw "Could not update permissions in $ContainerName."
        }
    }
}

for ($Attempt = 0; $Attempt -lt 30; $Attempt++) {
    $InitRunning = & docker container inspect -f '{{.State.Running}}' minio-init 2>$null
    if ($LASTEXITCODE -ne 0 -or $InitRunning -ne "true") {
        break
    }
    Start-Sleep -Seconds 1
}

$InitExitCode = & docker container inspect -f '{{.State.ExitCode}}' minio-init 2>$null
if ($LASTEXITCODE -eq 0 -and $InitExitCode -ne "0") {
    & docker logs minio-init
    throw "MinIO bucket initialization failed with exit code $InitExitCode."
}

$RequiredContainers = @(
    "mysql_db", "phpmyadmin", "laravel_app1", "laravel_app2", "laravel_queue",
    "laravel_nginx", "laravel_nextjs", "fastapi-app", "minio", "frontend-ui5"
)
foreach ($ContainerName in $RequiredContainers) {
    $IsRunning = & docker container inspect -f '{{.State.Running}}' $ContainerName 2>$null
    if ($LASTEXITCODE -ne 0 -or $IsRunning -ne "true") {
        & docker logs --tail 50 $ContainerName
        throw "Required container $ContainerName is not running."
    }
}

Write-Host "All services started successfully."
