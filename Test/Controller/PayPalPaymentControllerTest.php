<?php declare(strict_types=1);
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagPayPal\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use SwagPayPal\Controller\PayPalPaymentController;
use SwagPayPal\PayPal\Exception\RequiredParameterInvalidException;
use SwagPayPal\PayPal\PaymentIntent;
use SwagPayPal\PayPal\Resource\SaleResource;
use SwagPayPal\Test\Helper\ServicesTrait;
use SwagPayPal\Test\Mock\PayPal\Client\_fixtures\GetSaleResponseFixture;
use SwagPayPal\Test\Mock\PayPal\Client\_fixtures\RefundSaleResponseFixture;
use SwagPayPal\Test\Mock\PayPal\Resource\SaleResourceMock;
use Symfony\Component\HttpFoundation\Request;

class PayPalPaymentControllerTest extends TestCase
{
    use ServicesTrait;

    private const TEST_REFUND_INVOICE_NUMBER = 'testRefundInvoiceNumber';

    private const TEST_REFUND_AMOUNT = 5.5;

    private const TEST_REFUND_CURRENCY = 'EUR';

    public function testGetPaymentDetails(): void
    {
        $controller = $this->createPaymentController();

        $context = Context::createDefaultContext();
        $response = $controller->paymentDetails($context, 'testPaymentId');

        $paymentDetails = json_decode($response->getContent(), true);

        self::assertSame(
            GetSaleResponseFixture::TRANSACTION_AMOUNT_DETAILS_SUBTOTAL,
            $paymentDetails['transactions'][0]['amount']['details']['subtotal']
        );
    }

    public function testRefundPayment(): void
    {
        $controller = $this->createPaymentController();

        $request = new Request();
        $context = Context::createDefaultContext();
        $response = $controller->refundPayment($request, $context, PaymentIntent::SALE, 'testPaymentId');

        $refund = json_decode($response->getContent(), true);

        self::assertSame(RefundSaleResponseFixture::REFUND_AMOUNT, $refund['amount']['total']);
    }

    public function testRefundPaymentWithInvoiceAndAmount(): void
    {
        $controller = $this->createPaymentControllerWithSaleResourceMock();

        $request = new Request([], [
            PayPalPaymentController::REQUEST_PARAMETER_REFUND_INVOICE_NUMBER => self::TEST_REFUND_INVOICE_NUMBER,
            PayPalPaymentController::REQUEST_PARAMETER_REFUND_AMOUNT => self::TEST_REFUND_AMOUNT,
            PayPalPaymentController::REQUEST_PARAMETER_REFUND_CURRENCY => self::TEST_REFUND_CURRENCY,
        ]);
        $context = Context::createDefaultContext();
        $response = $controller->refundPayment($request, $context, PaymentIntent::SALE, 'testPaymentId');

        $refund = json_decode($response->getContent(), true);

        self::assertSame((string) self::TEST_REFUND_AMOUNT, $refund['amount']['total']);
        self::assertSame(self::TEST_REFUND_CURRENCY, $refund['amount']['currency']);
        self::assertSame(self::TEST_REFUND_INVOICE_NUMBER, $refund['invoice_number']);
    }

    public function testRefundPaymentWithInvalidIntent(): void
    {
        $controller = $this->createPaymentControllerWithSaleResourceMock();

        $request = new Request();
        $context = Context::createDefaultContext();

        $this->expectException(RequiredParameterInvalidException::class);
        $this->expectExceptionMessage('Required parameter "intent" is missing or invalid');
        $controller->refundPayment($request, $context, 'foo', 'testPaymentId');
    }

    private function createPaymentController(): PayPalPaymentController
    {
        return new PayPalPaymentController(
            $this->createPaymentResource(),
            new SaleResource($this->createPayPalClientFactory())
        );
    }

    private function createPaymentControllerWithSaleResourceMock(): PayPalPaymentController
    {
        return new PayPalPaymentController(
            $this->createPaymentResource(),
            new SaleResourceMock($this->createPayPalClientFactory())
        );
    }
}