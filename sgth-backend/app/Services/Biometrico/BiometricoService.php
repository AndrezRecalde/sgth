<?php

namespace App\Services\Biometrico;

use App\Contracts\Biometrico\BiometricoServiceInterface;
use App\Models\Asistencia\Marcacion;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BiometricoService implements BiometricoServiceInterface
{
    /**
     * Instancia de conexión PDO al SQL Server externo.
     */
    private ?\PDO $conexionBiometrico = null;

    public function __construct()
    {
        // En producción las credenciales vendrían de config('database.connections.biometrico')
        $host = env('BIOMETRICO_HOST', '192.168.1.100');
        $db   = env('BIOMETRICO_DATABASE', 'ZKTecoDB');
        $user = env('BIOMETRICO_USERNAME', 'sa');
        $pass = env('BIOMETRICO_PASSWORD', 'secret');

        try {
            // Se usa el driver sqlsrv/odbc para conectarse al servidor de marcaciones
            $dsn = "sqlsrv:Server=$host;Database=$db";
            $this->conexionBiometrico = new \PDO($dsn, $user, $pass);
            $this->conexionBiometrico->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            Log::error("Error al conectar con la base de datos del Biométrico: " . $e->getMessage());
            // No bloqueamos la instanciación para no tumbar la app entera, pero se reportará al intentar importar.
        }
    }

    /**
     * @inheritDoc
     */
    public function importarMarcaciones(Carbon $desde, Carbon $hasta): int
    {
        if (!$this->conexionBiometrico) {
            throw new \Exception("No hay conexión disponible con el sistema biométrico SQL Server.");
        }

        // 1. Obtener servidores estrictamente con código asignado
        $servidores = Servidor::whereNotNull('codigo_marcacion')
            ->where('estado', true)
            ->get();

        if ($servidores->isEmpty()) {
            return 0;
        }

        $importadas = 0;

        // 2. Preparar el Stored Procedure
        // REGLA CRÍTICA: SGTH NUNCA ESCRIBE EN LA BD BIOMÉTRICO (SOLO LECTURA)
        $stmt = $this->conexionBiometrico->prepare(
            'EXEC sp_ObtenerMarcaciones @CodigoEmpleado = ?, @FechaDesde = ?, @FechaHasta = ?'
        );

        foreach ($servidores as $servidor) {
            try {
                // 3. Ejecutar SP
                $stmt->execute([
                    $servidor->codigo_marcacion,
                    $desde->format('Y-m-d'),
                    $hasta->format('Y-m-d')
                ]);

                $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // 4. Registrar en la base de datos local (PostgreSQL)
                foreach ($resultados as $row) {
                    // Idempotencia: previene que la misma marcación se duplique si el cron corre de nuevo
                    $existe = Marcacion::where('servidor_id', $servidor->id)
                        ->where('fecha_hora', $row['FechaHora'])
                        ->exists();

                    if (!$existe) {
                        Marcacion::create([
                            'servidor_id'    => $servidor->id,
                            'fecha_hora'     => $row['FechaHora'],
                            // Mapeamos lo que devuelva el SP a los enums permitidos
                            'tipo'           => strtolower($row['TipoRegistro']) === 'entrada' ? 'entrada' : 'salida',
                            'dispositivo_id' => $row['IdDispositivo'] ?? 'Generico',
                        ]);
                        $importadas++;
                    }
                }
            } catch (\Exception $e) {
                // Registra la falla específica pero permite que el ciclo continúe con los otros empleados
                Log::error("Error importando marcaciones del servidor [{$servidor->codigo_marcacion}]: " . $e->getMessage());
            }
        }

        return $importadas;
    }
}
