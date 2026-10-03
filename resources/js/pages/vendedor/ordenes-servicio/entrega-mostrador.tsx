import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';

import CounterDeliveryController from '@/actions/App/Http/Controllers/Vendedor/CounterDeliveryController';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

type Order = {
    id: number;
    codigo: string;
    estado: string;
    client: { razon_social: string; numero_documento: string };
};

type Props = {
    order: Order;
    delivery: Record<string, unknown> | null;
    currentTeam: Team;
    isPaid: boolean;
};

export default function CounterDelivery({
    order,
    delivery,
    currentTeam,
    isPaid,
}: Props) {
    const showUrl = `/${currentTeam.slug}/vendedor/ordenes-servicio/${order.id}`;

    return (
        <VendedorLayout title="Entrega en mostrador">
            <Head title={`Entrega en mostrador · ${order.codigo}`} />
            <div className="mx-auto max-w-2xl space-y-5 p-4 sm:p-6">
                <Button variant="ghost" asChild>
                    <Link href={showUrl}>
                        <ArrowLeft className="mr-2 size-4" /> Volver a la orden
                    </Link>
                </Button>

                <div>
                    <h1 className="text-2xl font-bold">Entrega en mostrador</h1>
                    <p className="text-muted-foreground text-sm">
                        {order.codigo} · {order.client.razon_social}
                    </p>
                </div>

                {!isPaid && (
                    <div
                        role="alert"
                        className="rounded-xl border border-amber-400 bg-amber-50 p-4 text-sm font-semibold text-amber-900"
                    >
                        Esta orden todavía no está cobrada. Puedes entregarla,
                        pero registra el cobro pendiente.
                    </div>
                )}

                <Card className="p-5">
                    {delivery ? (
                        <div className="space-y-4">
                            <p className="font-medium text-emerald-700">
                                La entrega conforme ya fue registrada.
                            </p>
                            <Button asChild>
                                <a
                                    href={CounterDeliveryController.pdf.url({
                                        current_team: currentTeam.slug,
                                        service_order: order.id,
                                    })}
                                >
                                    <Download className="mr-2 size-4" />{' '}
                                    Descargar acta PDF
                                </a>
                            </Button>
                        </div>
                    ) : (
                        <Form
                            {...CounterDeliveryController.store.form({
                                current_team: currentTeam.slug,
                                service_order: order.id,
                            })}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="receptor_nombre">
                                            Quién recibe
                                        </Label>
                                        <Input
                                            id="receptor_nombre"
                                            name="receptor_nombre"
                                            required
                                        />
                                        {errors.receptor_nombre && (
                                            <p className="text-destructive-strong text-sm">
                                                {errors.receptor_nombre}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="receptor_dni">
                                            DNI
                                        </Label>
                                        <Input
                                            id="receptor_dni"
                                            name="receptor_dni"
                                            inputMode="numeric"
                                            maxLength={8}
                                            required
                                        />
                                        {errors.receptor_dni && (
                                            <p className="text-destructive-strong text-sm">
                                                {errors.receptor_dni}
                                            </p>
                                        )}
                                    </div>
                                    <label className="flex items-start gap-3 text-sm">
                                        <input
                                            type="checkbox"
                                            name="conformidad_aceptada"
                                            value="1"
                                            required
                                            className="mt-1"
                                        />
                                        <span>
                                            El cliente recibió los equipos y
                                            está conforme con el servicio.
                                        </span>
                                    </label>
                                    {errors.conformidad_aceptada && (
                                        <p className="text-destructive-strong text-sm">
                                            {errors.conformidad_aceptada}
                                        </p>
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        Registrar entrega conforme
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </Card>
            </div>
        </VendedorLayout>
    );
}
