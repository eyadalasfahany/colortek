<?php

declare(strict_types=1);

namespace App\Gateways\Odoo\Exceptions;

use RuntimeException;

final class OdooDriverNotImplemented extends RuntimeException
{
    public static function forMethod(string $method): self
    {
        return new self(sprintf(
            'The HTTP Odoo driver is a Phase 2 stub; %s is not implemented. Set services.odoo.driver=fake.',
            $method,
        ));
    }
}
