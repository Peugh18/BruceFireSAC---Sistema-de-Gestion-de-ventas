import BotonLista from '@/components/lista-administrable';
import capacidadesRutas from '@/routes/capacidades';

/** Botón para agregar, corregir o quitar capacidades (30 lb, 50 kg…), como el de agentes. */
export default function BotonCapacidades() {
    return (
        <BotonLista
            config={{
                titulo: 'Capacidades',
                singular: 'capacidad',
                descripcion:
                    'Las que salen al contar o registrar un extintor. Quitar una no cambia los extintores que ya la tienen.',
                ejemplo: 'Número y unidad, por ejemplo 30 lb',
                clave: 'capacidades',
                prop: 'capacidadesExtintor',
                rutas: {
                    index: capacidadesRutas.index.url(),
                    store: capacidadesRutas.store.url(),
                    update: (id) => capacidadesRutas.update.url(id),
                    destroy: (id) => capacidadesRutas.destroy.url(id),
                },
            }}
        />
    );
}
