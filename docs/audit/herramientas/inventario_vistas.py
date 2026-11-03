# Inventario de vistas desde el código: ruta GET -> controlador@método -> página Inertia -> diálogos de la página.
import json, re, subprocess, sys, os, collections

RAIZ = sys.argv[1]
SALIDA = sys.argv[2]
os.chdir(RAIZ)
rutas = json.loads(subprocess.run(['php', 'artisan', 'route:list', '--json'], capture_output=True, text=True, encoding='utf-8').stdout)


def metodo_de(archivo, metodo):
    try:
        src = open(archivo, encoding='utf-8').read()
    except OSError:
        return ''
    m = re.search(r'function\s+' + re.escape(metodo) + r'\s*\(', src)
    if not m:
        return ''
    resto = src[m.end():]
    n = re.search(r'\n    (public|protected|private) function ', resto)
    return resto[: n.start() if n else len(resto)]


def paginas_de(accion):
    if not accion.startswith('App'):
        return []
    clase, metodo = accion.split('@') if '@' in accion else (accion, '__invoke')
    archivo = clase.replace('App\\', 'app\\').replace('\\', '/') + '.php'
    cuerpo = metodo_de(archivo, metodo)
    return sorted(set(re.findall(r"(?:Inertia::render|inertia)\(\s*['\"]([^'\"]+)['\"]", cuerpo)))


def dialogos(pagina):
    ruta = f'resources/js/pages/{pagina}.tsx'
    if not os.path.exists(ruta):
        return None, []
    src = open(ruta, encoding='utf-8').read()
    lineas = src.count('\n') + 1
    titulos = re.findall(r'<(?:DialogTitle|SheetTitle|AlertDialogTitle)[^>]*>\s*([^<{]+?)\s*<', src)
    titulos = [re.sub(r'\s+', ' ', t).strip() for t in titulos if t.strip()]
    return lineas, titulos


def rol(uri):
    for prefijo in ['vendedor', 'gerente', 'almacen', 'tecnico-planta', 'tecnico-campo', 'guias', 'settings']:
        if f'/{prefijo}/' in f'/{uri}/' or uri.startswith(prefijo):
            return {'tecnico-planta': 'Técnico de Planta', 'tecnico-campo': 'Técnico de Campo', 'guias': 'Guías (Vendedor)', 'settings': 'Mi cuenta (todos)'}.get(prefijo, prefijo.capitalize())
    return 'Público / común'


filas = []
for r in rutas:
    if 'GET' not in r['method']:
        continue
    pags = paginas_de(r['action'])
    if not pags:
        continue
    for p in pags:
        lineas, dlg = dialogos(p)
        filas.append((rol(r['uri']), r['uri'], r.get('name') or '', p, lineas, dlg, r['action'].replace('App\\Http\\Controllers\\', '')))

usadas = {f[3] for f in filas}
todas = sorted(os.path.relpath(os.path.join(d, f), 'resources/js/pages').replace('\\', '/')[:-4]
               for d, _, fs in os.walk('resources/js/pages') for f in fs if f.endswith('.tsx'))
sin_ruta = [p for p in todas if p not in usadas]

orden = ['Público / común', 'Mi cuenta (todos)', 'Gerente', 'Vendedor', 'Guías (Vendedor)', 'Almacen', 'Técnico de Planta', 'Técnico de Campo']
filas.sort(key=lambda f: (orden.index(f[0]) if f[0] in orden else 99, f[1]))

with open(SALIDA, 'w', encoding='utf-8') as out:
    out.write('# Inventario de vistas actuales (generado desde el código)\n\n')
    out.write('> Generado por `docs/audit/herramientas/inventario_vistas.py` a partir de `php artisan route:list` y de las llamadas `Inertia::render` de cada controlador. ')
    out.write('Los diálogos salen de `DialogTitle`/`SheetTitle` de cada página (los títulos dinámicos no aparecen). Volver a generar después de cambiar rutas.\n\n')
    out.write(f'**Total:** {len(filas)} rutas de pantalla · {len(usadas)} páginas con ruta · {len(todas)} archivos de página · {sum(len(f[5]) for f in filas)} diálogos con título fijo.\n\n')
    actual = None
    n = 0
    for rol_, uri, nombre, pagina, lineas, dlg, accion in filas:
        if rol_ != actual:
            actual = rol_
            out.write(f'\n## {rol_}\n\n| ID | Ruta | Nombre de ruta | Página | Líneas | Diálogos y paneles | Controlador |\n|---|---|---|---|---|---|---|\n')
        n += 1
        out.write(f"| V-{n:03d} | `/{uri}` | `{nombre}` | `{pagina}` | {lineas or '—'} | {'; '.join(dlg) or '—'} | `{accion}` |\n")
    out.write('\n## Páginas sin ruta GET propia\n\n')
    out.write('`auth/*` las sirve Fortify (`FortifyServiceProvider`); `errors/*`, el manejador de excepciones; `settings/appearance` es una vista directa. '
              'Las demás se abren desde otra pantalla o son restos: revisarlas una por una (los diálogos de usuarios guardados en `pages/` deberían ir a `components/`).\n\n')
    for p in sin_ruta:
        out.write(f'- `{p}`\n')
    grandes = sorted({(f[4] or 0, f[3]) for f in filas}, reverse=True)[:12]
    out.write('\n## Páginas más grandes (candidatas a dividir en componentes)\n\n| Página | Líneas |\n|---|---|\n')
    for l, p in grandes:
        out.write(f'| `{p}` | {l} |\n')
print(len(filas), len(usadas), len(todas), len(sin_ruta))
