<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum;

use PagSeguro\Configuration;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Order\Model\OrderItem;

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
        \PagSeguro\Library::cmsVersion()->setName("Sylius")->setRelease("1.0.0@beta");
        \PagSeguro\Library::moduleVersion()->setName("SyliusPagseguroPayumBundle")->setRelease("1.0.0");
        \PagSeguro\Configuration\Configure::setAccountCredentials($email, $token);
    }

    public function createPaymentRequest(Order $order)
    {

        $payment = new \PagSeguro\Domains\Requests\Payment();
        //TODO: Trocar por variaveis
        $payment->setRedirectUrl("http://sylius.opalasjoias.com.br");
        //TODO: Criar url para notificações
        $payment->setNotificationUrl("http://sylius.opalajoias.com.br/pagseguro/nofitication");

        foreach ($order->getItems() as $item) {
            //$id, $description, $quantity, $amount, $weight = null, $shippingCost = null
            $payment->addItems()->withParameters(
                $item->getProduct()->getId(),
                $item->getProduct()->getName(),
                $item->getQuantity(),
                $item->getTotal()
            );
        }

        $payment->setCurrency("BRL");

        $payment->setExtraAmount(0);

        $payment->setReference($order->getNumber());

        $payment->setRedirectUrl("http://sylius.opalasjoias.com.br");

        // Set your customer information.
        $payment->setSender()->setName($order->getCustomer()->getFullName());
        $payment->setSender()->setEmail($order->getCustomer()->getEmail());
        $payment->setSender()->setPhone()->withParameters(
            substr($order->getCustomer()->getPhoneNumber(), 0, 2),
            $order->getCustomer()->getPhoneNumber()
        );
        $payment->setSender()->setDocument()->withParameters(
            'CPF',
            $order->getCustomer()->getCpf()
        );

        $payment->setShipping()->setAddress()->withParameters(
            $order->getCustomer()->getDefaultAddress()->getStreet(),
            '1384',
            'Jardim Paulistano',
            '01452002',
            'São Paulo',
            'SP',
            'BRA',
            'apto. 114'
        );
        $payment->setShipping()->setCost()->withParameters(20.00);
        $payment->setShipping()->setType()->withParameters(\PagSeguro\Enum\Shipping\Type::SEDEX);

        $payment->setRedirectUrl("http://www.lojamodelo.com.br");
        $payment->setNotificationUrl("http://www.lojamodelo.com.br/nofitication");

        //Add a limit for installment
        $payment->addPaymentMethod()->withParameters(
            \PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            \PagSeguro\Enum\PaymentMethod\Config\Keys::MAX_INSTALLMENTS_LIMIT,
            3 // (int) qty of installment
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
            die($e->getMessage());
        }

        return $result;
    }

    /**
     * @param string $transactionId
     * @return \stdClass
     */
    public function getTransactionData($transactionId)
    {
        return $this->client->payments->get($transactionId);
    }
}
