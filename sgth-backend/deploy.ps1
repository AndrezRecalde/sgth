Get-ChildItem app -Recurse -Filter "*.php" | Select-String -Pattern "\.beneficiario'|'beneficiario\." | Select-Object -Unique Path | Format-Table Path

Write-Host "--- GIT ---"
git add .
git commit -m "fix(dispensario): corregir ultima referencia residual a relacion beneficiario

HistoriaClinicaService::registrarConsulta() cargaba
historiaClinica.beneficiario en el eager load, pero la
relacion correcta tras la migracion es cargaFamiliar."
git push
