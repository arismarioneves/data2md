<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Stripe\Exception\ApiErrorException;

/**
 * Reads current subscription state.
 *
 * An interface so the event handler can be exercised without a network, and so
 * the tests for delivery ordering can hand it whatever Stripe would have said.
 */
interface SubscriptionReader
{
    /** @throws ApiErrorException */
    public function subscription(string $id): SubscriptionSnapshot;
}
