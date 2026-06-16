npm run build > build_out.txt 2>&1
$buildOutput = Get-Content build_out.txt -Raw
Write-Output $buildOutput
Remove-Item build_out.txt

if ($LASTEXITCODE -eq 0) {
    git add .
    git commit -m "fix(welcome): corregir error de tipado al usar Grid cambiándolo por SimpleGrid"
    git push origin main
} else {
    Write-Output "Build failed. Git commit skipped."
}
