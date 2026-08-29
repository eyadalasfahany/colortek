<?php

declare(strict_types=1);

use App\Gateways\Odoo\Data\JournalData;
use App\Gateways\Odoo\Data\PaymentData;
use App\Gateways\Odoo\Exceptions\OdooDriverNotImplemented;
use App\Gateways\Odoo\FakeOdooGateway;
use App\Gateways\Odoo\HttpOdooGateway;
use App\Gateways\Odoo\OdooGateway;
use App\Models\Client;
use App\Models\Journal;
use App\Models\OdooSyncLog;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

it('binds the fake driver by default', function (): void {
    expect(app(OdooGateway::class)::class)->toBe(FakeOdooGateway::class);
});

it('binds the http driver when configured', function (): void {
    config(['services.odoo.driver' => 'http']);
    app()->forgetInstance(OdooGateway::class);

    expect(app(OdooGateway::class)::class)->toBe(HttpOdooGateway::class);
});

it('throws rather than silently degrading when the http stub is used', function (): void {
    (new HttpOdooGateway)->findQuotation('SO9577');
})->throws(OdooDriverNotImplemented::class);

it('finds a client by its odoo id', function (): void {
    $client = Client::factory()->create(['odoo_client_id' => 'ODOO-42', 'name' => 'Omega']);

    $found = app(OdooGateway::class)->findClient('ODOO-42');

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Omega')
        ->and($found->localId)->toBe($client->id);
});

it('returns null for an unknown odoo client id', function (): void {
    expect(app(OdooGateway::class)->findClient('NOPE'))->toBeNull();
});

it('searches clients by name, contact and email', function (): void {
    Client::factory()->create(['name' => 'Omega Interiors']);
    Client::factory()->create(['name' => 'Delta Contracting', 'contact_person' => 'Omega Rep']);
    Client::factory()->create(['name' => 'Unrelated Co']);

    expect(app(OdooGateway::class)->searchClients('Omega'))->toHaveCount(2);
});

it('finds a quotation by number', function (): void {
    Quotation::factory()->create(['number' => 'SO9577', 'total_value' => 480000]);

    $found = app(OdooGateway::class)->findQuotation('SO9577');

    expect($found)->not->toBeNull()
        ->and($found->number)->toBe('SO9577')
        ->and($found->totalValue)->toBe('480000.00');
});

it('records a simulated journal push instead of sending it', function (): void {
    $journal = Journal::factory()->create(['status' => 'submitted', 'total_amount' => 1000]);

    $result = app(OdooGateway::class)->pushJournal(JournalData::fromModel($journal));

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('simulated');

    $log = OdooSyncLog::query()->firstOrFail();
    expect($log->operation)->toBe('push_journal')
        ->and($log->driver)->toBe('fake')
        ->and($log->status)->toBe('simulated')
        ->and($log->subject_id)->toBe($journal->id)
        ->and($log->payload['total_amount'])->toBe('1000.00');
});

it('does not double-post the same journal state on retry', function (): void {
    $journal = Journal::factory()->create(['status' => 'submitted']);
    $data = JournalData::fromModel($journal);

    app(OdooGateway::class)->pushJournal($data);
    $second = app(OdooGateway::class)->pushJournal($data);

    expect($second->status)->toBe('duplicate')
        ->and(OdooSyncLog::query()->count())->toBe(1);
});

it('pushes again once the journal reaches a new state', function (): void {
    $journal = Journal::factory()->create(['status' => 'submitted']);
    app(OdooGateway::class)->pushJournal(JournalData::fromModel($journal));

    $journal->update(['status' => 'accounted']);
    app(OdooGateway::class)->pushJournal(JournalData::fromModel($journal->fresh()));

    expect(OdooSyncLog::query()->count())->toBe(2);
});

it('records the payment payload a confirmation would have sent', function (): void {
    $project = Project::factory()->create();
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'installment_number' => 1,
        'amount' => 240000,
        'status' => 'confirmed',
    ]);

    $result = app(OdooGateway::class)->pushPaymentConfirmation(PaymentData::fromModel($payment));

    expect($result->status)->toBe('simulated');

    $log = OdooSyncLog::query()->firstOrFail();
    expect($log->operation)->toBe('push_payment_confirmation')
        ->and($log->payload['amount'])->toBe('240000.00')
        ->and($log->payload['project_reference'])->toBe($project->reference)
        ->and($log->payload['installment_number'])->toBe(1);
});

it('exposes the sync log to admins', function (): void {
    $journal = Journal::factory()->create(['status' => 'submitted']);
    app(OdooGateway::class)->pushJournal(JournalData::fromModel($journal));

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/odoo-sync-log')
        ->assertOk()
        ->assertJsonPath('data.0.operation', 'push_journal')
        ->assertJsonPath('data.0.status', 'simulated')
        ->assertJsonPath('data.0.subject_type', 'Journal');
});

it('hides the sync log from a user without settings.manage', function (): void {
    // Admin endpoints abort(404) rather than 403 so their existence is not
    // disclosed — see AuthorizesAdminAccess.
    $user = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/odoo-sync-log')->assertNotFound();
});
