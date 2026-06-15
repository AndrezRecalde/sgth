npm run build > build_out.txt 2>&1
$buildOutput = Get-Content build_out.txt -Raw
Write-Output $buildOutput
Remove-Item build_out.txt

if ($LASTEXITCODE -eq 0) {
    git add .
    git commit -m "feat(welcome): pantalla de bienvenida sin sidebar`n`n- (welcome)/bienvenida: selector de subsistemas por rol`n- page.tsx raíz: redirige a /bienvenida`n- useLogin: post-login va a /bienvenida`n- useCambiarPassword: post-cambio va a /bienvenida`n- Topbar: logo lleva a /bienvenida"
    git push origin main
} else {
    Write-Output "Build failed. Git commit skipped."
}
