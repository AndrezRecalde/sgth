"use client";

import { useState } from "react";
import {
  Modal,
  Button,
  Group,
  Stack,
  Select,
  Textarea,
  TextInput,
  Grid,
  Text,
  Stepper,
  Alert,
  Divider,
} from "@mantine/core";
import { DatePickerInput, TimeInput } from "@mantine/dates";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  IconCheck,
  IconInfoCircle,
  IconFileDownload,
} from "@tabler/icons-react";
import React from "react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useAuth } from "@/hooks/useAuth";
import { useUnidades } from "@/features/estructura/hooks/useUnidades";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { usePermisoMutations } from "../hooks/usePermisoMutations";
import { useExportarPermiso } from "../hooks/useExportarPermiso";
import { DirigirATalentoHumano } from "./DirigirATalentoHumano";
import {
  esTipoRetroactivo,
  fromDate,
  fechaMasAntiguaAdmitida,
  permisoSchema,
  toDate,
  type PermisoFormData,
} from "./permiso.schema";
import type {
  UnidadConRelaciones,
  ServidorConRelaciones,
  PermisoServidor,
} from "@/types/api";

const TIPO_OPTIONS = [
  { value: "personal", label: "Personal (máx. 4 horas)" },
  { value: "oficial", label: "Oficial" },
  { value: "enfermedad", label: "Por enfermedad" },
  { value: "calamidad", label: "Calamidad doméstica" },
];

interface Props {
  opened: boolean;
  onClose: () => void;
}

