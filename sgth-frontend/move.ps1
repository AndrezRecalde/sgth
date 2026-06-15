New-Item -ItemType Directory -Force "src\app\(sgth)", "src\app\(sgth)\sgth", "src\app\(salud)", "src\app\(salud)\salud", "src\app\(portal)", "src\app\(portal)\portal"

Copy-Item "src\app\(dashboard)\page.tsx" "src\app\(sgth)\sgth\page.tsx"

New-Item -ItemType Directory -Force "src\app\(sgth)\sgth\asistencia"
Copy-Item "src\app\(dashboard)\asistencia\page.tsx" "src\app\(sgth)\sgth\asistencia\page.tsx"
Copy-Item "src\app\(dashboard)\asistencia\AsistenciaView.tsx" "src\app\(sgth)\sgth\asistencia\AsistenciaView.tsx"

New-Item -ItemType Directory -Force "src\app\(sgth)\sgth\estructura"
Copy-Item "src\app\(dashboard)\estructura\page.tsx" "src\app\(sgth)\sgth\estructura\page.tsx"
Copy-Item "src\app\(dashboard)\estructura\EstructuraView.tsx" "src\app\(sgth)\sgth\estructura\EstructuraView.tsx"

New-Item -ItemType Directory -Force "src\app\(sgth)\sgth\expediente"
Copy-Item "src\app\(dashboard)\expediente\page.tsx" "src\app\(sgth)\sgth\expediente\page.tsx"
Copy-Item "src\app\(dashboard)\expediente\ExpedienteView.tsx" "src\app\(sgth)\sgth\expediente\ExpedienteView.tsx"

New-Item -ItemType Directory -Force "src\app\(sgth)\sgth\nomina"
Copy-Item "src\app\(dashboard)\nomina\page.tsx" "src\app\(sgth)\sgth\nomina\page.tsx"
Copy-Item "src\app\(dashboard)\nomina\NominaView.tsx" "src\app\(sgth)\sgth\nomina\NominaView.tsx"

New-Item -ItemType Directory -Force "src\app\(sgth)\sgth\usuarios"
Copy-Item "src\app\(dashboard)\usuarios\page.tsx" "src\app\(sgth)\sgth\usuarios\page.tsx"
Copy-Item "src\app\(dashboard)\usuarios\UsuariosView.tsx" "src\app\(sgth)\sgth\usuarios\UsuariosView.tsx"

New-Item -ItemType Directory -Force "src\app\(sgth)\sgth\viaticos", "src\app\(sgth)\sgth\viaticos\[codigo]"
Copy-Item "src\app\(dashboard)\viaticos\page.tsx" "src\app\(sgth)\sgth\viaticos\page.tsx"
Copy-Item "src\app\(dashboard)\viaticos\[codigo]\page.tsx" "src\app\(sgth)\sgth\viaticos\[codigo]\page.tsx"
