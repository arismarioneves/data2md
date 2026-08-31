<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Data2Md\Config\Config;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Every call this app makes to Stripe, and the only file that imports the SDK.
 *
 * The pinned API version goes on the client rather than being left to the
 * account default, so a version change in the Stripe dashboard cannot alter the
 * payloads this app parses.
 */
final class StripeGateway implements SubscriptionReader, CustomerSubscriptionReader
{
    /**
     * The SDK defaults are 30s to connect and 80s to read, and mod_php has 24
     * prefork workers. A Stripe outage on those numbers parks every worker for
     * close to two minutes each, which takes the site down -- including /health,
     * whose failure restarts the container. One retry, because a retrieve is a
     * GET and the SDK attaches an idempotency key when it retries a write.
     */
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const READ_TIMEOUT_SECONDS    = 15;
    private const NETWORK_RETRIES         = 1;

    private readonly StripeClient $client;

    public function __construct(
        private readonly string $secretKey,
        string $apiVersion,
        private readonly string $webhookSecret = '',
    ) {
        $curl = CurlClient::instance();
        $curl->setConnectTimeout(self::CONNECT_TIMEOUT_SECONDS);
        $curl->setTimeout(self::READ_TIMEOUT_SECONDS);

        $this->client = new StripeClient([
            'api_key'             => $secretKey,
            'stripe_version'      => $apiVersion,
            'max_network_retries' => self::NETWORK_RETRIES,
        ]);
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->string('STRIPE_SECRET_KEY'),
            $config->string('STRIPE_API_VERSION'),
            $config->string('STRIPE_WEBHOOK_SECRET'),
        );
    }

    /**
     * Verifies the signature and returns the event as an array.
     *
     * @throws SignatureVerificationException when the body, the signature and
     *         the secret do not agree, or the timestamp is outside Stripe's
     *         five-minute tolerance -- which a wrong container clock also causes
     * @return array<string,mixed>
     */
    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);

        return $event->toArray();
    }

    public function hasWebhookSecret(): bool
    {
        return $this->webhookSecret !== '';
    }

    /**
     * Current state of a subscription, read from the API rather than taken from
     * an event payload.
     *
     * Stripe does not guarantee delivery order, so an update carrying a
     * cancellation can arrive before the creation it followed. Writing whatever
     * the newest delivery says would leave that account premium for good. A
     * fresh read is the same answer whatever order the events arrive in.
     *
     * @throws ApiErrorException
     */
    public function subscription(string $id): SubscriptionSnapshot
    {
        return SubscriptionSnapshot::fromArray(
            $this->client->subscriptions->retrieve($id, [])->toArray()
        );
    }

    /**
     * A price, with its per-currency amounts.
     *
     * currency_options is absent from the response unless it is expanded --
     * measured against the API, where a price created with a BRL option reads
     * back as null without this. Omitting the expand would make the price
     * validator report a missing currency on a correctly configured price, which
     * is worse than no check: it teaches an operator to ignore the one alarm
     * that says a Brazilian card cannot be charged.
     *
     * @return array<string,mixed>
     */
    public function price(string $id): array
    {
        return $this->client->prices
            ->retrieve($id, ['expand' => ['currency_options']])
            ->toArray();
    }

    /** True when this key operates on live data. */
    public function isLiveMode(): bool
    {
        return str_starts_with($this->secretKey, 'sk_live_');
    }

    public function createProduct(string $name, string $description): string
    {
        return (string) $this->client->products->create([
            'name'        => $name,
            'description' => $description,
        ])->id;
    }

    /**
     * The single monthly price: USD as the default presentment currency, with a
     * BRL amount alongside it.
     *
     * Two prices would not do. Stripe picks the currency from where the customer
     * is, and a customer's currency is fixed by their first subscription, so the
     * choice has to live inside one price rather than in a branch this app takes.
     *
     * @return array<string,mixed>
     */
    public function createMonthlyPrice(string $productId, int $usdCents, int $brlCents): array
    {
        return $this->client->prices->create([
            'product'          => $productId,
            'currency'         => PriceValidator::EXPECTED_CURRENCY,
            'unit_amount'      => $usdCents,
            'recurring'        => ['interval' => PriceValidator::EXPECTED_INTERVAL],
            'currency_options' => [
                PriceValidator::REQUIRED_CURRENCY_OPTION => ['unit_amount' => $brlCents],
            ],
            'expand'           => ['currency_options'],
        ])->toArray();
    }

    /**
     * Creates the Stripe customer for an account.
     *
     * Done before the first checkout rather than left to Stripe to create from
     * an e-mail, so this app knows the customer id from the start. Without it,
     * an account whose webhooks were all lost has no Stripe identifier stored at
     * all, and nothing can find the subscription it paid for.
     *
     * @throws ApiErrorException
     */
    public function createCustomer(string $email, int $userId): string
    {
        return (string) $this->client->customers->create([
            'email'    => $email,
            'metadata' => ['user_id' => (string) $userId],
        ])->id;
    }

    /**
     * The most recent subscription belonging to a customer, whatever its state.
     *
     * The recovery path for an account with a customer but no subscription
     * recorded, which is what a lost delivery looks like from this side.
     *
     * @throws ApiErrorException
     */
    public function latestSubscriptionFor(string $customerId): ?SubscriptionSnapshot
    {
        $subscriptions = $this->client->subscriptions->all([
            'customer' => $customerId,
            'status'   => 'all',
            'limit'    => 1,
        ]);

        $first = $subscriptions->data[0] ?? null;

        return $first === null ? null : SubscriptionSnapshot::fromArray($first->toArray());
    }

    /**
     * Starts a hosted checkout and returns the URL to send the browser to.
     *
     * Cards only. With an asynchronous method enabled, Stripe fires
     * checkout.session.completed while the money is still in flight, and the
     * session reads as unpaid -- a free month for anyone who starts a payment
     * and abandons it.
     *
     * The account id travels in metadata and in client_reference_id, and never
     * as an e-mail: the address on the session is whatever the payer types into
     * it, so matching on it would land one customer's payment on another's
     * account.
     *
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        string $priceId,
        int $userId,
        ?string $customerId,
        ?string $email,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $params = [
            'mode'                 => 'subscription',
            'payment_method_types' => ['card'],
            'line_items'           => [['price' => $priceId, 'quantity' => 1]],
            'client_reference_id'  => (string) $userId,
            'metadata'             => ['user_id' => (string) $userId],
            // On the subscription itself, so every later subscription event
            // carries the account without needing the session it came from.
            'subscription_data'    => ['metadata' => ['user_id' => (string) $userId]],
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
        ];

        // Reusing the stored customer is what keeps one account to one customer.
        // Passing an e-mail instead on a second checkout makes Stripe create
        // another customer, and the billing portal then shows only one of them.
        if ($customerId !== null && $customerId !== '') {
            $params['customer'] = $customerId;
        } elseif ($email !== null && $email !== '') {
            $params['customer_email'] = $email;
        }

        return (string) $this->client->checkout->sessions->create($params)->url;
    }

    /** @throws ApiErrorException */
    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        return (string) $this->client->billingPortal->sessions->create([
            'customer'   => $customerId,
            'return_url' => $returnUrl,
        ])->url;
    }

    /** @return array<string,mixed> */
    public function account(): array
    {
        return $this->client->accounts->retrieve()->toArray();
    }
}
