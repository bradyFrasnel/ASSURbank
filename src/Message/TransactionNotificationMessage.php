<?php

namespace App\Message;

class TransactionNotificationMessage
{
    public function __construct(
        public readonly int $transactionId,
    ) {}
}
