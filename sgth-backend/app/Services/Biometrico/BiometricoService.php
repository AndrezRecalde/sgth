<?php

namespace App\Services\Biometrico;

use App\Contracts\Biometrico\BiometricoServiceInterface;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

final class BiometricoService implements BiometricoServiceInterface
{
    private ?PDO $conexionBiometrico = null;

    public function __construct()
    {
        try {
            // Se leen las credenciales externas desde el .env
            $host = env('BIOMETRICO_DB_HOST', 'localhost');
            $db   = env('BIOMETRICO_DB_DATABASE', 'biometrico');
            $user = env('BIOMETRICO_DB_USERNAME', 'sa');
            $pass = env('BIOMETRICO_DB_PASSWORD', '');

            // Conexión nativa PDO (ejemplo usando driver sqlsrv). NUNCA usaremos permisos de escritura aquí.
            $this->conexionBiometrico = new PDO("sqlsrv:Server=$host;Database=$db", $user, $pass);
            $this->conexionBiometrico->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            Log::warning('No se pudo establecer la conexión PDO con el reloj biométrico: ' . $e->getMessage());
        }
    }

    public function importarMarcaciones(Carbon $desde, Carbon $hasta): int
    {
        if (!$this->conexionBiometrico) {
            Log::error('Se intentó importar marcaciones sin una conexión activa a SQL Server.');
            return 0;
        }

        // Obtener servidores activos que tienen asignado un código en el reloj biométrico
        $servidores = Servidor::whereNotNull('codigo_marcacion')
            ->where('estado', true)
            ->get();

        $registrosImportados = 0;

        foreach ($servidores as $servidor) {
            try {
                // Llamada estricta al Stored Procedure del sistema biométrico
                $stmt = $this->conexionBiometrico->prepare(
                    'EXEC sp_ObtenerMarcaciones @CodigoEmpleado = ?, @FechaDesde = ?, @FechaHasta = ?'
                );
                
                $stmt->execute([
                    $servidor->codigo_marcacion, 
                    $desde->format('Y-m-d'), 
                    $hasta->format('Y-m-d')
                ]);
                
                $marcaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // El guardado real en la tabla de BD nativa 'marcaciones' se implementará en el Módulo 04.
                // Por ahora realizamos el conteo de prueba y dejamos validada la comunicación.
                $registrosImportados += count($marcaciones);

            } catch (\Exception $e) {
                // Continuamos con el resto de servidores si uno falla
                Log::error("Fallo al obtener marcaciones del código {$servidor->codigo_marcacion} (Servidor ID: {$servidor->id}): " . $e->getMessage());
            }
        }

        return $registrosImportados;
    }
}
