npm run build > build_out.txt 2>&1
$buildOutput = Get-Content build_out.txt -Raw
Write-Output $buildOutput
Remove-Item build_out.txt

if ($LASTEXITCODE -eq 0) {
    git add .
    git commit -m "feat(viaticos): mover viáticos de SGTH a Portal Servidor`n`n- routes.ts: VIATICOS y VIATICO_DETALLE movidos a PORTAL`n- nav.ts: viáticos en grupo Solicitudes del Portal`n- (portal)/portal/viaticos: páginas movidas desde (sgth)`n- ViaticoView: router.push usa ROUTES.PORTAL.VIATICO_DETALLE"
    git push origin main
} else {
    Write-Output "Build failed. Git commit skipped."
}
