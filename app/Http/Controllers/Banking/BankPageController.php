<?php

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BankPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('bank.view');

        return Inertia::render('Ameise/Bank/Index', [
            'permissions' => [
                'view' => Gate::allows('bank.view'),
                'view_sensitive' => Gate::allows('bank.view_sensitive'),
                'sync' => Gate::allows('bank.sync'),
                'reconcile' => Gate::allows('bank.reconcile'),
                'manage_connection' => Gate::allows('bank.manage_connection'),
                'manage_payment_drafts' => Gate::allows('bank.manage_payment_drafts'),
                'view_audit' => Gate::allows('bank.view_audit'),
            ],
            'readOnly' => (bool) config('banking.sber.read_only'),
            'bankTimezone' => (string) config('banking.bank_timezone', 'Europe/Moscow'),
        ]);
    }
}
