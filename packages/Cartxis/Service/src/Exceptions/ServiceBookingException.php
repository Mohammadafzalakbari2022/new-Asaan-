<?php

declare(strict_types=1);

namespace Cartxis\Service\Exceptions;

use RuntimeException;

/**
 * A booking request that cannot go ahead, with the field it belongs to.
 *
 * The reason is written for the customer and the field is carried alongside it,
 * so the message can be shown under the right input without anyone having to
 * guess which words in a sentence hint at which box. English wording can change
 * freely; the field cannot.
 */
class ServiceBookingException extends RuntimeException
{
    public function __construct(string $message, public string $field = 'form')
    {
        parent::__construct($message);
    }

    public static function date(string $message): self
    {
        return new self($message, 'scheduled_date');
    }

    public static function slot(string $message): self
    {
        return new self($message, 'scheduled_slot');
    }

    public static function form(string $message): self
    {
        return new self($message, 'form');
    }
}
