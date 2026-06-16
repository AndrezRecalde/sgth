npm run build > build_out.txt 2>&1
$buildOutput = Get-Content build_out.txt -Raw
Write-Output $buildOutput
Remove-Item build_out.txt

if ($LASTEXITCODE -eq 0) {
    git add .
    git commit -m "feat(layout): corregir TS error en Grid y completar Dashboard Dividido"
    git push origin main
} else {
    Write-Output "Build failed. Git commit skipped."
}
