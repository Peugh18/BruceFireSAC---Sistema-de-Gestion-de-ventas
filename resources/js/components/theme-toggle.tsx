import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

interface ThemeToggleProps {
    className?: string;
}

export function ThemeToggle({ className = '' }: ThemeToggleProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const isDark = resolvedAppearance === 'dark';

    const toggleTheme = () => {
        updateAppearance(isDark ? 'light' : 'dark');
    };

    return (
        <button
            type="button"
            onClick={toggleTheme}
            className={`border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground relative flex size-[38px] shrink-0 items-center justify-center rounded-[9px] border transition-colors ${className}`}
            title={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            aria-label={
                isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'
            }
        >
            {isDark ? (
                <Sun
                    className="size-4 text-amber-400 transition-transform hover:rotate-45"
                    strokeWidth={2}
                />
            ) : (
                <Moon
                    className="text-muted-foreground size-4 transition-transform hover:-rotate-12"
                    strokeWidth={2}
                />
            )}
        </button>
    );
}
