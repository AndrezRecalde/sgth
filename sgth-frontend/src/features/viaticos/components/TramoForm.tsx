"use client";

import { Stack, Text } from "@mantine/core";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notificar } from "@/components/ui";
import { useProvincias } from "@/features/expediente/hooks/useProvincias";
import { useCantones } from "@/features/expediente/hooks/useCantones";
import { viaticoService } from "../services/viaticoService";
import { PAISES_OPTIONS } from "../constants/viatico.constants";
import { tramoSchema, type TramoFormData } from "../schemas/viatico.schema";
import { TramoLugarSelect } from "./TramoLugarSelect";
import { TramoTipoSelector } from "./TramoTipoSelector";
import { TramoTransporteCampos } from "./TramoTransporteCampos";
import { erroresDeCampo } from "@/lib/erroresDeCampo";
import { fromDateTimeValue } from "@/lib/fecha";
import type { TramoViatico, Viatico } from "@/types/api";

/** El `id` del formulario: el botón de enviar vive en el pie del modal. */
export const TRAMO_FORM_ID = "tramo-form";
/** Para que el pie del modal sepa que se está guardando. */
export const CREAR_TRAMO = ["crear-tramo"];

interface Props {
  viaticoId: number;
  viatico?: Viatico | null;
  tramosExistentes?: number;
  /** Con esto se corrige ese tramo; sin él, se agrega uno nuevo. */
  tramo?: TramoViatico | null;
  onSuccess: () => void;
}

/** Los valores de un tramo guardado, para corregirlo. */
function desdeTramo(t: TramoViatico): TramoFormData {
  return {
    tipo_tramo: t.tipo_tramo ?? null,
    origen_tipo: t.origen_tipo,
    origen_provincia_id: t.origen_provincia_id ?? null,
    origen_canton_id: t.origen_canton_id ?? null,
    origen_pais: t.origen_pais ?? null,
    origen_ciudad: t.origen_ciudad,
    destino_tipo: t.destino_tipo,
    destino_provincia_id: t.destino_provincia_id ?? null,
    destino_canton_id: t.destino_canton_id ?? null,
    destino_pais: t.destino_pais ?? null,
    destino_ciudad: t.destino_ciudad,
    catalogo_transporte_id: t.catalogo_transporte_id,
    empresa_transporte_id: t.empresa_transporte_id ?? null,
    // Un tramo con empresa es de un tipo que las tiene.
    con_empresas: t.empresa_transporte_id != null,
    datetime_salida: fromDateTimeValue(t.datetime_salida),
    datetime_llegada: fromDateTimeValue(t.datetime_llegada),
  };
}

type Opcion = { id: number; nombre?: string | null };
const opciones = (lista: Opcion[]) =>
  lista.map((o) => ({ value: String(o.id), label: o.nombre ?? "" }));

const VACIO: TramoFormData = {
  tipo_tramo: null,
  origen_tipo: "nacional",
  origen_provincia_id: null,
  origen_canton_id: null,
  origen_pais: null,
  origen_ciudad: "",
  destino_tipo: "nacional",
  destino_provincia_id: null,
  destino_canton_id: null,
  destino_pais: null,
  destino_ciudad: "",
  catalogo_transporte_id: 0,
  empresa_transporte_id: null,
  con_empresas: false,
  datetime_salida: "",
  datetime_llegada: "",
};

