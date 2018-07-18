<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum;

use Payum\Core\Reply\HttpRedirect;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderShippingStates;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

class Api
{
    private $token;
    private $email;
    private $sandbox;
    private $storeUrl;
    private $notificationUrl;
    private $session;
    /** @var Container */
    private $container;

    /**
     * Api constructor.
     * @param $token
     * @param $email
     * @param $sandbox
     * @param $storeUrl
     * @param $notificationUrl
     */
    public function __construct($session, $container)
    {
        $this->session = $session;
        $this->container = $container;
//    public function __construct($token, $email, $sandbox, $storeUrl, $notificationUrl, $session)
//        die($token);
//        $this->token = $token;
//        $this->email = $email;
//        $this->sandbox = $sandbox;
//        $this->storeUrl = $storeUrl;
//        $this->notificationUrl = $notificationUrl;
//
//
    }

    private function getGatewayConfiguration(PaymentInterface $payment)
    {
        $configs = $payment->getMethod()->getGatewayConfig()->getConfig();

        \PagSeguro\Library::initialize();
        \PagSeguro\Library::cmsVersion()->setName("Sylius")->setRelease("1.0.0");
        \PagSeguro\Library::moduleVersion()->setName("SyliusPagseguroPayumBundle")->setRelease("1.0.0");

        \PagSeguro\Configuration\Configure::setEnvironment($configs['environment']);
        \PagSeguro\Configuration\Configure::setAccountCredentials($configs['email'], $configs['token']);
        \PagSeguro\Configuration\Configure::setCharset($configs['charset']);
        \PagSeguro\Configuration\Configure::setLog(true, __DIR__ . '/../../../../var/logs/pagseguro.log');

        try {
            $sessionCode = \PagSeguro\Services\Session::create(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return $configs;
    }

    public function createPaymentRequest(PaymentInterface $orderPayment, $redirectUrl, $notifyUrl)
    {
        $this->getGatewayConfiguration($orderPayment);
        $order = $orderPayment->getOrder();

        $payment = new \PagSeguro\Domains\Requests\Payment();
        $payment->setRedirectUrl($redirectUrl);
        $payment->setNotificationUrl($notifyUrl);

        foreach ($order->getItems() as $item) {
            //$id, $description, $quantity, $amount, $weight = null, $shippingCost = null
            $payment->addItems()->withParameters(
                $item->getProduct()->getId(),
                $item->getProduct()->getName(),
                $item->getQuantity(),
                number_format((float)($item->getDiscountedUnitPrice() / 100), 2, '.', '')
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

    public function directPaymentCreditCard(PaymentInterface $payment, $redirectUrl, $notifyUrl)
    {
        //******************************************************************
        //Email: c73899547364277788267@sandbox.pagseguro.com.br
        //Senha: re13rv3w6N44p9M9
        //cpf: 90723232709
        // tid f53ea1b68e6f8202b41d47599c9b2112
        //******************************************************************

        //Instantiate a new direct payment request, using Credit Card
        $configs = $this->getGatewayConfiguration($payment);

        $creditCard = new \PagSeguro\Domains\Requests\DirectPayment\CreditCard();

        $order = $payment->getOrder();

        /**
         * @todo Change the receiver Email
         */
        $creditCard->setReceiverEmail($configs['email']);
        // Set a reference code for this payment request. It is useful to identify this payment
        // in future notifications.
        $creditCard->setReference($order->getNumber());
        // Set the currency
        $creditCard->setCurrency("BRL");
        // Add an item for this payment request


        foreach ($order->getItems() as $item) {
            //$id, $description, $quantity, $amount, $weight = null, $shippingCost = null
            $creditCard->addItems()->withParameters(
                $item->getProduct()->getId(),
                $item->getProduct()->getName(),
                $item->getQuantity(),
                number_format((float)($item->getDiscountedUnitPrice() / 100), 2, '.', '')
            );
        }

        $creditCard->setCurrency("BRL");
        $creditCard->setSender()->setName($this->session->get('pagseguro.credit_card')['holder'] ?? $order->getCustomer()->getFullName() ?? 'Opalas Joias');
        $creditCard->setSender()->setEmail($order->getCustomer()->getEmail());
        $phoneNumber = $order->getShippingAddress()->getPhoneNumber() ?? $order->getCustomer()->getPhoneNumber();
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        $ddd = substr($phoneNumber, 0, 2);
        $substrCount = (strlen($phoneNumber) == 11) ? -9 : -8;
        $phone = substr($phoneNumber, $substrCount);

        $creditCard->setSender()->setPhone()->withParameters(
            $ddd,
            $phone
        );
        $creditCard->setSender()->setDocument()->withParameters(
            'CPF',
            $this->session->get('pagseguro.credit_card')['cpf'] ?? $order->getCustomer()->getCpf()
        );

        /**
         * TODO: Onde achar o setSender Hash
         */
        $creditCard->setSender()->setHash($this->session->get('pagseguro.sender_hash'));
        $creditCard->setSender()->setIp($order->getCustomerIp());

        $cep = str_replace('.', '', $order->getShippingAddress()->getPostcode());
        $cep = str_replace('-', '', $cep);

        $creditCard->setShipping()->setAddress()->withParameters(
            $order->getShippingAddress()->getStreet(),
            $order->getShippingAddress()->getNumber(),
            $order->getShippingAddress()->getNeighbourhood(),
            $cep,
            $order->getShippingAddress()->getCity(),
            str_replace('BR-', '', $order->getShippingAddress()->getProvinceCode()),
            $order->getShippingAddress()->getCountryCode(),
            $order->getShippingAddress()->getComplements()
        );

        $cep = str_replace('.', '', $order->getBillingAddress()->getPostcode());
        $cep = str_replace('-', '', $cep);

        $creditCard->setBilling()->setAddress()->withParameters(
            $order->getBillingAddress()->getStreet(),
            $order->getBillingAddress()->getNumber(),
            $order->getBillingAddress()->getNeighbourhood(),
            $cep,
            $order->getBillingAddress()->getCity(),
            str_replace('BR-', '', $order->getBillingAddress()->getProvinceCode()),
            $order->getBillingAddress()->getCountryCode(),
            $order->getBillingAddress()->getComplements()
        );

        $shipmentType = \PagSeguro\Enum\Shipping\Type::NOT_SPECIFIED;

        foreach ($order->getShipments() as $shipment) {
            if (stripos($shipment->getMethod()->getName(), 'pac') !== false) {
                $shipmentType = \PagSeguro\Enum\Shipping\Type::PAC;
            } else if (stripos($shipment->getMethod()->getName(), 'sedex') !== false) {
                $shipmentType = \PagSeguro\Enum\Shipping\Type::SEDEX;
            }
        }

        $creditCard->setShipping()->setCost()->withParameters(round($order->getShippingTotal() / 100, 2));
        $creditCard->setShipping()->setType()->withParameters($shipmentType);

        // Set credit card token
        $creditCard->setToken($this->session->get('pagseguro.card_token'));
        // Set the installment quantity and value (could be obtained using the Installments
        // service, that have an example here in \public\getInstallments.php)

        $creditCard->setInstallment()->withParameters(
            $this->session->get('pagseguro.credit_card')['installments'],
            $this->session->get('pagseguro.credit_card')['installmentAmount'],
            3
        );

        // Set the credit card holder information

        if (isset($this->session->get('pagseguro.credit_card')['birthDate'])) {
            $birthDate = $this->session->get('pagseguro.credit_card')['birthDate'];
        } else {
            $birthDate = $order->getCustomer()->getBirthday();
        }

        $birthDate = $birthDate->format('d/m/Y');

        $creditCard->setHolder()->setBirthdate($birthDate);
        $creditCard->setHolder()->setName($this->session->get('pagseguro.credit_card')['holder'] ?? $order->getCustomer()->getFullName());
        $creditCard->setHolder()->setPhone()->withParameters(
            $ddd,
            $phone
        );
        $creditCard->setHolder()->setDocument()->withParameters(
            'CPF',
            $this->session->get('pagseguro.credit_card')['cpf'] ?? $order->getCustomer()->getCpf()
        );
        // Set the Payment Mode for this payment request
        $creditCard->setMode('DEFAULT');
        // Set a reference code for this payment request. It is useful to identify this payment
        // in future notifications.


        //$payment->setRedirectUrl($redirectUrl);
        $creditCard->setNotificationUrl($notifyUrl);

        try {
            $result = $creditCard->register(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );
        } catch (\Exception $e) {
            $order->setState(OrderInterface::STATE_CART);
            $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
            $order->setShippingState(OrderShippingStates::STATE_CART);
            $order->setPaymentState(OrderPaymentStates::STATE_CART);
            $payment->setState(PaymentInterface::STATE_CART);
            $this->container->get('doctrine.orm.entity_manager')->merge($order);
            $this->container->get('doctrine.orm.entity_manager')->merge($payment);
            $this->container->get('doctrine.orm.entity_manager')->flush();
            $xml = simplexml_load_string($e->getMessage());
            if ($xml)
                $this->container->get('session')->getFlashBag()->add('error', $xml->error->code . ' - ' . $xml->error->message);
            throw new HttpRedirect('/checkout/complete');
        }

        $this->session->set('pagseguro.credit_card', null);

        return $result;
    }

    public function directPaymentBoleto(PaymentInterface $payment, $redirectUrl, $notifyUrl)
    {
        //******************************************************************
        //Email: c73899547364277788267@sandbox.pagseguro.com.br
        //Senha: re13rv3w6N44p9M9
        //cpf: 90723232709
        // tid f53ea1b68e6f8202b41d47599c9b2112
        //******************************************************************

        //Instantiate a new direct payment request, using Credit Card
        $configs = $this->getGatewayConfiguration($payment);
        /** @var Order $order */
        $order = $payment->getOrder();

        $boleto = new \PagSeguro\Domains\Requests\DirectPayment\Boleto();

        $boleto->setReceiverEmail($configs['email']);
        $boleto->setMode('DEFAULT');
        $boleto->setCurrency("BRL");
        $boleto->setReference($order->getNumber());

        foreach ($order->getItems() as $item) {
            //$id, $description, $quantity, $amount, $weight = null, $shippingCost = null
            $boleto->addItems()->withParameters(
                $item->getProduct()->getId(),
                $item->getProduct()->getName(),
                $item->getQuantity(),
                number_format((float)($item->getDiscountedUnitPrice() / 100), 2, '.', '')
            );
        }

        $boleto->setSender()->setName($order->getShippingAddress()->getFullName() ?? $order->getCustomer()->getFullName());
        $boleto->setSender()->setEmail($order->getCustomer()->getEmail());
        $phoneNumber = $order->getShippingAddress()->getPhoneNumber() ?? $order->getCustomer()->getPhoneNumber();
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        $ddd = substr($phoneNumber, 0, 2);
        $substrCount = (strlen($phoneNumber) == 11) ? -9 : -8;
        $phone = substr($phoneNumber, $substrCount);

        $boleto->setSender()->setPhone()->withParameters(
            $ddd,
            $phone
        );
        $boleto->setSender()->setDocument()->withParameters(
            'CPF',
            $this->session->get('pagseguro.boleto')['cpf'] ?? $order->getCustomer()->getCpf()
        );

        $boleto->setSender()->setHash($this->session->get('pagseguro.sender_hash'));
        $boleto->setSender()->setIp($order->getCustomerIp());

        $cep = str_replace('.', '', $order->getShippingAddress()->getPostcode());
        $cep = str_replace('-', '', $cep);

        $boleto->setShipping()->setAddress()->withParameters(
            $order->getShippingAddress()->getStreet(),
            $order->getShippingAddress()->getNumber(),
            $order->getShippingAddress()->getNeighbourhood(),
            $cep,
            $order->getShippingAddress()->getCity(),
            str_replace('BR-', '', $order->getShippingAddress()->getProvinceCode()),
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

        $boleto->setShipping()->setCost()->withParameters(round($order->getShippingTotal() / 100, 2));
        $boleto->setShipping()->setType()->withParameters($shipmentType);

        try {
            $result = $boleto->register(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );
        } catch (\Exception $e) {
            $order->setState(OrderInterface::STATE_CART);
            $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
            $order->setShippingState(OrderShippingStates::STATE_CART);
            $order->setPaymentState(OrderPaymentStates::STATE_CART);
            $payment->setState(PaymentInterface::STATE_CART);
            $this->container->get('doctrine.orm.entity_manager')->merge($order);
            $this->container->get('doctrine.orm.entity_manager')->merge($payment);
            $this->container->get('doctrine.orm.entity_manager')->flush();
            $xml = simplexml_load_string($e->getMessage());
            if ($xml)
                $this->container->get('session')->getFlashBag()->add('error', $xml->error->code . ' - ' . $xml->error->message);
            throw new HttpRedirect('/checkout/complete');
        }

        $this->session->set('pagseguro.boleto', null);

        return $result;
    }

    /**
     * @param string $transactionId
     * @return string
     */
    public function getTransactionData($payment)
    {
        $this->getGatewayConfiguration($payment);
        $reference = $payment->getOrder()->getNumber();

        $configs = $payment->getMethod()->getGatewayConfig()->getConfig();

        //pagseguro-php-sdk começou a retornar incorretamente
        //chamada feita via CURL
//        try {
//            return \PagSeguro\Services\Transactions\Search\Reference::search(
//                \PagSeguro\Configuration\Configure::getAccountCredentials(),
//                $reference,
//                ['initial_date' => date_format(new \DateTime(), 'Y-01-01\T00:00')]
//            );
//        } catch (Exception $e) {
//            die($e->getMessage());
//        }


        $url = 'https://ws.' . ($configs['environment'] == 'production' ? '' : 'sandbox.') .
            'pagseguro.uol.com.br/v2/transactions' .
            '?email=' . $configs['email'] . '&token=' . $configs['token'] . '&reference=' . $reference;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $http = curl_getinfo($curl);

        if ($response == 'Unauthorized') {
            return 'Unauthorized';
        }

        curl_close($curl);
        $response = simplexml_load_string($response);

        if ($response !== false) {
            if (count($response->error) > 0) {
                return $response->error->code . ' ' . $response->error->message;
            }
            return $response->transactions[0]->transaction;
        }
    }
}
