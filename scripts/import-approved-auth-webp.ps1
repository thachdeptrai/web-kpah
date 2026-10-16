# Imports the six approved WebP files from kpah-auth-concept-webp.zip
# and commits/pushes them to the EXISTING main branch. No force-push.
param([Parameter(Mandatory=$true)][string]$Archive)
$ErrorActionPreference = "Stop"
$Repo = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Archive = (Resolve-Path $Archive).Path
Push-Location $Repo
try {
    if ((git branch --show-current).Trim() -ne "main") { throw "Chuyển về nhánh main trước." }
    git fetch origin main
    if ($LASTEXITCODE -ne 0) { throw "Không thể fetch origin/main." }
    $dirty = git status --porcelain
    if ($dirty) { throw "Git worktree đang có thay đổi. Commit/stash trước khi import." }
    git pull --ff-only origin main
    if ($LASTEXITCODE -ne 0) { throw "Không thể cập nhật main bằng fast-forward." }

    $tmp = Join-Path ([IO.Path]::GetTempPath()) ("kpah-auth-" + [Guid]::NewGuid().ToString("N"))
    New-Item -Type Directory -Path $tmp | Out-Null
    try {
        Expand-Archive -LiteralPath $Archive -DestinationPath $tmp -Force
        $names = @("login","register","recovery")
        foreach ($name in $names) {
            foreach ($suffix in @("concept","art")) {
                $f = "auth-$name-$suffix.webp"
                $source = Join-Path $tmp "assets/img/$f"
                if (!(Test-Path -LiteralPath $source)) { throw "Thiếu ảnh: $f" }
                $bytes = [IO.File]::ReadAllBytes($source)
                if ($bytes.Length -lt 1000 -or [Text.Encoding]::ASCII.GetString($bytes,0,4) -ne "RIFF" -or [Text.Encoding]::ASCII.GetString($bytes,8,4) -ne "WEBP") { throw "Ảnh WebP lỗi: $f" }
                Copy-Item -LiteralPath $source -Destination (Join-Path $Repo "assets/img/$f") -Force
                git add -- "assets/img/$f"
                if ($LASTEXITCODE -ne 0) { throw "Không thể git add $f" }
            }
        }
        git diff --cached --quiet
        if ($LASTEXITCODE -eq 0) { Write-Output "WebP đã có sẵn trên main."; exit 0 }
        git commit -m "feat(auth): import six approved concept WebP images"
        if ($LASTEXITCODE -ne 0) { throw "Không thể commit WebP." }
        git push origin main
        if ($LASTEXITCODE -ne 0) { throw "Không thể push lên origin/main." }
        Write-Output "Đã push 6 ảnh WebP lên main."
    } finally { Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue }
} finally { Pop-Location }
