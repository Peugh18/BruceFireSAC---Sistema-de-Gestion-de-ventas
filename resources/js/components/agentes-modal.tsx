import BotonLista from '@/components/lista-administrable';
import agentesRutas from '@/routes/agentes';

/**
 * Botón chico junto al selector de agente: abre un modal para agregar,
 * renombrar o quitar agentes sin salir del formulario.
 */
export default function BotonAgentes() {
    return (
        <BotonLista
            config={{
                titulo: 'Agentes',
                singular: 'agente',
                descripcion:
                    'Los que salen al registrar un extintor. Quitar uno no cambia los extintores que ya lo tienen.',
                ejemplo: 'Nombre, por ejemplo Halotrón',
                clave: 'agentes',
                prop: 'agentesExtintor',
                conTipo: true,
                rutas: {
                    index: agentesRutas.index.url(),
                    store: agentesRutas.store.url(),
                    update: (id) => agentesRutas.update.url(id),
                    destroy: (id) => agentesRutas.destroy.url(id),
                },
            }}
        />
    );
}
