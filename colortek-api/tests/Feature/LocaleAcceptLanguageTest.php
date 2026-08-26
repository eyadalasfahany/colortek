<?php

declare(strict_types=1);

it('returns Arabic validation messages when Accept-Language is ar', function (): void {
    $response = $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);

    $emailError = $response->json('errors.email.0');
    $passwordError = $response->json('errors.password.0');

    expect($emailError)->toBeString()->not->toContain('must be')
        ->and($passwordError)->toBeString()->not->toContain('required')
        ->and($emailError.$passwordError)->toMatch('/[\x{0600}-\x{06FF}]/u');
});

it('returns English validation messages when Accept-Language is en', function (): void {
    $response = $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);

    expect($response->json('errors.email.0'))->toContain('email')
        ->and($response->json('errors.password.0'))->toContain('required');
});
