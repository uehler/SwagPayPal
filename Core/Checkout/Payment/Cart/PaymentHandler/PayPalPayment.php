<?php declare(strict_types=1);
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagPayPal\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use SwagPayPal\PayPal\PaymentStatus;
use SwagPayPal\PayPal\Resource\PaymentResource;
use SwagPayPal\PayPal\Struct\Payment\RelatedResources\RelatedResource;
use SwagPayPal\Service\PaymentBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PayPalPayment implements PaymentHandlerInterface
{
    /**
     * @var RepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var PaymentResource
     */
    private $paymentResource;

    /**
     * @var PaymentBuilderInterface
     */
    private $paymentBuilder;

    public function __construct(
        RepositoryInterface $transactionRepository,
        PaymentResource $paymentResource,
        PaymentBuilderInterface $paymentBuilder
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->paymentResource = $paymentResource;
        $this->paymentBuilder = $paymentBuilder;
    }

    public function pay(PaymentTransactionStruct $transaction, Context $context): ?RedirectResponse
    {
        $payment = $this->paymentBuilder->getPayment($transaction, $context);

        $response = $this->paymentResource->create($payment, $context);

        $data = [
            'id' => $transaction->getTransactionId(),
            'details' => [
                'swag_paypal' => [
                    'transactionId' => $response->getId(),
                ],
            ],
        ];
        $this->transactionRepository->update([$data], $context);

        return new RedirectResponse($response->getLinks()[1]->getHref());
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
        if ($request->query->getBoolean('cancel')) {
            $transaction = [
                'id' => $transactionId,
                'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_FAILED,
            ];
            $this->transactionRepository->update([$transaction], $context);

            return;
        }

        $payerId = $request->query->get('PayerID');
        $paymentId = $request->query->get('paymentId');
        $response = $this->paymentResource->execute($payerId, $paymentId, $context);

        /** @var RelatedResource $responseSale */
        $responseSale = $response->getTransactions()->getRelatedResources()->getResources()[0];

        // apply the payment status if its completed by PayPal
        $paymentState = $responseSale->getState();

        if ($paymentState === PaymentStatus::PAYMENT_COMPLETED) {
            $transaction = [
                'id' => $transactionId,
                'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_COMPLETED,
            ];
        } else {
            $transaction = [
                'id' => $transactionId,
                'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
            ];
        }

        $this->transactionRepository->update([$transaction], $context);
    }
}