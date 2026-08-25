<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TaskNotReadyToComplete extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        /** @var array<string, list<string>> */
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function missingField(string $field): self
    {
        return new self(
            __('The :field field is required before this task can be completed.', ['field' => $field]),
            'task.missing_required_field',
            ['fields' => [$field => [__('The :field field is required.', ['field' => $field])]]],
        );
    }

    public static function missingAttachment(string $type): self
    {
        $label = str_replace('_', ' ', $type);

        return new self(
            __('The :type attachment is required before this task can be completed.', ['type' => $label]),
            'task.missing_required_attachment',
            ['attachments.'.$type => [__('A :type file must be attached.', ['type' => $label])]],
        );
    }
}
