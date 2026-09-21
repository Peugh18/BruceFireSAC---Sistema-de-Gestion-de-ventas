import AlmacenLayout from '@/layouts/almacen-layout';
import { Package } from 'lucide-react';

export default function AlmacenPlaceholder() {
    return (
        <AlmacenLayout title="Dashboard de Almacén">
            <div className="flex min-h-[400px] flex-col items-center justify-center rounded-[14px] border border-dashed border-[#D5D2CB] bg-white p-12 text-center">
                <div className="flex size-14 items-center justify-center rounded-2xl bg-[#F3F1ED] text-[#E31E24]">
                    <Package className="size-7" />
                </div>
                <h2 className="mt-4 text-lg font-bold text-[#201F1D]">
                    Módulo de Almacén
                </h2>
                <p className="mt-1.5 max-w-sm text-sm text-[#8A8680]">
                    Scaffolding del rol Almacén completado. Los módulos operativos se construirán en las siguientes fases del plan.
                </p>
            </div>
        </AlmacenLayout>
    );
}
