<?php

declare(strict_types=1);

namespace App\Gateways\Odoo;

use App\Gateways\Odoo\Data\ClientData;
use App\Gateways\Odoo\Data\JournalData;
use App\Gateways\Odoo\Data\PaymentData;
use App\Gateways\Odoo\Data\PushResult;
use App\Gateways\Odoo\Data\QuotationData;
use Illuminate\Support\Collection;

/**
 * The single seam between this system and Odoo.
 *
 * Nothing outside `app/Gateways/Odoo/` may know Odoo exists.
 * `specs/02-architecture.md` §5, `specs/13-odoo-gateway-and-seed-data.md`.
 */
interface OdooGateway
{
    public function findClient(string $odooId): ?ClientData;

    /** @return Collection<int, ClientData> */
    public function searchClients(string $query): Collection;

    public function findQuotation(string $number): ?QuotationData;

    public function pushJournal(JournalData $journal): PushResult;

    public function pushPaymentConfirmation(PaymentData $payment): PushResult;
}
