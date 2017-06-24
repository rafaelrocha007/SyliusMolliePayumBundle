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

    /**
     * @param float $amount
     * @param string $description
     * @param string $redirectUrl
     * @param string $webhookUrl
     * @param PaymentInterface $payment
     * @return array
     */
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
            substr($order->getCustomer()->getPhoneNumber(),0,2),
            $order->getCustomer()->getPhoneNumber()
        );
        $payment->setSender()->setDocument()->withParameters(
            'CPF',
            'insira um numero de CPF valido'
        );

        $payment->setShipping()->setAddress()->withParameters(
            'Av. Brig. Faria Lima',
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

//Add metadata items
        $payment->addMetadata()->withParameters('PASSENGER_CPF', 'insira um numero de CPF valido');
        $payment->addMetadata()->withParameters('GAME_NAME', 'DOTA');
        $payment->addMetadata()->withParameters('PASSENGER_PASSPORT', '23456', 1);

//Add items by parameter
//On index, you have to pass in parameter: total items plus one.
        $payment->addParameter()->withParameters('itemId', '0003')->index(3);
        $payment->addParameter()->withParameters('itemDescription', 'Notebook Amarelo')->index(3);
        $payment->addParameter()->withParameters('itemQuantity', '1')->index(3);
        $payment->addParameter()->withParameters('itemAmount', '200.00')->index(3);

//Add items by parameter using an array
        $payment->addParameter()->withArray(['notificationURL', 'http://www.lojamodelo.com.br/nofitication']);

        $payment->setRedirectUrl("http://www.lojamodelo.com.br");
        $payment->setNotificationUrl("http://www.lojamodelo.com.br/nofitication");

//Add discount
        $payment->addPaymentMethod()->withParameters(
            PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            PagSeguro\Enum\PaymentMethod\Config\Keys::DISCOUNT_PERCENT,
            10.00 // (float) Percent
        );

//Add installments with no interest
        $payment->addPaymentMethod()->withParameters(
            PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            PagSeguro\Enum\PaymentMethod\Config\Keys::MAX_INSTALLMENTS_NO_INTEREST,
            2 // (int) qty of installment
        );

//Add a limit for installment
        $payment->addPaymentMethod()->withParameters(
            PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            PagSeguro\Enum\PaymentMethod\Config\Keys::MAX_INSTALLMENTS_LIMIT,
            6 // (int) qty of installment
        );

// Add a group and/or payment methods name
        $payment->acceptPaymentMethod()->groups(
            \PagSeguro\Enum\PaymentMethod\Group::CREDIT_CARD,
            \PagSeguro\Enum\PaymentMethod\Group::BALANCE
        );
        $payment->acceptPaymentMethod()->name(\PagSeguro\Enum\PaymentMethod\Name::DEBITO_ITAU);
// Remove a group and/or payment methods name
        $payment->excludePaymentMethod()->group(\PagSeguro\Enum\PaymentMethod\Group::BOLETO);


        try {

            /**
             * @todo For checkout with application use:
             * \PagSeguro\Configuration\Configure::getApplicationCredentials()
             *  ->setAuthorizationCode("FD3AF1B214EC40F0B0A6745D041BF50D")
             */
            $result = $payment->register(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );

            echo "<h2>Criando requisi&ccedil;&atilde;o de pagamento</h2>"
                . "<p>URL do pagamento: <strong>$result</strong></p>"
                . "<p><a title=\"URL do pagamento\" href=\"$result\" target=\_blank\">Ir para URL do pagamento.</a></p>";
        } catch (Exception $e) {
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
