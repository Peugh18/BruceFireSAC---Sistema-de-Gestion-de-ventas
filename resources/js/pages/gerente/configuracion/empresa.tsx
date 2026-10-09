import { router, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    CreditCard,
    Eye,
    Palette,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { ConfiguracionTabs } from '@/components/configuracion-tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import UbigeoPicker, { type UbigeoOption } from '@/components/ubigeo-picker';
import cuentasBancarias from '@/routes/gerente/configuracion/cuentas-bancarias';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
import empresa from '@/routes/gerente/configuracion/empresa';
import GerenteLayout from '@/layouts/gerente-layout';
import type { Team } from '@/types';

type Company = {
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string;
    direccion: string | null;
    ubigeo: string | null;
    ubicacion: UbigeoOption | null;
    telefono: string | null;
    email: string | null;
    sitio_web: string | null;
    color_marca: string;
    leyenda_pie: string | null;
    mensaje_agradecimiento: string | null;
    condiciones_comprobante: string | null;
    cuenta_detraccion: string | null;
    logo_url: string | null;
};

type BankAccount = {
    id: number;
    banco: string;
    titular: string;
    numero_cuenta: string;
    cci: string | null;
    moneda: string;
    activo: boolean;
};

type Props = {
    company: Company;
    bankAccounts: BankAccount[];
    firmasPendientes: number;
};

// Mismos límites que valida el servidor (UpdateCompanySettingRequest).
const LOGO_MIN_ANCHO = 150;
const LOGO_MIN_ALTO = 60;
const LOGO_MAX_LADO = 3000;

const COLORES_SUGERIDOS = [
    '#D2232A',
    '#B71C1C',
    '#E65100',
    '#1F4E79',
    '#1B5E20',
    '#263238',
];

function field(errors: Record<string, string>, name: string) {
    return errors[name] ? (
        <p role="alert" className="text-destructive-strong mt-1 text-[11.5px]">
            {errors[name]}
        </p>
    ) : null;
}

export default function EmpresaConfiguracion({
    company,
    bankAccounts,
    firmasPendientes,
}: Props) {
    const { pedirConfirmacion, dialogoConfirmacion } = useConfirmarAccion();
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [logoPreview, setLogoPreview] = useState<string | null>(
        company.logo_url,
    );
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [avisoLogo, setAvisoLogo] = useState<string | null>(null);

    const [ubicacion, setUbicacion] = useState<UbigeoOption | null>(
        company.ubicacion,
    );

    const form = useForm({
        razon_social: company.razon_social,
        nombre_comercial: company.nombre_comercial ?? '',
        ruc: company.ruc,
        direccion: company.direccion ?? '',
        ubigeo: company.ubigeo ?? '',
        telefono: company.telefono ?? '',
        email: company.email ?? '',
        sitio_web: company.sitio_web ?? '',
        color_marca: company.color_marca,
        leyenda_pie: company.leyenda_pie ?? '',
        mensaje_agradecimiento: company.mensaje_agradecimiento ?? '',
        condiciones_comprobante: company.condiciones_comprobante ?? '',
        cuenta_detraccion: company.cuenta_detraccion ?? '',
        logo: null as File | null,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(empresa.update.url({ current_team: teamSlug }), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    }

    function onLogoChange(event: React.ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0] ?? null;
        setAvisoLogo(null);

        if (!file) {
            form.setData('logo', null);
            return;
        }

        const url = URL.createObjectURL(file);
        const imagen = new Image();
        imagen.onload = () => {
            const { naturalWidth: ancho, naturalHeight: alto } = imagen;

            if (
                ancho < LOGO_MIN_ANCHO ||
                alto < LOGO_MIN_ALTO ||
                ancho > LOGO_MAX_LADO ||
                alto > LOGO_MAX_LADO
            ) {
                setAvisoLogo(
                    `El logo mide ${ancho}×${alto} px. Debe tener al menos ${LOGO_MIN_ANCHO}×${LOGO_MIN_ALTO} px y como máximo ${LOGO_MAX_LADO} px por lado.`,
                );
                form.setData('logo', null);
                URL.revokeObjectURL(url);

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }

                return;
            }

            form.setData('logo', file);
            setLogoPreview(url);
        };
        imagen.src = url;
    }

    const bankForm = useForm({
        banco: '',
        titular: company.razon_social,
        numero_cuenta: '',
        cci: '',
        moneda: 'PEN',
    });

    function addBankAccount(event: React.FormEvent) {
        event.preventDefault();
        bankForm.post(cuentasBancarias.store.url({ current_team: teamSlug }), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => bankForm.reset('banco', 'numero_cuenta', 'cci'),
        });
    }

    async function deleteBankAccount(id: number) {
        if (
            !(await pedirConfirmacion(
                '¿Eliminar esta cuenta bancaria? Dejará de imprimirse en los comprobantes.',
            ))
        ) {
            return;
        }

        router.delete(
            cuentasBancarias.destroy.url({
                current_team: teamSlug,
                cuenta_bancaria: id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo eliminar la cuenta bancaria.',
                    ),
                preserveScroll: true,
            },
        );
    }

    return (
        <GerenteLayout title="Datos de la empresa">
            <div className="flex flex-col gap-5">
                {dialogoConfirmacion}
                <ConfiguracionTabs
                    teamSlug={teamSlug}
                    activa="empresa"
                    firmasPendientes={firmasPendientes}
                />
                <div className="flex items-center gap-3">
                    <div className="bg-destructive/10 text-primary-strong flex size-10 items-center justify-center rounded-[11px]">
                        <Building2 className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-[20px] font-semibold uppercase">
                            Datos de la empresa
                        </h1>
                        <p className="text-muted-foreground text-[12px]">
                            Se usan en todos los comprobantes (Factura, Boleta,
                            Nota) y certificados.
                        </p>
                    </div>
                </div>

                <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex items-center gap-4">
                            <div className="border-border bg-muted/40 flex size-20 items-center justify-center overflow-hidden rounded-[10px] border border-dashed">
                                {logoPreview ? (
                                    // eslint-disable-next-line @next/next/no-img-element
                                    <img
                                        src={logoPreview}
                                        alt="Logo de la empresa"
                                        className="max-h-full max-w-full object-contain"
                                    />
                                ) : (
                                    <span className="text-muted-foreground text-[10px]">
                                        Sin logo
                                    </span>
                                )}
                            </div>
                            <div>
                                <input
                                    aria-label="Subir logo de la empresa"
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={onLogoChange}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                    className="border-border bg-card text-foreground/80 h-9 rounded-[9px] shadow-none"
                                >
                                    <Upload className="size-3.5" /> Subir logo
                                </Button>
                                <p className="text-muted-foreground mt-1 text-[11px]">
                                    PNG con fondo transparente (o JPG), de
                                    {` ${LOGO_MIN_ANCHO}×${LOGO_MIN_ALTO}`} a{' '}
                                    {LOGO_MAX_LADO} px, máx. 2MB. Ideal:
                                    horizontal, unos 600×200 px. En el
                                    comprobante se ajusta solo, sin deformarse.
                                </p>
                                {avisoLogo ? (
                                    <p className="text-destructive-strong mt-1 text-[11.5px] font-semibold">
                                        {avisoLogo}
                                    </p>
                                ) : null}
                                {field(form.errors, 'logo')}
                            </div>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-razon-social"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Razón social
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-razon-social"
                                    value={form.data.razon_social}
                                    onChange={(e) =>
                                        form.setData(
                                            'razon_social',
                                            e.target.value,
                                        )
                                    }
                                />
                                {field(form.errors, 'razon_social')}
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-nombre-comercial"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Nombre comercial
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-nombre-comercial"
                                    value={form.data.nombre_comercial}
                                    onChange={(e) =>
                                        form.setData(
                                            'nombre_comercial',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-ruc"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    RUC
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-ruc"
                                    value={form.data.ruc}
                                    onChange={(e) =>
                                        form.setData('ruc', e.target.value)
                                    }
                                    maxLength={11}
                                />
                                {field(form.errors, 'ruc')}
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-telefono"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Teléfono
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-telefono"
                                    value={form.data.telefono}
                                    onChange={(e) =>
                                        form.setData('telefono', e.target.value)
                                    }
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label
                                    htmlFor="gerente-configuracion-empresa-direccion"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Dirección
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-direccion"
                                    value={form.data.direccion}
                                    onChange={(e) =>
                                        form.setData(
                                            'direccion',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Ubigeo (distrito)
                                </Label>
                                <UbigeoPicker
                                    value={ubicacion}
                                    onChange={(ubigeo) => {
                                        setUbicacion(ubigeo);
                                        form.setData(
                                            'ubigeo',
                                            ubigeo?.codigo ?? '',
                                        );
                                    }}
                                    error={form.errors.ubigeo}
                                />
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-email"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Email
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-pagina-web"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Página web
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-pagina-web"
                                    value={form.data.sitio_web}
                                    onChange={(e) =>
                                        form.setData(
                                            'sitio_web',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="www.extintoresbrucefire.com"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label
                                    htmlFor="gerente-configuracion-empresa-cuenta-de-detraccion-banco-de-la-nacion"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Cuenta de detracción (Banco de la Nación)
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-cuenta-de-detraccion-banco-de-la-nacion"
                                    value={form.data.cuenta_detraccion}
                                    onChange={(e) =>
                                        form.setData(
                                            'cuenta_detraccion',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="00-000-000000"
                                />
                                <p className="text-muted-foreground mt-1 text-[11px]">
                                    Obligatoria en comprobantes con detracción
                                    (servicios sobre S/ 700).
                                </p>
                            </div>
                        </div>

                        <div className="border-border flex flex-col gap-3 border-t pt-4">
                            <div className="flex items-center gap-2">
                                <Palette className="text-primary-strong size-4" />
                                <h2 className="font-['Oswald',sans-serif] text-[16px] font-semibold uppercase">
                                    Diseño del comprobante
                                </h2>
                            </div>
                            <p className="text-muted-foreground -mt-2 text-[12px]">
                                Vale para todas las facturas y boletas. Los
                                datos de cada venta (cliente, productos,
                                referencia, observaciones) salen solos de la
                                venta o cotización.
                            </p>
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Color de la marca
                                </Label>
                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                    <input
                                        aria-label="Color marca"
                                        type="color"
                                        value={form.data.color_marca}
                                        onChange={(e) =>
                                            form.setData(
                                                'color_marca',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        className="border-border h-9 w-12 cursor-pointer rounded-[8px] border bg-transparent p-1"
                                    />
                                    {COLORES_SUGERIDOS.map((color) => (
                                        <button
                                            key={color}
                                            type="button"
                                            title={color}
                                            onClick={() =>
                                                form.setData(
                                                    'color_marca',
                                                    color,
                                                )
                                            }
                                            className={`size-7 rounded-full border-2 transition-transform hover:scale-110 ${form.data.color_marca === color ? 'border-foreground' : 'border-transparent'}`}
                                            style={{ backgroundColor: color }}
                                        />
                                    ))}
                                    <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[12px]">
                                        {form.data.color_marca}
                                    </span>
                                </div>
                                {field(form.errors, 'color_marca')}
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-mensaje-de-agradecimiento"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Mensaje de agradecimiento
                                </Label>
                                <Input
                                    id="gerente-configuracion-empresa-mensaje-de-agradecimiento"
                                    value={form.data.mensaje_agradecimiento}
                                    onChange={(e) =>
                                        form.setData(
                                            'mensaje_agradecimiento',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="¡Gracias por su preferencia!"
                                    maxLength={150}
                                />
                            </div>
                            <div>
                                <Label
                                    htmlFor="gerente-configuracion-empresa-condiciones-de-venta-o-garantia-opcional"
                                    className="text-foreground/80 text-[11px] font-bold uppercase"
                                >
                                    Condiciones de venta o garantía (opcional)
                                </Label>
                                <Textarea
                                    id="gerente-configuracion-empresa-condiciones-de-venta-o-garantia-opcional"
                                    value={form.data.condiciones_comprobante}
                                    onChange={(e) =>
                                        form.setData(
                                            'condiciones_comprobante',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej: Garantía de 1 año en extintores nuevos. No se aceptan devoluciones de recargas ya realizadas."
                                    rows={3}
                                    maxLength={1000}
                                />
                                {field(form.errors, 'condiciones_comprobante')}
                            </div>
                        </div>

                        <div>
                            <Label
                                htmlFor="gerente-configuracion-empresa-leyenda-de-pie-opcional"
                                className="text-foreground/80 text-[11px] font-bold uppercase"
                            >
                                Leyenda de pie (opcional)
                            </Label>
                            <Textarea
                                id="gerente-configuracion-empresa-leyenda-de-pie-opcional"
                                value={form.data.leyenda_pie}
                                onChange={(e) =>
                                    form.setData('leyenda_pie', e.target.value)
                                }
                                placeholder='Ej: "Encomienda al Señor todo lo que haces, y tus planes se cumplirán."'
                                rows={2}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-foreground text-background hover:bg-foreground/90 h-10 rounded-[9px] px-4 text-[13px] font-bold shadow-xs transition-colors"
                            >
                                {form.processing
                                    ? 'Guardando…'
                                    : 'Guardar cambios'}
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="border-border bg-card text-foreground h-10 rounded-[9px] shadow-none"
                            >
                                <a
                                    href={empresa.vistaPrevia.url({
                                        current_team: teamSlug,
                                    })}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" />
                                    Vista previa de la factura
                                </a>
                            </Button>
                            {form.isDirty ? (
                                <span className="text-muted-foreground text-[11.5px]">
                                    Guarda los cambios para verlos en la vista
                                    previa.
                                </span>
                            ) : null}
                        </div>
                    </form>
                </Card>

                <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                    <div className="flex items-center gap-2">
                        <CreditCard className="text-success-strong size-4" />
                        <h2 className="font-['Oswald',sans-serif] text-[16px] font-semibold uppercase">
                            Cuentas bancarias
                        </h2>
                    </div>

                    <div className="flex flex-col gap-2">
                        {bankAccounts.length === 0 ? (
                            <p className="text-muted-foreground text-[12px]">
                                Sin cuentas registradas. Se imprimen en todos
                                los comprobantes.
                            </p>
                        ) : (
                            bankAccounts.map((account) => (
                                <div
                                    key={account.id}
                                    className="border-border flex items-center justify-between rounded-[9px] border px-3 py-2 text-[12px]"
                                >
                                    <div>
                                        <span className="font-bold">
                                            {account.banco}
                                        </span>{' '}
                                        — {account.titular} —{' '}
                                        {account.numero_cuenta}
                                        {account.cci
                                            ? ` — CCI: ${account.cci}`
                                            : ''}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label={`Eliminar cuenta de ${account.banco}`}
                                        onClick={() =>
                                            deleteBankAccount(account.id)
                                        }
                                        className="border-border bg-card text-destructive-strong size-7 rounded-[7px] shadow-none"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>

                    <form
                        onSubmit={addBankAccount}
                        className="border-border grid gap-2 border-t pt-3 md:grid-cols-5"
                    >
                        <Input
                            aria-label="Banco"
                            placeholder="Banco (ej. BCP)"
                            value={bankForm.data.banco}
                            onChange={(e) =>
                                bankForm.setData('banco', e.target.value)
                            }
                        />
                        <Input
                            aria-label="Titular"
                            placeholder="Titular"
                            value={bankForm.data.titular}
                            onChange={(e) =>
                                bankForm.setData('titular', e.target.value)
                            }
                        />
                        <Input
                            aria-label="Número de cuenta"
                            placeholder="N° de cuenta"
                            value={bankForm.data.numero_cuenta}
                            onChange={(e) =>
                                bankForm.setData(
                                    'numero_cuenta',
                                    e.target.value,
                                )
                            }
                        />
                        <Input
                            aria-label="CCI"
                            placeholder="CCI (opcional)"
                            value={bankForm.data.cci}
                            onChange={(e) =>
                                bankForm.setData('cci', e.target.value)
                            }
                        />
                        <Button
                            type="submit"
                            disabled={bankForm.processing}
                            className="h-10 rounded-[9px] bg-emerald-600 text-white shadow-none hover:bg-emerald-700"
                        >
                            Agregar
                        </Button>
                    </form>
                </Card>
            </div>
        </GerenteLayout>
    );
}
