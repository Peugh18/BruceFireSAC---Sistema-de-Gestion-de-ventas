import { History } from 'lucide-react';

import { Card } from '@/components/ui/card';
import { fechaHora } from '@/lib/utils';

export type HistorialItem = {
    fecha: string | null;
    texto: string;
    usuario: string | null;
};

export function HistorialActividad({
    historial,
}: {
    historial: HistorialItem[];
}) {
    return (
        <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
            <div className="border-border flex items-center gap-2 border-b pb-3">
                <History className="text-primary-strong size-4" />
                <h2 className="text-foreground text-base font-bold">
                    Historial de actividad
                </h2>
            </div>
            {historial.length === 0 ? (
                <p className="text-muted-foreground py-6 text-center text-xs">
                    Sin eventos registrados.
                </p>
            ) : (
                <ol className="border-border relative mt-1 space-y-4 border-l pl-4">
                    {historial.map((evento, indice) => (
                        <li key={indice} className="relative">
                            <span className="bg-primary absolute top-1.5 -left-[21px] size-2 rounded-full" />
                            <p className="text-foreground text-xs font-semibold">
                                {evento.texto}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {fechaHora(evento.fecha)}
                                {evento.usuario
                                    ? ` · Por ${evento.usuario}`
                                    : ''}
                            </p>
                        </li>
                    ))}
                </ol>
            )}
        </Card>
    );
}
