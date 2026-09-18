"use client";

import { useMemo } from "react";
import { Stack, Grid, Alert, Button, Text } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconCheck, IconAlertCircle } from "@tabler/icons-react";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { useCategoriasFactura, useLiquidacion } from "../hooks/useViaticos";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import { ActividadesModal } from "./ActividadesModal";
import { FacturasModal } from "./FacturasModal";
import { LiquidacionActividadesCard } from "./LiquidacionActividadesCard";
import { LiquidacionFacturasCard } from "./LiquidacionFacturasCard";
import { CalculoViaticoCard } from "./CalculoViaticoCard";
import type { ActividadData } from "./ActividadesModal";
import type { FacturaData } from "./FacturasModal";
import type {
  Viatico,
  CategoriaFactura,
  ActividadLiquidacion,
  ComprobanteRevisado,
} from "@/types/api";

interface Props {
  viatico: Viatico;
  onSuccess: () => void;
}

export function LiquidacionSection({ viatico, onSuccess }: Props) {
  const { data: categoriasData = [] } = useCategoriasFactura();
  const { data: liquidacionData } = useLiquidacion(viatico.id);
  const { confirmarLiquidacion } = useViaticoMutations();
  // La presenta el titular o quien opera; Talento Humano y los acompañantes
  // la ven sin poder tocarla.
  const presenta = useAccionesViatico().liquidar(viatico);

  const [actModalAbierto, { open: abrirAct, close: cerrarAct }] =
    useDisclosure(false);
  const [factModalAbierto, { open: abrirFact, close: cerrarFact }] =
    useDisclosure(false);

  const actividades: ActividadData[] = useMemo(() => {
    if (!liquidacionData?.actividades) return [];
    return liquidacionData.actividades.map((a: ActividadLiquidacion) => ({
      fecha: a.fecha as string,
      hora_inicio: (a.hora_inicio as string) ?? "",
      hora_fin: (a.hora_fin as string) ?? "",
      descripcion: a.descripcion as string,
      lugar: a.lugar as string,
    }));
  }, [liquidacionData]);

  const facturas: FacturaData[] = useMemo(() => {
    if (!liquidacionData?.detalles_factura) return [];
    return liquidacionData.detalles_factura.map((f: ComprobanteRevisado) => ({
      categoria_factura_id: Number(f.categoria_factura_id),
      fecha_factura: (f.fecha_factura as string) ?? "",
      tipo_comprobante:
        (f.tipo_comprobante as "factura" | "ticket" | "recibo" | "otro") ??
        "factura",
      numero_factura: (f.numero_factura as string) ?? "",
      numero_ticket: (f.numero_ticket as string) ?? "",
      ruc_proveedor: (f.ruc_proveedor as string) ?? "",
      nombre_proveedor: f.nombre_proveedor as string,
      detalle: (f.detalle as string) ?? "",
      monto: Number(f.monto),
      estado_revision: f.estado_revision,
      observacion_revision: f.observacion_revision,
    }));
  }, [liquidacionData]);

  // La cuenta llega resuelta del backend: aquí solo se muestra.
  const calculo = liquidacionData?.calculo;

  const puedeRegistrar = actividades.length > 0 && facturas.length > 0;

  const handleRegistrar = async () => {
    try {
      await confirmarLiquidacion.mutateAsync(viatico.id);
      onSuccess();
    } catch {
      // El hook de mutación ya notifica el error.
    }
  };

  return (
    <Stack gap="md">
      {calculo && <CalculoViaticoCard calculo={calculo} enCurso />}

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <LiquidacionActividadesCard
            actividades={actividades}
            onRegistrar={presenta ? abrirAct : undefined}
            onEditar={presenta ? abrirAct : undefined}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <LiquidacionFacturasCard
            facturas={facturas}
            categorias={categoriasData as CategoriaFactura[]}
            onRegistrar={presenta ? abrirFact : undefined}
            onEditar={presenta ? abrirFact : undefined}
          />
        </Grid.Col>
      </Grid>

      {presenta && !puedeRegistrar && (
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="slate"
          variant="light"
        >
          <Text size="xs">
            Para registrar la liquidación debe completar tanto el{" "}
            <strong>informe de actividades</strong> como las{" "}
            <strong>facturas de respaldo</strong>.
          </Text>
        </Alert>
      )}

      {presenta && (
        <Button
          size="md"
          disabled={!puedeRegistrar}
          loading={confirmarLiquidacion.isPending}
          leftSection={<IconCheck size={16} />}
          onClick={handleRegistrar}
          fullWidth
        >
          Registrar liquidación
        </Button>
      )}

      <ActividadesModal
        opened={actModalAbierto}
        onClose={cerrarAct}
        viatico={viatico}
        onGuardar={() => {}}
        valorInicial={actividades}
      />

      <FacturasModal
        opened={factModalAbierto}
        onClose={cerrarFact}
        viatico={viatico}
        calculo={calculo}
        onGuardar={() => {}}
        valorInicial={facturas}
      />
    </Stack>
  );
}
