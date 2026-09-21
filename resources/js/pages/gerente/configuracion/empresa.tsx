import { router, useForm, usePage } from '@inertiajs/react';
import { Building2, CreditCard, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import cuentasBancarias from '@/routes/gerente/configuracion/cuentas-bancarias';
import empresa from '@/routes/gerente/configuracion/empresa';
import GerenteLayout from '@/layouts/gerente-layout';
import type { Team } from '@/types';

type Company = {
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string;
    direccion: string | null;
    ubigeo: string | null;
    departamento: string | null;
    provincia: string | null;
    distrito: string | null;
    telefono: string | null;
    email: string | null;
    leyenda_pie: string | null;
    cuenta_detraccion: string | null;
    logo_url: string | null;
};

type BankAccount = {
    id: number;
    banco: string;
    titular: string;
    numero_cuenta: string;
    cci: string | null;
    moneda: string;
    activo: boolean;
};

type Props = { company: Company; bankAccounts: BankAccount[] };

function field(errors: Record<string, string>, name: string) {
    return errors[name] ? <p className="mt-1 text-[11.5px] text-[#B91C1C]">{errors[name]}</p> : null;
}

export default function EmpresaConfiguracion({ company, bankAccounts }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? (typeof window !== 'undefined' ? window.location.pathname.split('/')[1] : '');
    const [logoPreview, setLogoPreview] = useState<string | null>(company.logo_url);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        razon_social: company.razon_social,
        nombre_comercial: company.nombre_comercial ?? '',
        ruc: company.ruc,
        direccion: company.direccion ?? '',
        ubigeo: company.ubigeo ?? '',
        departamento: company.departamento ?? '',
        provincia: company.provincia ?? '',
        distrito: company.distrito ?? '',
        telefono: company.telefono ?? '',
        email: company.email ?? '',
        leyenda_pie: company.leyenda_pie ?? '',
        cuenta_detraccion: company.cuenta_detraccion ?? '',
        logo: null as File | null,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(empresa.update.url({ current_team: teamSlug }), { forceFormData: true, preserveScroll: true });
    }

    function onLogoChange(event: React.ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0] ?? null;
        form.setData('logo', file);

        if (file) {
            setLogoPreview(URL.createObjectURL(file));
        }
    }

    const bankForm = useForm({ banco: '', titular: company.razon_social, numero_cuenta: '', cci: '', moneda: 'PEN' });

    function addBankAccount(event: React.FormEvent) {
        event.preventDefault();
        bankForm.post(cuentasBancarias.store.url({ current_team: teamSlug }), {
            preserveScroll: true,
            onSuccess: () => bankForm.reset('banco', 'numero_cuenta', 'cci'),
        });
    }

    function deleteBankAccount(id: number) {
        router.delete(cuentasBancarias.destroy.url({ current_team: teamSlug, cuenta_bancaria: id }), { preserveScroll: true });
    }

    return (
        <GerenteLayout title="Datos de la empresa">
            <div className="flex flex-col gap-5">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-[11px] bg-[#FBE7E7] text-[#E31E24]">
                        <Building2 className="size-5" />
                    </div>
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-[20px] font-semibold uppercase text-[#201F1D]">Datos de la empresa</h1>
                        <p className="text-[12px] text-[#8A8680]">Se usan en todos los comprobantes (Factura, Boleta, Nota) y certificados.</p>
                    </div>
                </div>

                <Card className="gap-4 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex items-center gap-4">
                            <div className="flex size-20 items-center justify-center overflow-hidden rounded-[10px] border border-dashed border-[#D9D5CE] bg-[#FAFAF8]">
                                {logoPreview ? (
                                    // eslint-disable-next-line @next/next/no-img-element
                                    <img src={logoPreview} alt="Logo de la empresa" className="max-h-full max-w-full object-contain" />
                                ) : (
                                    <span className="text-[10px] text-[#8A8680]">Sin logo</span>
                                )}
                            </div>
                            <div>
                                <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={onLogoChange} />
                                <Button type="button" variant="outline" onClick={() => fileInputRef.current?.click()} className="h-9 rounded-[9px] border-[#E4E1DC] bg-white text-[#4A4742] shadow-none">
                                    <Upload className="size-3.5" /> Subir logo
                                </Button>
                                <p className="mt-1 text-[11px] text-[#8A8680]">PNG o JPG, máx. 2MB.</p>
                            </div>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Razón social</Label>
                                <Input value={form.data.razon_social} onChange={(e) => form.setData('razon_social', e.target.value)} />
                                {field(form.errors, 'razon_social')}
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Nombre comercial</Label>
                                <Input value={form.data.nombre_comercial} onChange={(e) => form.setData('nombre_comercial', e.target.value)} />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">RUC</Label>
                                <Input value={form.data.ruc} onChange={(e) => form.setData('ruc', e.target.value)} maxLength={11} />
                                {field(form.errors, 'ruc')}
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Teléfono</Label>
                                <Input value={form.data.telefono} onChange={(e) => form.setData('telefono', e.target.value)} />
                            </div>
                            <div className="md:col-span-2">
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Dirección</Label>
                                <Input value={form.data.direccion} onChange={(e) => form.setData('direccion', e.target.value)} />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Ubigeo (6 dígitos SUNAT)</Label>
                                <Input value={form.data.ubigeo} onChange={(e) => form.setData('ubigeo', e.target.value)} maxLength={6} />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Email</Label>
                                <Input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Departamento</Label>
                                <Input value={form.data.departamento} onChange={(e) => form.setData('departamento', e.target.value)} />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Provincia</Label>
                                <Input value={form.data.provincia} onChange={(e) => form.setData('provincia', e.target.value)} />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Distrito</Label>
                                <Input value={form.data.distrito} onChange={(e) => form.setData('distrito', e.target.value)} />
                            </div>
                            <div className="md:col-span-2">
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Cuenta de detracción (Banco de la Nación)</Label>
                                <Input
                                    value={form.data.cuenta_detraccion}
                                    onChange={(e) => form.setData('cuenta_detraccion', e.target.value)}
                                    placeholder="00-000-000000"
                                />
                                <p className="mt-1 text-[11px] text-[#8A8680]">Obligatoria en comprobantes con detracción (servicios sobre S/ 700).</p>
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Leyenda de pie (opcional)</Label>
                            <Textarea
                                value={form.data.leyenda_pie}
                                onChange={(e) => form.setData('leyenda_pie', e.target.value)}
                                placeholder='Ej: "Encomienda al Señor todo lo que haces, y tus planes se cumplirán."'
                                rows={2}
                            />
                        </div>

                        <div>
                            <Button type="submit" disabled={form.processing} className="h-10 rounded-[9px] bg-[#18181B] px-4 text-[13px] font-bold text-white shadow-none hover:bg-[#27272A]">
                                {form.processing ? 'Guardando…' : 'Guardar cambios'}
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card className="gap-4 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <div className="flex items-center gap-2">
                        <CreditCard className="size-4 text-[#1E8E5A]" />
                        <h2 className="font-['Oswald',sans-serif] text-[16px] font-semibold uppercase">Cuentas bancarias</h2>
                    </div>

                    <div className="flex flex-col gap-2">
                        {bankAccounts.length === 0 ? (
                            <p className="text-[12px] text-[#8A8680]">Sin cuentas registradas. Se imprimen en todos los comprobantes.</p>
                        ) : (
                            bankAccounts.map((account) => (
                                <div key={account.id} className="flex items-center justify-between rounded-[9px] border border-[#E7E4DE] px-3 py-2 text-[12px]">
                                    <div>
                                        <span className="font-bold">{account.banco}</span> — {account.titular} — {account.numero_cuenta}
                                        {account.cci ? ` — CCI: ${account.cci}` : ''}
                                    </div>
                                    <Button type="button" variant="outline" size="icon" onClick={() => deleteBankAccount(account.id)} className="size-7 rounded-[7px] border-[#E7E4DE] bg-white text-[#B91C1C] shadow-none">
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>

                    <form onSubmit={addBankAccount} className="grid gap-2 border-t border-[#F1EFEC] pt-3 md:grid-cols-5">
                        <Input placeholder="Banco (ej. BCP)" value={bankForm.data.banco} onChange={(e) => bankForm.setData('banco', e.target.value)} />
                        <Input placeholder="Titular" value={bankForm.data.titular} onChange={(e) => bankForm.setData('titular', e.target.value)} />
                        <Input placeholder="N° de cuenta" value={bankForm.data.numero_cuenta} onChange={(e) => bankForm.setData('numero_cuenta', e.target.value)} />
                        <Input placeholder="CCI (opcional)" value={bankForm.data.cci} onChange={(e) => bankForm.setData('cci', e.target.value)} />
                        <Button type="submit" disabled={bankForm.processing} className="h-10 rounded-[9px] bg-[#1E8E5A] text-white shadow-none hover:bg-[#17714A]">
                            Agregar
                        </Button>
                    </form>
                </Card>
            </div>
        </GerenteLayout>
    );
}
