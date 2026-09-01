<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Stripe\Exception\ApiErrorException;

/** Finds a subscription from the customer rather than from its own id. */
interface CustomerSubscriptionReader
{
    /** @throws ApiErrorException */
    public function latestSubscriptionFor(string $customerId): ?SubscriptionSnapshot;
}
