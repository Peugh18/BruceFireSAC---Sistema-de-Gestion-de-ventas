<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\UpdateCompanySettingRequest;
use App\Models\CompanyBankAccount;
use App\Models\CompanySetting;
use App\Models\Signer;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingController extends Controller
{
    public function edit(Team $current_team): Response
    {
        $company = CompanySetting::current();

        return Inertia::render('gerente/configuracion/empresa', [
            'company' => [
                'razon_social' => $company->razon_social,
                'nombre_comercial' => $company->nombre_comercial,
                'ruc' => $company->ruc,
                'direccion' => $company->direccion,
                'ubigeo' => $company->ubigeo,
                'ubicacion' => $company->ubicacion?->paraFormulario(),
                'telefono' => $company->telefono,
                'email' => $company->email,
                'leyenda_pie' => $company->leyenda_pie,
                'cuenta_detraccion' => $company->cuenta_detraccion,
                'logo_url' => $company->logoUrl(),
            ],
            'bankAccounts' => CompanyBankAccount::query()->orderBy('orden')->get(),
            'firmasPendientes' => Signer::query()->where('activo', true)->whereNull('firma_path')->count(),
        ]);
    }

    public function update(Team $current_team, UpdateCompanySettingRequest $request): RedirectResponse
    {
        $company = CompanySetting::current();
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('company', 'public');
        }

        $oldData = $company->only(array_keys($data));
        $company->update($data);

        AuditLogger::log(
            action: 'configuracion.empresa_actualizada',
            entity: $company,
            oldValues: $oldData,
            newValues: $data
        );

        return back();
    }
}
