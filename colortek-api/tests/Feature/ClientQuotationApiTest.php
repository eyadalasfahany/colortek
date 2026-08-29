<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

function actingAsSales(): User
{
    $user = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($user);

    return $user;
}

it('lists clients paginated', function (): void {
    actingAsSales();
    Client::factory()->count(3)->create();

    $this->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']]);
});

it('searches clients by name', function (): void {
    actingAsSales();
    Client::factory()->create(['name' => 'Omega Interiors']);
    Client::factory()->create(['name' => 'Delta Contracting']);

    $this->getJson('/api/v1/clients?q=Omega')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Omega Interiors');
});

it('creates a client', function (): void {
    actingAsSales();

    $this->postJson('/api/v1/clients', [
        'name' => 'Omega',
        'contact_person' => 'Mahmoud Eslily',
        'phone' => '+20 100 000 0000',
        'email' => 'omega@example.test',
        'address' => 'New Giza',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Omega')
        ->assertJsonPath('data.contact_person', 'Mahmoud Eslily');

    $this->assertDatabaseHas('clients', ['name' => 'Omega', 'address' => 'New Giza']);
});

it('rejects a client without a name', function (): void {
    actingAsSales();

    $this->postJson('/api/v1/clients', ['email' => 'x@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rejects a duplicate odoo client id', function (): void {
    actingAsSales();
    Client::factory()->create(['odoo_client_id' => 'ODOO-1']);

    $this->postJson('/api/v1/clients', ['name' => 'Dup', 'odoo_client_id' => 'ODOO-1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('odoo_client_id');
});

it('updates a client', function (): void {
    actingAsSales();
    $client = Client::factory()->create(['name' => 'Old']);

    $this->patchJson("/api/v1/clients/{$client->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');
});

it('returns a friendly 404 for a missing client', function (): void {
    actingAsSales();

    $this->getJson('/api/v1/clients/9999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Client not found');
});

it('forbids client creation without client.manage', function (): void {
    $user = User::factory()->create();
    $user->assignRole('viewer');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/clients', ['name' => 'Nope'])->assertForbidden();
});

it('creates a quotation with the number typed by sales', function (): void {
    actingAsSales();
    $client = Client::factory()->create();

    $this->postJson('/api/v1/quotations', [
        'number' => 'SO9577',
        'client_id' => $client->id,
        'total_value' => 480000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.number', 'SO9577')
        ->assertJsonPath('data.currency', 'EGP')
        ->assertJsonPath('data.status', 'draft');
});

it('rejects a duplicate quotation number', function (): void {
    actingAsSales();
    $client = Client::factory()->create();
    Quotation::factory()->create(['number' => 'SO9577', 'client_id' => $client->id]);

    $this->postJson('/api/v1/quotations', [
        'number' => 'SO9577',
        'client_id' => $client->id,
        'total_value' => 1000,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('number');
});

it('filters quotations by client', function (): void {
    actingAsSales();
    $a = Client::factory()->create();
    $b = Client::factory()->create();
    Quotation::factory()->count(2)->create(['client_id' => $a->id]);
    Quotation::factory()->create(['client_id' => $b->id]);

    $this->getJson("/api/v1/quotations?client_id={$a->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('stops a non-super-admin editing a locked quotation', function (): void {
    actingAsSales();
    $quotation = Quotation::factory()->locked()->create();

    $this->patchJson("/api/v1/quotations/{$quotation->id}", ['total_value' => 1])
        ->assertForbidden();
});

it('lets a super admin correct a locked quotation', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    Sanctum::actingAs($admin);
    $quotation = Quotation::factory()->locked()->create();

    $this->patchJson("/api/v1/quotations/{$quotation->id}", ['total_value' => 1234])
        ->assertOk()
        ->assertJsonPath('data.total_value', '1234.00');
});

it('creates a project from a client and quotation', function (): void {
    $sales = actingAsSales();
    $client = Client::factory()->create(['name' => 'Omega']);

    $quotationResponse = $this->postJson('/api/v1/quotations', [
        'number' => 'SO9577',
        'client_id' => $client->id,
        'total_value' => 480000,
    ])->assertCreated();
    $quotation = $quotationResponse->json('data');

    $this->postJson('/api/v1/projects', [
        'reference' => 'SO9577',
        'name' => 'Omega — Mahmoud Eslily',
        'client_id' => $client->id,
        'quotation_id' => $quotation['id'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.reference', 'SO9577')
        ->assertJsonPath('data.stage', 'lead');

    $this->assertDatabaseHas('projects', [
        'reference' => 'SO9577',
        'client_id' => $client->id,
        'sales_user_id' => $sales->id,
    ]);
});
