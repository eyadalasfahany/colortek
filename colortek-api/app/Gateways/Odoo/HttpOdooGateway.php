<?php

declare(strict_types=1);

namespace App\Gateways\Odoo;

use App\Gateways\Odoo\Data\ClientData;
use App\Gateways\Odoo\Data\JournalData;
use App\Gateways\Odoo\Data\PaymentData;
use App\Gateways\Odoo\Data\PushResult;
use App\Gateways\Odoo\Data\QuotationData;
use App\Gateways\Odoo\Exceptions\OdooDriverNotImplemented;
use Illuminate\Support\Collection;

/**
 * Phase 2 driver. Deliberately unimplemented: it exists so the binding and the
 * config switch are real, and so selecting it fails loudly rather than silently
 * degrading to fake behaviour.
 * `specs/13-odoo-gateway-and-seed-data.md` §1.
 */
final class HttpOdooGateway implements OdooGateway
{
    public function findClient(string $odooId): ?ClientData
    {
        throw OdooDriverNotImplemented::forMethod(__METHOD__);
    }

    /** @return Collection<int, ClientData> */
    public function searchClients(string $query): Collection
    {
        throw OdooDriverNotImplemented::forMethod(__METHOD__);
    }

    public function findQuotation(string $number): ?QuotationData
    {
        throw OdooDriverNotImplemented::forMethod(__METHOD__);
    }

    public function pushJournal(JournalData $journal): PushResult
    {
        throw OdooDriverNotImplemented::forMethod(__METHOD__);
    }

    public function pushPaymentConfirmation(PaymentData $payment): PushResult
    {
        throw OdooDriverNotImplemented::forMethod(__METHOD__);
    }
}
