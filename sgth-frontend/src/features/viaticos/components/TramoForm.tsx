"use client";

import { Alert, Grid, Select, Stack, Text } from "@mantine/core";
import { DateTimePicker } from "@mantine/dates";
import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notificar } from "@/components/ui";
import { useContainedInput } from "@/hooks/useContainedInput";
import { formatFechaHora, fromDateTimeValue } from "@/lib/fecha";
import { useProvincias } from "@/features/expediente/hooks/useProvincias";
import { useCantones } from "@/features/expediente/hooks/useCantones";
import { useTiposTransporte, useEmpresasPorTipo } from "../hooks/useViaticos";
import { viaticoService } from "../services/viaticoService";
import { PAISES_OPTIONS } from "../constants/viatico.constants";
import { tramoSchema, type TramoFormData } from "../schemas/viatico.schema";
import { TramoLugarSelect } from "./TramoLugarSelect";
import { TramoTipoSelector } from "./TramoTipoSelector";
import type { CatalogoTransporte, EmpresaTransporte, Viatico } from "@/types/api";

/** El `id` del formulario: el botón de enviar vive en el pie del modal. */
export const TRAMO_FORM_ID = "tramo-form";
/** Para que el pie del modal sepa que se está guardando. */
export const CREAR_TRAMO = ["crear-tramo"];

interface Props {
  viaticoId: number;
  viatico?: Viatico | null;
  tramosExistentes?: number;
  onSuccess: () => void;
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
  empresa_transporte_id: 0,
  datetime_salida: "",
  datetime_llegada: "",
};

/*
| Un tramo nuevo del itinerario: de dónde a dónde, en qué y cuándo.
|
| Sin botones propios: los pone el pie del modal, por el `id` del formulario.
| Antes el formulario traía su Cancelar y su Agregar debajo de los campos, y
| con un error de validación quedaban fuera de la vista.
*/
export function TramoForm({ viaticoId, viatico, tramosExistentes, onSuccess }: Props) {
  const contained = useContainedInput();
  const qc = useQueryClient();

  const { control, handleSubmit, setValue, setError, formState: { errors } } =
    useForm<TramoFormData>({ resolver: zodResolver(tramoSchema), defaultValues: VACIO });

  const [origenTipo, destinoTipo, catalogoId, origenProv, destinoProv, salida, llegada, tipoTramo] =
    useWatch({
      control,
      name: [
        "origen_tipo", "destino_tipo", "catalogo_transporte_id", "origen_provincia_id",
        "destino_provincia_id", "datetime_salida", "datetime_llegada", "tipo_tramo",
      ],
    });

  const esPrimerTramo = (tramosExistentes ?? 0) === 0;
  const tipoEfectivo = esPrimerTramo ? "ida" : tipoTramo;

  const { data: tipos = [] } = useTiposTransporte();
  const { data: provincias = [] } = useProvincias();
  const { data: empresas = [] } = useEmpresasPorTipo(catalogoId || null);
  const { data: cantonesOrigen = [] } = useCantones(origenProv ?? null);
  const { data: cantonesDestino = [] } = useCantones(destinoProv ?? null);

  const provinciaOptions = opciones(provincias as Opcion[]);
  const empresaOptions = opciones(empresas as EmpresaTransporte[]);

  // Solo el primer tramo tiene que salir con el viático; ninguno puede llegar
  // después del regreso.
  const salidaDistinta =
    esPrimerTramo && !!viatico?.datetime_salida && !!salida &&
    new Date(viatico.datetime_salida as string).getTime() !== new Date(salida).getTime();
  const llegaTarde =
    !!viatico?.datetime_llegada && !!llegada &&
    new Date(llegada).getTime() > new Date(viatico.datetime_llegada as string).getTime();

  const crear = useMutation({
    mutationKey: CREAR_TRAMO,
    mutationFn: (data: Parameters<typeof viaticoService.tramos.crear>[1]) =>
      viaticoService.tramos.crear(viaticoId, data),
    onSuccess: () => {
      notificar.exito("Tramo agregado", "El tramo quedó en el itinerario.");
      qc.invalidateQueries({ queryKey: ["tramos", viaticoId] });
      qc.invalidateQueries({ queryKey: ["viatico", viaticoId] });
      onSuccess();
    },
    onError: notificar.alFallar("No se pudo agregar el tramo"),
  });

  const onSubmit = (values: TramoFormData) => {
    if (!tipoEfectivo) {
      setError("tipo_tramo", { message: "Elija qué es este tramo en el viaje" });
      return;
    }
    const { catalogo_transporte_id: _catalogo, ...resto } = values;
    crear.mutate({ ...resto, tipo_tramo: tipoEfectivo });
  };

  const limpiarLugar = (prefijo: "origen" | "destino") => () => {
    setValue(`${prefijo}_provincia_id`, null);
    setValue(`${prefijo}_canton_id`, null);
    setValue(`${prefijo}_pais`, null);
    setValue(`${prefijo}_ciudad`, "");
  };

  const fechaHora = (name: "datetime_salida" | "datetime_llegada", label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DateTimePicker
          label={label}
          valueFormat="DD/MM/YYYY HH:mm"
          {...contained}
          value={field.value ? new Date(field.value) : null}
          onChange={(v) => field.onChange(fromDateTimeValue(v))}
          error={errors[name]?.message}
        />
      )}
    />
  );

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
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="catalogo_transporte_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo de transporte"
                    data={opciones(tipos as CatalogoTransporte[])}
                    searchable
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => {
                      field.onChange(v ? Number(v) : 0);
                      setValue("empresa_transporte_id", 0);
                    }}
                    error={errors.catalogo_transporte_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="empresa_transporte_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Empresa"
                    data={empresaOptions}
                    searchable
                    disabled={empresaOptions.length === 0}
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => field.onChange(v ? Number(v) : 0)}
                    error={errors.empresa_transporte_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>{fechaHora("datetime_salida", "Salida")}</Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>{fechaHora("datetime_llegada", "Llegada")}</Grid.Col>
          </Grid>

          {salidaDistinta && (
            <Alert color="amber" variant="light" p="xs">
              El primer tramo sale con el viático: el{" "}
              <strong>{formatFechaHora(viatico?.datetime_salida as string)}</strong>.
            </Alert>
          )}
          {llegaTarde && (
            <Alert color="red" variant="light" p="xs">
              La llegada no puede pasar del regreso del viático:{" "}
              <strong>{formatFechaHora(viatico?.datetime_llegada as string)}</strong>.
            </Alert>
          )}
        </Stack>

        <Stack gap="xs">
          <Text size="sm" fw={600}>Qué es este tramo</Text>
          <TramoTipoSelector control={control} errors={errors} esPrimerTramo={esPrimerTramo} />
        </Stack>
      </Stack>
    </form>
  );
}
