// Fotos originales de cada galería (/categoria?cat=...).
// Las usa la página pública como valor por defecto y el panel /admin → Medios
// para mostrar lo que realmente está publicado. Si en el admin se edita una
// categoría, su lista se guarda en MySQL (config.media) y reemplaza a esta.
export const DEFAULT_GALLERIES = {
  "modulos": [
    "/assets/modulos/20260204_103237.webp",
    "/assets/modulos/20260204_102714.webp",
    "/assets/modulos/20260211_112857.webp",
    "/assets/modulos/20260211_112829.webp",
    "/assets/modulos/20260204_140942.webp",
    "/assets/modulos/20260204_140820.webp"
  ],
  "stands": [
    "/assets/stands/20260506_014651.webp",
    "/assets/stands/20260505_204724.webp",
    "/assets/stands/20260505_230503.webp",
    "/assets/stands/20260506_005732.webp",
    "/assets/stands/20260430_182019.webp",
    "/assets/stands/stand-caprimsa-01.webp",
    "/assets/stands/stand-caprimsa-02.webp",
    "/assets/stands/stand-heic-01.webp",
    "/assets/stands/stand-heic-02.webp"
  ],
  "cocheras-galerias": [
    "/assets/cocheras/cochera-01-nocturna.webp",
    "/assets/cocheras/cochera-02-galeria-dia.webp",
    "/assets/cocheras/cochera-03-motos.webp",
    "/assets/cocheras/cochera-04-construccion.webp",
    "/assets/cocheras/cochera-05-blanca.webp",
    "/assets/cocheras/cochera-06-terraza-deck.webp",
    "/assets/cocheras/cochera-07-galeria-jardin.webp",
    "/assets/cocheras/cochera-08-pergola-listones.webp"
  ],
  "ampliaciones": [
    "/assets/ampliaciones/amp-01-pared-listones.webp",
    "/assets/ampliaciones/amp-02-frente-nocturno.webp",
    "/assets/ampliaciones/amp-03-chapa-perforada.webp",
    "/assets/ampliaciones/amp-04-cielorraso-madera.webp",
    "/assets/ampliaciones/amp-05-voladizo-obra.webp",
    "/assets/ampliaciones/amp-06-pergola-vidrio.webp"
  ],
  "locales": [
    "/assets/locales/local_2_fachada_completa.webp",
    "/assets/locales/local_3_porton_grande.webp",
    "/assets/locales/local_1_detalle_ventanas.webp",
    "/assets/locales/local_4_calle_arbol.webp",
    "/assets/locales/local_5_lateral_camioneta.webp"
  ]
};

// Portadas originales de las tarjetas "Proyectos terminados" del inicio.
// Desde /admin → Medios se pueden reemplazar (config.covers en MySQL).
export const DEFAULT_COVERS = {
  "modulos": "https://images.unsplash.com/photo-1600585154340-be6161a56a0c?q=80&w=1300&auto=format&fit=crop",
  "stands": "/assets/stand-emergencias.webp",
  "cocheras-galerias": "/assets/cochera-bento.webp",
  "ampliaciones": "/assets/ampliaciones.webp",
  "locales": "/assets/locales-comerciales.webp"
};
