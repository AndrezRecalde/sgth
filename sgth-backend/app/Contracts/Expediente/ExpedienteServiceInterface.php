<?php

namespace App\Contracts\Expediente;

use App\Models\Expediente\DocumentoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Http\UploadedFile;

interface ExpedienteServiceInterface
{
    public function crearServidor(array $datos): Servidor;

    public function crearServidorBasico(array $datos): Servidor;

    public function actualizarServidor(int $id, array $datos): Servidor;

    public function subirDocumento(int $servidorId, array $datos, UploadedFile $archivo): DocumentoServidor;

    public function obtenerExpedienteCompleto(int $servidorId): Servidor;

    public function listarServidores(array $filtros): mixed;

    public function exportarServidores(array $filtros): \Illuminate\Support\Collection;
}
