import type { NextConfig } from "next";

/**
 * Dónde está Laravel, visto desde el servidor de Next.
 *
 * El navegador ya no llama a Laravel por su dominio: llama a `/api/v1` en el
 * mismo dominio del frontend, y Next lo reenvía aquí. Es lo que permite que
 * el token viaje en una cookie HttpOnly —que solo se manda al mismo sitio que
 * la puso— y deje de estar al alcance del JavaScript de la página.
 *
 * Se lee al construir (`next build`): en producción hay que fijarlo antes de
 * construir la imagen. Si nginx ya sirve `/api` y `/storage` hacia Laravel en
 * el mismo dominio, estas reglas nunca se alcanzan y da igual su valor.
 */
const BACKEND_URL = process.env.BACKEND_URL ?? "http://sgth.test";

const nextConfig: NextConfig = {
  experimental: {
    turbopackFileSystemCacheForDev: true,
  },

  async rewrites() {
    return [
      { source: "/api/v1/:ruta*", destination: `${BACKEND_URL}/api/v1/:ruta*` },
      // Los adjuntos públicos (resultados de laboratorio, fotos).
      { source: "/storage/:ruta*", destination: `${BACKEND_URL}/storage/:ruta*` },
    ];
  },
};

export default nextConfig;
