# Gera a pasta public_html pronta para upload na Hostinger.
# Fonte: projeto local em XAMPP (versão completa do site).

$ErrorActionPreference = 'Stop'

$Source = $PSScriptRoot
$Target = Join-Path $PSScriptRoot 'public_html'

if (-not (Test-Path $Source)) {
    throw "Fonte não encontrada: $Source"
}

if (Test-Path $Target) {
    Remove-Item $Target -Recurse -Force
}

New-Item -ItemType Directory -Path $Target | Out-Null

$excludeDirs = @(
    '__nao_enviar_servidor',
    'scratch',
    'cloudflare',
    'canvases',
    '.vscode',
    '.wrangler',
    'vendor',
    'PHPMailer',
    'public_html'
)

$excludeFiles = @(
    '.gitignore',
    '.env',
    'config_secrets.php',
    'favicon.svg',
    'cloudflare_worker.js',
    'run_precise_location_migration.php',
    'run_verify_master_admin.php',
    'build-public_html.ps1'
)

Get-ChildItem -Path $Source -Force | ForEach-Object {
    if ($_.PSIsContainer) {
        if ($excludeDirs -contains $_.Name) { return }
        Copy-Item -Path $_.FullName -Destination (Join-Path $Target $_.Name) -Recurse -Force
        return
    }

    if ($excludeFiles -contains $_.Name) { return }
    if ($_.Extension -in '.md', '.log', '.sql') { return }

    Copy-Item -Path $_.FullName -Destination (Join-Path $Target $_.Name) -Force
}

# Remove markdown dentro de subpastas copiadas
Get-ChildItem -Path $Target -Recurse -Include '*.md', '*.markdown', '*.log', '*.sql' -File -ErrorAction SilentlyContinue |
    Remove-Item -Force

# uploads: estrutura vazia gravável (sem cache local)
$uploadDirs = @('uploads', 'uploads/bio', 'uploads/logos')
foreach ($dir in $uploadDirs) {
    $full = Join-Path $Target $dir
    New-Item -ItemType Directory -Path $full -Force | Out-Null
}
$uploadsHtaccess = Join-Path $Source 'uploads\.htaccess'
if (Test-Path $uploadsHtaccess) {
    Copy-Item $uploadsHtaccess (Join-Path $Target 'uploads\.htaccess') -Force
}
if (Test-Path (Join-Path $Source 'uploads\.gitkeep')) {
    Copy-Item (Join-Path $Source 'uploads\.gitkeep') (Join-Path $Target 'uploads\.gitkeep') -Force
}
Get-ChildItem (Join-Path $Target 'uploads') -Recurse -File |
    Where-Object { $_.Name -notin '.htaccess', '.gitkeep' } |
    Remove-Item -Force

# Garante assets de marca
if (Test-Path (Join-Path $PSScriptRoot 'logo.png')) {
    Copy-Item (Join-Path $PSScriptRoot 'logo.png') (Join-Path $Target 'logo.png') -Force
}
if (Test-Path (Join-Path $PSScriptRoot 'favicon.png')) {
    Copy-Item (Join-Path $PSScriptRoot 'favicon.png') (Join-Path $Target 'favicon.png') -Force
}

# Preserva config de produção se existir na raiz do projeto
$secretsFile = Join-Path $PSScriptRoot 'config_secrets.php'
if (Test-Path $secretsFile) {
    Copy-Item $secretsFile (Join-Path $Target 'config_secrets.php') -Force
}

$deployReadme = Join-Path $PSScriptRoot 'LEIA-ME-DEPLOY.txt'
if (Test-Path $deployReadme) {
    Copy-Item $deployReadme (Join-Path $Target 'LEIA-ME-DEPLOY.txt') -Force
}

$fileCount = (Get-ChildItem -Path $Target -Recurse -File).Count
Write-Output "public_html gerado em: $Target"
Write-Output "Arquivos: $fileCount"
