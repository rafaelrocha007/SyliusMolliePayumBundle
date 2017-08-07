<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum;

use Mockery\Exception;
use Sylius\Component\Core\Model\Order;

class Api
{
    private $token;
    private $email;
    private $sandbox;
    private $storeUrl;
    private $notificationUrl;

    /**
     * Api constructor.
     * @param $token
     * @param $email
     * @param $sandbox
     * @param $storeUrl
     * @param $notificationUrl
     */
    public function __construct($token, $email, $sandbox, $storeUrl, $notificationUrl)
    {
        $this->token = $token;
        $this->email = $email;
        $this->sandbox = $sandbox;
        $this->storeUrl = $storeUrl;
        $this->notificationUrl = $notificationUrl;

        \PagSeguro\Library::initialize();
        //\PagSeguro\Library::cmsVersion()->setName("Sylius")->setRelease("1.0.0@beta");
        //\PagSeguro\Library::moduleVersion()->setName("SyliusPagseguroPayumBundle")->setRelease("1.0.0");

        \PagSeguro\Configuration\Configure::setEnvironment($this->sandbox ? 'sandbox' : 'production');//production or sandbox
        \PagSeguro\Configuration\Configure::setAccountCredentials($email, $token);
        \PagSeguro\Configuration\Configure::setCharset('UTF-8');// UTF-8 or ISO-8859-1
        \PagSeguro\Configuration\Configure::setLog(true, __DIR__ . '/../../../../var/logs/pagseguro.log');

        try {
            $sessionCode = \PagSeguro\Services\Session::create(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function createPaymentRequest(Order $order, $redirectUrl, $notifyUrl)
    {
        //******************************************************************
        //Email: c73899547364277788267@sandbox.pagseguro.com.br
        //Senha: re13rv3w6N44p9M9
        //cpf: 90723232709
        // tid f53ea1b68e6f8202b41d47599c9b2112
        //******************************************************************

        $payment = new \PagSeguro\Domains\Requests\Payment();
        $payment->setRedirectUrl($redirectUrl);
        $payment->setNotificationUrl($notifyUrl);

        foreach ($order->getItems() as $item) {
            //$id, $description, $quantity, $amount, $weight = null, $shippingCost = null
            $payment->addItems()->withParameters(
                $item->getProduct()->getId(),
                $item->getProduct()->getName(),
                $item->getQuantity(),
                $item->getUnitPrice() / 100
            );
        }

        $payment->setCurrency("BRL");

        //$payment->setExtraAmount(0.00);

        $payment->setReference($order->getNumber());

        //$payment->setRedirectUrl("http://sylius.opalasjoias.com.br");

        // Set your customer information.
        $payment->setSender()->setName($order->getCustomer()->getFullName());
        $payment->setSender()->setEmail($order->getCustomer()->getEmail());
        $ddd = substr($order->getCustomer()->getPhoneNumber(), 0, 2);
        $substrCount = strlen($order->getCustomer()->getPhoneNumber() == 11) ? -9 : -8;
        $phone = substr($order->getCustomer()->getPhoneNumber(), $substrCount);
        $payment->setSender()->setPhone()->withParameters(
            $ddd,
            $phone
        );
        $payment->setSender()->setDocument()->withParameters(
            'CPF',
            $order->getCustomer()->getCpf()
        );

        $cep = str_replace('.', '', $order->getShippingAddress()->getPostcode());
        $cep = str_replace('-', '', $cep);

        $payment->setShipping()->setAddress()->withParameters(
            $order->getShippingAddress()->getStreet(),
            $order->getShippingAddress()->getNumber(),
            $order->getShippingAddress()->getNeighbourhood(),
            $cep,
            $order->getShippingAddress()->getCity(),
            $order->getShippingAddress()->getProvinceName(),
            $order->getShippingAddress()->getCountryCode(),
            $order->getShippingAddress()->getComplements()
        );

        $shipmentType = \PagSeguro\Enum\Shipping\Type::NOT_SPECIFIED;

        foreach ($order->getShipments() as $shipment) {
            if (stripos($shipment->getMethod()->getName(), 'pac') !== false) {
                $shipmentType = \PagSeguro\Enum\Shipping\Type::PAC;
            } else if (stripos($shipment->getMethod()->getName(), 'sedex') !== false) {
                $shipmentType = \PagSeguro\Enum\Shipping\Type::SEDEX;
            }
        }

        $payment->setShipping()->setCost()->withParameters(round($order->getShippingTotal() / 100, 2));
        $payment->setShipping()->setType()->withParameters($shipmentType);

        //Add a limit for installment
        $payment->addPaymentMethod()->withParameters(
            \PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            \PagSeguro\Enum\PaymentMethod\Config\Keys::MAX_INSTALLMENTS_NO_INTEREST,
            6 // (int) qty of installment
        );

        // Add a group and/or payment methods name
        $payment->acceptPaymentMethod()->groups(
            \PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            \PagSeguro\Enum\PaymentMethod\Group::BOLETO
        );

        try {
            $result = $payment->register(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );

        } catch (\Exception $e) {
            $result = 'https://www.opalasjoias.com.br/checkout/select-payment';
            //die('API 130 - ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * @param string $transactionId
     * @return string
     */
    public function getTransactionData($reference)
    {

        try {
            return \PagSeguro\Services\Transactions\Search\Reference::search(
                \PagSeguro\Configuration\Configure::getAccountCredentials(),
                $reference,
                ['initial_date' => date_format(new \DateTime(), 'Y-m-d\T00:00')]
            );
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
}
