npm run build > build_out.txt 2>&1
$buildOutput = Get-Content build_out.txt -Raw
Write-Output $buildOutput
Remove-Item build_out.txt

if ($LASTEXITCODE -eq 0) {
    git add .
    git commit -m "fix(welcome): usar Topbar real en bienvenida y actualizar perfil con roles"
    git push origin main
} else {
    Write-Output "Build failed. Git commit skipped."
}
