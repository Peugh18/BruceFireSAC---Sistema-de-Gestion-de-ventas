# Bruce Fire — Sistema de diseño

## Producto
Panel interno de gestión (Inertia + React + Laravel) para una empresa peruana de venta, mantenimiento e inspección de extintores y sistemas contra incendios. Uso 100% empresarial: 5 roles internos (Vendedor, Almacén, Gerente, Técnico Planta, Técnico Campo) inician sesión con credenciales asignadas por la empresa — **no hay registro público**.

## Personalidad de marca
Industrial, sobria, de confianza. Nada de "startup juguetona" ni landing de marketing genérica. El usuario es un vendedor, técnico o gerente trabajando, no un visitante decidiendo si comprar.

## Logo
- Ícono/monograma: `public/brand/logo-icon.png` — "EF" en plateado metálico + llama roja/naranja, fondo transparente.
- Wordmark completo: `public/brand/logo-full.png` — "EXTINTORES BRUCE FIRE — SISTEMAS CONTRA INCENDIOS", fondo transparente.

## Paleta (ya vive en `resources/css/app.css` como tokens OKLCH)
- **Rojo de marca** (`--primary` / `--destructive`, oklch(0.55 0.22 25) ≈ `#D2232A`–`#E8352C`): reservado para acciones primarias, estado activo, alertas reales. **No usar como fondo dominante** — el rojo repetido en toda la UI causa fatiga visual y le resta peso a las alertas de verdad.
- **Gris/plata** (tokens `--muted`, `--muted-foreground`, `--border`): color base neutro para superficies, texto secundario, bordes — es el color del monograma "EF".
- **Naranja llama** (`#F7941D`–`#FF6B00`): acento puntual, no token global todavía — usar solo para iconografía de urgencia/stock bajo si se necesita, nunca como color de fondo.
- Modo oscuro ya implementado con los mismos tokens invertidos.

## Tipografía (ya en uso, mantener)
- Títulos / marca: `Oswald` (bold, uppercase, tracking amplio) — se usa en sidebars y pantallas de error.
- Datos/códigos: `IBM Plex Mono` — para subtítulos técnicos tipo "Panel Vendedor", números de orden, etc.
- Cuerpo: la fuente sans-serif por defecto de shadcn/Tailwind.

## Qué evitar (detectado en el estado previo, ya se está corrigiendo)
- Splash/landing por defecto de Laravel (`welcome.tsx` mostraba "Let's get started" con links a Laravel Docs/Laracast) — reemplazado por landing de marca.
- Layout de login genérico shadcn sin logo — reemplazado con el ícono real.
- Badge de texto "BF" hecho con un div y una tipografía, en vez del logo real — reemplazado por `logo-icon.png` en los 5 sidebars/headers de rol.
- `APP_NAME=Laravel` en `.env` (aparecía literal en el título de la pestaña del navegador) — corregido a "Bruce Fire".
- Barra de búsqueda decorativa en el header de Vendedor/Almacén/Gerente que no estaba conectada a nada — eliminada por decisión explícita del usuario (se implementará más adelante cuando se defina bien el alcance).

## Restricción de movimiento
Panel de gestión operativa, no landing de marketing. Transiciones cortas y con propósito (apertura de sidebar móvil, hover de botones, apertura de modales) — nunca animación decorativa que retrase completar una tarea. Referencia: filosofía de restricción de Apple/Emil Kowalski (`apple-design`, `emil-design-eng`).