/*
| Un tramo del itinerario, nuevo o para corregirlo: de dónde a dónde, en qué
| y cuándo.
|
| Sin botones propios: los pone el pie del modal, por el `id` del formulario.
| Antes el formulario traía su Cancelar y su Agregar debajo de los campos, y
| con un error de validación quedaban fuera de la vista.
*/
export function TramoForm({ viaticoId, viatico, tramosExistentes, tramo, onSuccess }: Props) {
  const qc = useQueryClient();

  const { control, handleSubmit, setValue, setError, formState: { errors } } =
    useForm<TramoFormData>({
      resolver: zodResolver(tramoSchema),
      defaultValues: tramo ? desdeTramo(tramo) : VACIO,
    });

  const [origenTipo, destinoTipo, origenProv, destinoProv, tipoTramo] = useWatch({
    control,
    name: ["origen_tipo", "destino_tipo", "origen_provincia_id", "destino_provincia_id", "tipo_tramo"],
  });

  const esPrimerTramo = tramo ? tramo.orden === 1 : (tramosExistentes ?? 0) === 0;
  const tipoEfectivo = esPrimerTramo ? "ida" : tipoTramo;

  const { data: provincias = [] } = useProvincias();
  const { data: cantonesOrigen = [] } = useCantones(origenProv ?? null);
  const { data: cantonesDestino = [] } = useCantones(destinoProv ?? null);

  const provinciaOptions = opciones(provincias as Opcion[]);

  const guardar = useMutation({
    mutationKey: CREAR_TRAMO,
    mutationFn: (data: Parameters<typeof viaticoService.tramos.crear>[1]) =>
      tramo
        ? viaticoService.tramos.actualizar(viaticoId, tramo.id, data)
        : viaticoService.tramos.crear(viaticoId, data),
    onSuccess: () => {
      notificar.exito(
        tramo ? "Tramo corregido" : "Tramo agregado",
        tramo ? "Los cambios quedaron en el itinerario." : "El tramo quedó en el itinerario.",
      );
      qc.invalidateQueries({ queryKey: ["tramos", viaticoId] });
      // La ficha se consulta por código, no por id: sin la clave corta no se
      // refrescaba, y el aviso del itinerario seguía igual.
      qc.invalidateQueries({ queryKey: ["viatico"] });
      onSuccess();
    },
    // Las reglas del itinerario (fechas, cruces, regreso) llegan por campo.
    onError: (error) => {
      const campos = erroresDeCampo(error);
      if (!campos) {
        notificar.alFallar(tramo ? "No se pudo corregir el tramo" : "No se pudo agregar el tramo")(error);
        return;
      }
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in VACIO) setError(campo as keyof TramoFormData, { message: mensaje });
      }
    },
  });

  const onSubmit = (values: TramoFormData) => {
    if (!tipoEfectivo) {
      setError("tipo_tramo", { message: "Elija qué es este tramo en el viaje" });
      return;
    }
    const { con_empresas: _conEmpresas, ...resto } = values;
    guardar.mutate({ ...resto, tipo_tramo: tipoEfectivo });
  };

  const limpiarLugar = (prefijo: "origen" | "destino") => () => {
    setValue(`${prefijo}_provincia_id`, null);
    setValue(`${prefijo}_canton_id`, null);
    setValue(`${prefijo}_pais`, null);
    setValue(`${prefijo}_ciudad`, "");
  };

  return (
    <form id={TRAMO_FORM_ID} onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="md">
        {(["origen", "destino"] as const).map((prefijo) => (
          <Stack key={prefijo} gap="xs">
            <Text size="sm" fw={600}>{prefijo === "origen" ? "Origen" : "Destino"}</Text>
            <TramoLugarSelect
              prefijo={prefijo}
              label={prefijo === "origen" ? "Origen" : "Destino"}
              control={control}
              errors={errors}
              tipo={(prefijo === "origen" ? origenTipo : destinoTipo) as "nacional" | "internacional"}
              provinciaOptions={provinciaOptions}
              cantonOptions={opciones((prefijo === "origen" ? cantonesOrigen : cantonesDestino) as Opcion[])}
              paises={PAISES_OPTIONS}
              onTipoChange={limpiarLugar(prefijo)}
              onProvinciaChange={() => setValue(`${prefijo}_canton_id`, null)}
              setValue={setValue}
            />
          </Stack>
        ))}

        <Stack gap="xs">
          <Text size="sm" fw={600}>Transporte y horario</Text>
          <TramoTransporteCampos
            control={control}
            errors={errors}
            setValue={setValue}
            viatico={viatico}
            esPrimerTramo={esPrimerTramo}
          />
        </Stack>

        <Stack gap="xs">
          <Text size="sm" fw={600}>Qué es este tramo</Text>
          <TramoTipoSelector control={control} errors={errors} esPrimerTramo={esPrimerTramo} />
        </Stack>
      </Stack>
    </form>
  );
}
