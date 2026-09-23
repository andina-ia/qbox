// Genera /api/seed.json en el build: la config base que install.php carga en MySQL
// la primera vez. Después de instalar, la fuente de verdad es la base de datos.
import config from '../../data/config.json';

export function GET() {
  return new Response(JSON.stringify(config), {
    headers: { 'Content-Type': 'application/json' }
  });
}
