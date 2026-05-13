<?php
namespace App\Enums\Actividades;
enum CategoriaActividadEnum: string {
    case REUNION = 'reunion';
    case VISITA_CAMPO = 'visita_campo';
    case ELABORACION_DOCUMENTOS = 'elaboracion_documentos';
    case COORDINACION = 'coordinacion';
    case CAPACITACION = 'capacitacion';
    case ATENCION_CIUDADANA = 'atencion_ciudadana';
    case OTRO = 'otro';
}