import { UserRound } from 'lucide-react';
import ChatFlotante from '@/components/chat-flotante';
import type { ConversacionProps } from '@/components/conversacion-orden';
import { usePlantaPerfil } from '@/hooks/use-planta-perfil';

export default function BarraOrdenCampo({
    ordenId,
    codigo,
    conversacion,
}: {
    ordenId: number;
    codigo: string;
    conversacion: ConversacionProps;
}) {
    const { abrirPerfil } = usePlantaPerfil();
    return (
        <div className="border-border bg-card mb-4 flex items-center justify-between gap-3 rounded-3xl border px-4 py-3">
            <span className="text-muted-foreground text-sm">
                Conversación de la orden
            </span>
            <div className="flex shrink-0 items-center gap-2">
                <ChatFlotante
                    ordenId={ordenId}
                    codigo={codigo}
                    conversacion={conversacion}
                    inline
                />
                <button
                    type="button"
                    aria-label="Abrir mi perfil"
                    onClick={abrirPerfil}
                    className="border-border bg-card focus-visible:ring-ring flex size-11 items-center justify-center rounded-full border focus-visible:ring-2 focus-visible:outline-none"
                >
                    <UserRound className="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>
    );
}
