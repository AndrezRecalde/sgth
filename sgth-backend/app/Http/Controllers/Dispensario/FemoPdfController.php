<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Services\Dispensario\PdfFemoService;
use Illuminate\Http\Response;

final class FemoPdfController extends Controller
{
    public function __construct(
        private readonly PdfFemoService $service
    ) {}

    public function generar(int $id): Response
    {
        $result = $this->service->generarContent($id);

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$result['filename'].'"',
        ]);
    }
}
