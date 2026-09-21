<?php

namespace App\Services\Conversations;

class PotentialCustomerDetector
{
    public function isPotentialCustomer(string $message): bool
    {
        return preg_match(
            '/\b(?:quote|pricing|prices?|purchase|order|availability|available|moq|minimum order quantity|shipping|delivery|sample|wholesale|distributor(?:ship)?)\b/i',
            $message,
        ) === 1;
    }
}
