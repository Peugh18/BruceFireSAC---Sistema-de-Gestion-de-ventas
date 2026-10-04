<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreCompanyBankAccountRequest;
use App\Http\Requests\Gerente\UpdateCompanyBankAccountRequest;
use App\Models\CompanyBankAccount;
use App\Models\CompanySetting;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class CompanyBankAccountController extends Controller
{
    public function store(Team $current_team, StoreCompanyBankAccountRequest $request): RedirectResponse
    {
        CompanyBankAccount::create([
            ...$request->validated(),
            'orden' => CompanyBankAccount::max('orden') + 1,
        ]);

        return back();
    }

    public function update(Team $current_team, UpdateCompanyBankAccountRequest $request, CompanyBankAccount $cuenta_bancaria): RedirectResponse
    {
        $cuenta_bancaria->update($request->validated());

        return back();
    }

    public function destroy(Team $current_team, CompanyBankAccount $cuenta_bancaria): RedirectResponse
    {
        $cuenta_bancaria->delete();

        // Los comprobantes ya generados se vuelven a dibujar sin esta cuenta.
        CompanySetting::current()->touch();

        return back();
    }
}
