<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Services\Estructura\OrganigramaPdfService;
use Illuminate\Http\Response;

/**
 * Descarga del organigrama en PDF. Abierta, igual que su consulta: contiene
 * únicamente la estructura de unidades y subprocesos.
 */
final class OrganigramaPdfController extends Controller
{
    public function __construct(
        private readonly OrganigramaPdfService $service
    ) {}

    public function __invoke(): Response
    {
        $result = $this->service->generarContent();

        return response($result['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$result['filename'].'"',
        ]);
    }
}
