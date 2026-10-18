import { router, usePage } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useState } from 'react';

import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { leido } from '@/routes/ordenes/mensajes';
import type { Team } from '@/types';

/** Deja leídos los mensajes de la orden para quien la está viendo. */
export function marcarLeido(equipo: string, ordenId: number) {
    router.post(
        leido.url({ current_team: equipo, service_order: ordenId }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * Chat de la orden en un botón flotante: el vendedor, planta y campo ven la
 * misma conversación. Al abrirlo, los mensajes nuevos quedan leídos.
 */
export default function ChatFlotante({
    ordenId,
    codigo,
    conversacion,
    nota,
    inline = false,
}: {
    ordenId: number;
    codigo: string;
    conversacion: ConversacionProps;
    nota?: string | null;
    inline?: boolean;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const [abierto, setAbierto] = useState(false);
    const nuevos = conversacion.no_leidos ?? 0;

    const abrir = (valor: boolean) => {
        setAbierto(valor);

        if (valor && nuevos > 0) {
            marcarLeido(currentTeam?.slug ?? '', ordenId);
        }
    };

    return (
        <Sheet open={abierto} onOpenChange={abrir}>
            <button
                type="button"
                onClick={() => abrir(true)}
                aria-label={
                    nuevos > 0
                        ? `Abrir el chat de la orden, ${nuevos} mensajes nuevos`
                        : 'Abrir el chat de la orden'
                }
                className={`focus-visible:ring-ring flex shrink-0 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none ${inline ? 'border-border bg-card text-foreground relative size-11 border shadow-sm' : 'bg-foreground text-background fixed right-4 bottom-24 z-40 size-14 shadow-lg'}`}
            >
                <MessageSquare className="size-6" aria-hidden="true" />
                {nuevos > 0 && (
                    <span className="bg-primary text-primary-foreground border-background absolute -top-0.5 -right-0.5 flex min-w-6 items-center justify-center rounded-full border-2 px-1 text-xs font-bold">
                        {nuevos}
                    </span>
                )}
            </button>
            <SheetContent
                side="bottom"
                className="mx-auto flex h-[85dvh] max-w-2xl flex-col gap-3 rounded-t-3xl p-4"
            >
                <div>
                    <SheetTitle className="text-base font-bold">
                        Chat de la orden {codigo}
                    </SheetTitle>
                    <SheetDescription className="text-xs">
                        El vendedor, planta y campo ven lo mismo.
                    </SheetDescription>
                </div>
                <div className="min-h-0 flex-1">
                    <ConversacionOrden
                        ordenId={ordenId}
                        conversacion={conversacion}
                        nota={nota}
                        panel
                    />
                </div>
            </SheetContent>
        </Sheet>
    );
}
