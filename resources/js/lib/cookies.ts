/**
 * Lee una cookie del navegador (por ejemplo XSRF-TOKEN, que Laravel exige en
 * los fetch que cambian datos).
 */
export function leerCookie(nombre: string) {
    const valor = document.cookie
        .split('; ')
        .find((c) => c.startsWith(`${nombre}=`))
        ?.split('=')[1];

    return valor ? decodeURIComponent(valor) : '';
}
