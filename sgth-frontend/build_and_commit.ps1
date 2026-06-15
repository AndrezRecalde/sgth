npm run build > build_out.txt 2>&1
$buildOutput = Get-Content build_out.txt -Raw
Write-Output $buildOutput
Remove-Item build_out.txt

if ($LASTEXITCODE -eq 0) {
    git add .
    git commit -m "feat(layout): Sprint I-01 layout multi-subsistema`n`n- routes.ts: rutas separadas por subsistema sgth/salud/portal`n- nav.ts: NAV_SGTH, NAV_SALUD, NAV_PORTAL con helpers`n- (sgth)/(salud)/(portal): route groups independientes`n- SubsistemaHeader: selector de subsistemas en header`n- Topbar: integra SubsistemaHeader en el centro`n- Sidebar: detecta subsistema activo por pathname`n- Layouts independientes por subsistema`n- Redirect raíz según rol del usuario"
    git push origin main
} else {
    Write-Output "Build failed. Git commit skipped."
}
