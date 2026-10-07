import { usePage } from '@inertiajs/react';

/**
 * Agentes extintores de la lista única del sistema (App\Enums\EquipmentType).
 * El técnico elige de aquí; no se escribe a mano.
 */
export function useAgentesExtintor(): string[] {
    return usePage().props.agentesExtintor ?? [];
}

export default function OpcionesAgente({ vacia }: { vacia?: string }) {
    const agentes = useAgentesExtintor();

    return (
        <>
            {vacia !== undefined ? <option value="">{vacia}</option> : null}
            {agentes.map((agente) => (
                <option key={agente} value={agente}>
                    {agente}
                </option>
            ))}
        </>
    );
}
