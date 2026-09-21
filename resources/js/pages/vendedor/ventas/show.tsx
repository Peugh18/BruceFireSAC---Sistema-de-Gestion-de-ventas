import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Banknote, CalendarDays, ReceiptText } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type SaleItem = {
    id: number;
    tipo_linea?: string;
    numero_serie?: string;
    cantidad?: number;
    precio_unitario?: number | string;
    descuento?: number | string;
    subtotal?: number | string;
    product?: { nombre?: string };
    service?: { nombre?: string };
};

type SalePayment = {
    id: number;
    forma_pago?: string;
    monto?: number | string;
    fecha_pago?: string;
    estado?: string;
};

type ElectronicDocument = {
    id: number;
    tipo?: string;
    serie?: string;
    correlativo?: number;
    sunat_estado?: string;
    sunat_mensaje?: string;
};

type Sale = {
    id: number;
    numero_interno?: string;
    fecha?: string;
    comprobante_tipo?: string;
    condicion_pago?: string;
    destino?: string;
    subtotal?: number | string;
    igv?: number | string;
    total?: number | string;
    estado?: string;
    client?: { razon_social?: string; numero_documento?: string };
    items?: SaleItem[];
    payments?: SalePayment[];
    electronic_documents?: ElectronicDocument[];
};

type Props = { sale: Sale };

function money(value?: number | string) {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(value ?? 0) || 0);
}

function nice(value?: string) {
    return value ? value.replaceAll('_', ' ') : '-';
}

export default function VentasShow({ sale }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? (typeof window !== 'undefined' ? window.location.pathname.split('/')[1] : '');
    const [confirmando, setConfirmando] = useState(false);
    const documento = (sale.electronic_documents ?? [])[0];

    function confirmarVenta() {
        setConfirmando(true);
        router.post(
            ventas.confirmar.url({ current_team: teamSlug, sale: sale.id }),
            {},
            { onFinish: () => setConfirmando(false) },
        );
    }

    return (
        <VendedorLayout title="Detalle venta">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Button asChild variant="outline" className="h-9 rounded-[9px] border-[#E4E1DC] bg-white text-[#4A4742] shadow-none">
                        <Link href={ventas.index(teamSlug)}>
                            <ArrowLeft className="size-4" />
                            Ventas
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-[24px] font-semibold tracking-[0.02em] text-[#201F1D] uppercase">{sale.numero_interno ?? `Venta ${sale.id}`}</h1>
                        <p className="text-[12.5px] text-[#8A8680]">{sale.client?.razon_social ?? 'Cliente sin datos'}</p>
                    </div>
                    <div className="flex-1" />
                    {sale.estado === 'borrador' && (
                        <Button
                            onClick={confirmarVenta}
                            disabled={confirmando}
                            className="h-9 rounded-[9px] bg-[#1E8E5A] text-white shadow-none hover:bg-[#17714A]"
                        >
                            {confirmando ? 'Confirmando…' : 'Confirmar y emitir'}
                        </Button>
                    )}
                    <Badge className="rounded-full border-transparent bg-[#E5F5EC] px-3 py-1 text-[10.5px] font-bold text-[#1E8E5A] capitalize">{sale.estado ?? 'registrada'}</Badge>
                    {documento && (
                        <Badge className="rounded-full border-transparent bg-[#EAF1FF] px-3 py-1 text-[10.5px] font-bold text-[#2563EB] capitalize">
                            SUNAT: {documento.serie}-{documento.correlativo} · {documento.sunat_estado}
                        </Badge>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-4">
                    <Card className="gap-1 rounded-[14px] border-[#E7E4DE] bg-white p-4 shadow-none">
                        <CalendarDays className="size-5 text-[#B45309]" />
                        <div className="text-[11px] font-bold text-[#8A8680] uppercase">Fecha</div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">{sale.fecha ?? '-'}</div>
                    </Card>
                    <Card className="gap-1 rounded-[14px] border-[#E7E4DE] bg-white p-4 shadow-none">
                        <ReceiptText className="size-5 text-[#2563EB]" />
                        <div className="text-[11px] font-bold text-[#8A8680] uppercase">Comprobante</div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold uppercase">{sale.comprobante_tipo ?? '-'}</div>
                    </Card>
                    <Card className="gap-1 rounded-[14px] border-[#E7E4DE] bg-white p-4 shadow-none">
                        <Banknote className="size-5 text-[#1E8E5A]" />
                        <div className="text-[11px] font-bold text-[#8A8680] uppercase">Condición</div>
                        <div className="text-[13px] font-bold capitalize">{nice(sale.condicion_pago)}</div>
                    </Card>
                    <Card className="gap-1 rounded-[14px] border-[#E7E4DE] bg-white p-4 shadow-none">
                        <div className="text-[11px] font-bold text-[#8A8680] uppercase">Total</div>
                        <div className="font-['Oswald',sans-serif] text-[25px] font-semibold">{money(sale.total)}</div>
                    </Card>
                </div>

                <Card className="gap-0 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">Items</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>{['Producto', 'Serie', 'Tipo', 'Cant.', 'P. Unit.', 'Desc.', 'Subtotal'].map((h) => <th key={h} className="border-b border-[#E7E4DE] px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-[#8A8680] uppercase">{h}</th>)}</tr>
                            </thead>
                            <tbody>
                                {(sale.items ?? []).map((item) => (
                                    <tr key={item.id}>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-semibold">{item.product?.nombre ?? item.service?.nombre ?? '-'}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace]">{item.numero_serie ?? '-'}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] capitalize">{nice(item.tipo_linea)}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">{item.cantidad ?? 1}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">{money(item.precio_unitario)}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">{money(item.descuento)}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-bold">{money(item.subtotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card className="gap-0 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">Pagos</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>{['Forma', 'Fecha', 'Monto', 'Estado'].map((h) => <th key={h} className="border-b border-[#E7E4DE] px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-[#8A8680] uppercase">{h}</th>)}</tr>
                            </thead>
                            <tbody>
                                {(sale.payments ?? []).length === 0 ? (
                                    <tr><td colSpan={4} className="px-2.5 py-8 text-center text-[#8A8680]">Sin pagos registrados</td></tr>
                                ) : (
                                    (sale.payments ?? []).map((payment) => (
                                        <tr key={payment.id}>
                                            <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] capitalize">{nice(payment.forma_pago)}</td>
                                            <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">{payment.fecha_pago ?? '-'}</td>
                                            <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-bold">{money(payment.monto)}</td>
                                            <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] capitalize">{payment.estado ?? '-'}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </VendedorLayout>
    );
}