export function PermisoModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { crear } = usePermisoMutations();

  // Talento Humano emite permisos a nombre de cualquier servidor; el resto,
  // solo el propio. Es la misma regla que aplica el backend
  // (`PermisoServidorPolicy::crear`): ofrecer aquí la lista de toda la
  // institución solo serviría para que el alta respondiera 403.
  const { usuario, hasPermiso } = useAuth();
  const emiteATodos = hasPermiso("registrar-permisos-servidores");
  const propio = usuario?.servidor ?? null;
  const unidadPropia = propio?.unidad_administrativa_id ?? null;
  const puedeRegistrar = emiteATodos || (propio !== null && unidadPropia !== null);

  const [paso, setPaso] = useState(0);
  const [permisoCreado, setPermisoCreado] = useState<PermisoServidor | null>(
    null,
  );
  const { exportar, exportandoId } = useExportarPermiso();
  const unidadInicial = emiteATodos ? null : unidadPropia;
  const [unidadSelId, setUnidadSelId] = useState<number | null>(unidadInicial);

  // Datos
  const { data: unidadesRaw } = useUnidades({ nivel: 2 });
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[];

  // Los servidores se piden ya filtrados por unidad.
  //
  // Antes se traían los primeros 200 y se filtraba en el navegador. Con más de
  // 200 servidores en la institución —que los hay— las unidades que caían
  // fuera de esa primera página aparecían vacías: el desplegable decía «Sin
  // servidores en esta unidad» y no había forma de registrarles un permiso.
  // Es el mismo patrón que ya usan MovimientoModal y SubrogacionModal.
  const { data: servidoresData } = useServidores(
    unidadSelId
      ? { unidad_administrativa_id: unidadSelId, per_page: 100 }
      : undefined,
  );
  const servidoresUnidad = unidadSelId
    ? ((servidoresData?.data ?? []) as ServidorConRelaciones[])
    : [];

  const unidadOptions = unidades.map((u) => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }));

  const servidorOptions = servidoresUnidad.map((s) => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre].filter(Boolean).join(" ")} — ${s.cedula}`,
  }));

  // Jefe: servidores de la misma unidad con es_jefe = true
  const jefeOptions = servidoresUnidad
    .filter((s) => (s.puesto as { es_jefe?: boolean } | null)?.es_jefe === true)
    .map((s) => ({
      value: String(s.id),
      label: `${[s.apellido, s.nombre].filter(Boolean).join(" ")}`,
    }));

  const {
    control,
    handleSubmit,
    reset,
    register,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<PermisoFormData>({
    resolver: zodResolver(permisoSchema),
    defaultValues: {
      unidad_administrativa_id: unidadInicial ?? undefined,
      servidor_id: emiteATodos ? undefined : propio?.id,
      jefe_id: null,
      dirigido_a_talento_humano: false,
      tipo: "personal",
      fecha: "",
      hora_inicio: "08:00",
      hora_fin: "12:00",
      observacion: "",
    },
  });

  const tipoWatch = useWatch({ control, name: "tipo" });
  const dirigidoATh = useWatch({ control, name: "dirigido_a_talento_humano" });
  const servidorWatch = useWatch({ control, name: "servidor_id" });
  const esRetroactivo = esTipoRetroactivo(tipoWatch);

  const handleClose = () => {
    reset();
    setUnidadSelId(unidadInicial);
    setPaso(0);
    setPermisoCreado(null);
    onClose();
  };

  const onSubmit = async (values: PermisoFormData) => {
    try {
      const result = await crear.mutateAsync({
        unidad_administrativa_id: values.unidad_administrativa_id,
        servidor_id:              values.servidor_id,
        // Con la opción activa el jefe lo resuelve el backend: mandar además un
        // `jefe_id` sería ofrecer un dato que se va a ignorar.
        jefe_id:                  values.dirigido_a_talento_humano
          ? null
          : (values.jefe_id ?? null),
        dirigido_a_talento_humano: values.dirigido_a_talento_humano,
        tipo:                     values.tipo,
        fecha:                    values.fecha,
        hora_inicio:              values.hora_inicio,
        hora_fin:                 values.hora_fin,
        observacion:              values.observacion ?? null,
      });
      setPermisoCreado(result ?? null);
      setPaso(1);
    } catch {
      // El hook de mutación ya notifica el error; el formulario sigue abierto
      // para corregirlo. Sin esto, el rechazo quedaba sin atrapar.
    }
  };

  const handleExportar = () => {
    if (!permisoCreado) return;

    exportar(Number(permisoCreado.id), permisoCreado.folio);
  };

  const nombrePropio = propio
    ? [
        [propio.apellido, propio.nombre].filter(Boolean).join(" "),
        propio.cedula,
      ]
        .filter(Boolean)
        .join(" — ")
    : "";

  // El mismo selector para los dos casos: el jefe se elige igual registre
  // Talento Humano o el propio servidor.
  const selectorJefe = (
    <Controller
      name="jefe_id"
      control={control}
      render={({ field }) => (
        <Select
          label="Jefe inmediato"
          placeholder={
            dirigidoATh
              ? "Firma el jefe de Talento Humano"
              : !unidadSelId
                ? "Seleccione primero la unidad"
                : jefeOptions.length === 0
                  ? "Sin jefes en esta unidad"
                  : "Seleccionar jefe"
          }
          data={jefeOptions}
          searchable
          clearable
          disabled={!unidadSelId || dirigidoATh}
          {...contained}
          value={field.value ? String(field.value) : null}
          onChange={(v) => field.onChange(v ? Number(v) : null)}
          error={errors.jefe_id?.message}
        />
      )}
    />
  );

  return (
    <Modal
      closeOnClickOutside={false}
      opened={opened}
      onClose={handleClose}
      title="Registrar permiso de ausencia"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <Stepper active={paso} mb="lg" size="sm">
        <Stepper.Step label="Datos del permiso" />
        <Stepper.Step label="Confirmación" />
      </Stepper>

      {/* ── PASO 0: Formulario ── */}
      {paso === 0 && !puedeRegistrar && (
        <Alert icon={<IconInfoCircle size={16} />} color="orange" variant="light">
          <Text size="sm">
            Su usuario no está vinculado a un servidor con unidad asignada, así
            que no hay a nombre de quién registrar el permiso. Pídale a Talento
            Humano que lo registre o que complete la vinculación.
          </Text>
        </Alert>
      )}

      {paso === 0 && puedeRegistrar && (
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <Stack gap="sm">
            {emiteATodos ? (
              <>
                {/* Unidad administrativa */}
                <Controller
                  name="unidad_administrativa_id"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Unidad administrativa"
                      placeholder="Seleccionar unidad"
                      data={unidadOptions}
                      searchable
                      {...contained}
                      value={field.value ? String(field.value) : null}
                      onChange={(v) => {
                        const id = v ? Number(v) : undefined;
                        field.onChange(id);
                        setUnidadSelId(id ?? null);
                        setValue("servidor_id", 0);
                        setValue("jefe_id", null);
                        setValue("dirigido_a_talento_humano", false);
                      }}
                      error={errors.unidad_administrativa_id?.message}
                    />
                  )}
                />

                <Grid>
                  {/* Servidor */}
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <Controller
                      name="servidor_id"
                      control={control}
                      render={({ field }) => (
                        <Select
                          label="Servidor"
                          placeholder={
                            !unidadSelId
                              ? "Seleccione primero la unidad"
                              : servidorOptions.length === 0
                                ? "Sin servidores en esta unidad"
                                : "Seleccionar servidor"
                          }
                          data={servidorOptions}
                          searchable
                          disabled={!unidadSelId}
                          {...contained}
                          value={field.value ? String(field.value) : null}
                          onChange={(v) => {
                            field.onChange(v ? Number(v) : undefined);
                            // La opción de Talento Humano se confirmó para otro
                            // servidor: si el nuevo es el propio jefe de TH ya no
                            // cabe, y en cualquier caso hay que volver a decidirla.
                            setValue("dirigido_a_talento_humano", false);
                          }}
                          error={errors.servidor_id?.message}
                        />
                      )}
                    />
                  </Grid.Col>

                  {/* Jefe inmediato */}
                  <Grid.Col span={{ base: 12, sm: 6 }}>{selectorJefe}</Grid.Col>
                </Grid>
              </>
            ) : (
              <Grid>
                {/* El solicitante es quien tiene la sesión: no se elige. */}
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <TextInput
                    label="Servidor"
                    description="Solo puede registrar sus propios permisos."
                    value={nombrePropio}
                    readOnly
                    {...contained}
                  />
                </Grid.Col>

                {/* Jefe inmediato */}
                <Grid.Col span={{ base: 12, sm: 6 }}>{selectorJefe}</Grid.Col>
              </Grid>
            )}

            {/*
              Omitir al jefe inmediato. Quien firma entonces no se elige: es el
              jefe vigente de la unidad de Talento Humano, o quien lo subrogue,
              y lo resuelve el backend con la misma regla que las Acciones de
              Personal.
            */}
            <Controller
              name="dirigido_a_talento_humano"
              control={control}
              render={({ field }) => (
                <DirigirATalentoHumano
                  activo={field.value}
                  servidorId={servidorWatch || undefined}
                  onCambiar={(activo) => {
                    field.onChange(activo);
                    if (activo) setValue("jefe_id", null);
                  }}
                />
              )}
            />

            <Divider label="Datos del permiso" labelPosition="left" />

            {/* Tipo de permiso */}
            <Controller
              name="tipo"
              control={control}
              render={({ field }) => (
                <Select
                  label="Tipo de permiso"
                  data={TIPO_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v ?? "personal")}
                  error={errors.tipo?.message}
                />
              )}
            />

            {tipoWatch === "personal" && (
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="orange"
                variant="light"
                py={6}
              >
                <Text size="xs">
                  Máximo 4 horas <b>por día</b> — se suman los permisos
                  personales que el servidor ya tenga esa fecha. Se descuentan
                  del saldo de vacaciones, así que hace falta un período abierto
                  con saldo suficiente.
                </Text>
              </Alert>
            )}

            {esRetroactivo && (
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="blue"
                variant="light"
                py={6}
              >
                <Text size="xs">
                  Se registra con la fecha en que ocurrió, nunca a futuro y
                  como mucho dos días hábiles atrás: el respaldo tiene 72 horas
                  laborables desde esa fecha para llegar a Recepción.
                </Text>
              </Alert>
            )}

            {/* Fecha */}
            <Controller
              name="fecha"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha del permiso"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  // Igual que en el backend: ningún tipo admite una fecha cuyo
                  // plazo de respaldo ya venció (dos días hábiles atrás como
                  // mucho), y enfermedad y calamidad además nunca a futuro.
                  minDate={fechaMasAntiguaAdmitida()}
                  maxDate={esRetroactivo ? new Date() : undefined}
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d) => field.onChange(fromDate(d ?? null) ?? "")}
                  error={errors.fecha?.message}
                />
              )}
            />

            <Grid>
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <TimeInput
                  label="Hora inicio"
                  {...contained}
                  {...register("hora_inicio")}
                  error={errors.hora_inicio?.message}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <TimeInput
                  label="Hora fin"
                  {...contained}
                  {...register("hora_fin")}
                  error={errors.hora_fin?.message}
                />
              </Grid.Col>
            </Grid>

            <Textarea
              label="Observación"
              placeholder={
                tipoWatch === "oficial"
                  ? "Requerido para permisos oficiales"
                  : "Motivo del permiso (opcional)"
              }
              autosize
              minRows={4}
              maxRows={6}
              {...contained}
              {...register("observacion")}
              error={errors.observacion?.message}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={handleClose}>
                Cancelar
              </Button>
              <Button
                type="submit"
                color="emerald"
                variant="light"
                loading={isSubmitting}
              >
                Crear permiso
              </Button>
            </Group>
          </Stack>
        </form>
      )}

      {/* ── PASO 1: Confirmación y exportar ── */}
      {paso === 1 && permisoCreado && (
        <Stack gap="md" align="center">
          <Alert
            icon={<IconCheck size={20} />}
            color="emerald"
            variant="light"
            w="100%"
          >
            <Text fw={600}>Permiso registrado correctamente</Text>
            <Text size="sm" mt={4}>
              Folio: <strong>{permisoCreado.folio ?? "—"}</strong>
            </Text>
          </Alert>

          <Stack gap="xs" w="100%">
            <Text size="sm" c="dimmed" ta="center">
              ¿Desea exportar el permiso en PDF para firma y archivo físico?
            </Text>
            <Group justify="center" mt="xs">
              <Button
                variant="light"
                color="blue"
                leftSection={<IconFileDownload size={16} />}
                loading={exportandoId !== null}
                onClick={handleExportar}
              >
                Exportar PDF
              </Button>
              <Button variant="default" onClick={handleClose}>
                Cerrar
              </Button>
            </Group>
          </Stack>
        </Stack>
      )}
    </Modal>
  );
}
