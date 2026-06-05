import { Metadata } from "next";
import { ViaticoDetallePage } from "@/features/viaticos/components/ViaticoDetallePage";

export const metadata: Metadata = {
  title: "Detalle de viático",
};

interface Props {
  params: Promise<{ id: string }>;
}

export default async function Page({ params }: Props) {
  const { id } = await params;
  return <ViaticoDetallePage viaticoId={Number(id)} />;
}
