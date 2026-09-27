import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-muted/40 flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-3"
                        >
                            <img
                                src="/brand/logo-icon.png"
                                alt="Bruce Fire"
                                className="size-14 drop-shadow-sm"
                            />
                            <span className="text-foreground font-['Oswald',sans-serif] text-[13px] font-bold tracking-[0.1em] uppercase">
                                Bruce Fire
                            </span>
                        </Link>

                        <div className="space-y-1.5 text-center">
                            <h1 className="text-foreground text-xl font-semibold">
                                {title}
                            </h1>
                            <p className="text-muted-foreground text-center text-sm">
                                {description}
                            </p>
                        </div>
                    </div>

                    <div className="border-border bg-card rounded-[14px] border p-6 shadow-sm">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
