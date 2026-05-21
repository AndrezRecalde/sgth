echo "1. Estudios"
Get-ChildItem -Path "app\Models" -Recurse -Filter "*.php" | Where-Object { $_.Name -match "Estudio|Capacitacion|Formacion|Titulo" } | Select-Object FullName
Get-ChildItem -Path "database\migrations" -Recurse -Filter "*.php" | Where-Object { $_.Name -match "estudio|capacitacion|formacion|titulo" } | Select-Object FullName

echo "2. Cargas"
Get-ChildItem -Path "app\Models" -Recurse -Filter "*.php" | Where-Object { $_.Name -match "Carga|Familiar|Dependiente" } | Select-Object FullName
Get-ChildItem -Path "database\migrations" -Recurse -Filter "*.php" | Where-Object { $_.Name -match "carga|familiar|dependiente" } | Select-Object FullName

echo "3. Declaraciones"
Get-ChildItem -Path "app\Models" -Recurse -Filter "*.php" | Where-Object { $_.Name -match "Declaracion|Juramentada" } | Select-Object FullName
Get-ChildItem -Path "database\migrations" -Recurse -Filter "*.php" | Where-Object { $_.Name -match "declaracion|juramentada" } | Select-Object FullName

echo "6. Modelos Expediente"
Get-ChildItem -Path "app\Models\Expediente" -Filter "*.php" | Select-Object Name
