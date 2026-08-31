<?php

declare(strict_types=1);

namespace Data2Md\Billing;

/**
 * The outcome of trying to become the processor of one Stripe event.
 *
 * The three cases answer Stripe differently, and conflating them is how an
 * event gets applied twice or dropped: InFlight has to be a 5xx so Stripe
 * retries, while AlreadyProcessed has to be a 200 so it stops.
 */
enum WebhookClaim
{
    /** This process owns the event and must handle it. */
    case Owned;

    /** Another process already finished it; nothing left to do. */
    case AlreadyProcessed;

    /** Another process holds it right now. Ask Stripe to come back. */
    case InFlight;
}
