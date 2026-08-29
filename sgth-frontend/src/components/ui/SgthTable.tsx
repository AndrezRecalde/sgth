'use client'

import { DataTable, type DataTableProps } from 'mantine-datatable'
import classes from './SgthTable.module.css'

/**
 * Única tabla de datos del sistema. Ningún módulo usa `DataTable` directo ni
 * `<table>` nativo.
 *
 * Fija aquí lo que debe ser idéntico en las 40 y pico tablas del SGTH: bordes,
 * densidad, textos en español y comportamiento de la paginación. Cualquier
 * prop de `mantine-datatable` sigue disponible y sobrescribe estos valores.
 *
 * Paginación: SIEMPRE del lado del servidor, con `per_page` 15 por defecto.
 * Traer 3.000 servidores al navegador para paginar en memoria hace lenta la
 * pantalla y castiga a quien la abre desde una conexión de la Prefectura.
 */
export function SgthTable<T>(props: DataTableProps<T>) {
  return (
    <DataTable
      withTableBorder
      withColumnBorders
      borderRadius="lg"
      highlightOnHover
      verticalSpacing="sm"
      horizontalSpacing="md"
      minHeight={160}
      noRecordsText="No hay registros para mostrar"
      classNames={{ root: classes.root, header: classes.header }}
      {...props}
    />
  )
}

/**
 * Textos en español de la paginación.
 *
 * Van aparte y no dentro de `SgthTable` porque `mantine-datatable` tipa la
 * paginación como una unión discriminada: `paginationText` solo existe en la
 * variante paginada, y fijarlo dentro del envoltorio genérico impide a
 * TypeScript resolver la variante. La alternativa habría sido una aserción de tipo forzada,
 * que está prohibida.
 *
 *   <SgthTable
 *     {...PAGINACION_ES}
 *     records={data}
 *     totalRecords={total}
 *     recordsPerPage={15}
 *     page={page}
 *     onPageChange={setPage}
 *   />
 */
export const PAGINACION_ES = {
  recordsPerPageLabel: 'Registros por página',
  paginationText: ({
    from,
    to,
    totalRecords,
  }: {
    from: number
    to: number
    totalRecords: number
  }) => `${from} – ${to} de ${totalRecords}`,
}
