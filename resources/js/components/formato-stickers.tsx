import { useId } from 'react';

export type FormatoStickers = 'rollo' | 'a4';

export default function SelectorFormatoStickers({
    value,
    onChange,
}: {
    value: FormatoStickers;
    onChange: (formato: FormatoStickers) => void;
}) {
    const id = useId();

    return (
        <div className="flex flex-wrap items-center gap-2 text-xs">
            <label htmlFor={id} className="text-muted-foreground font-medium">
                Formato de impresión
            </label>
            <select
                id={id}
                name="formato"
                value={value}
                onChange={(event) =>
                    onChange(event.target.value as FormatoStickers)
                }
                className="border-input bg-background text-foreground focus-visible:ring-ring min-h-11 max-w-full rounded-lg border px-3 focus-visible:ring-2"
            >
                <option value="rollo">Rollo 80 × 50 mm</option>
                <option value="a4">Hoja A4 · 10 etiquetas (2 × 5)</option>
            </select>
        </div>
    );
}
