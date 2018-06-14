<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Controller;

use PagSeguro\Configuration\Configure;
use PagSeguro\Parsers\Transaction\Search\Date\Transaction;
use PagSeguro\Services\Application\Notification;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PagSeguroController extends Controller
{
    function senderHashAction($hash, Request $request)
    {
        $this->get('session')->set('pagseguro.sender_hash', $hash);
        return new Response();
    }

    function cardTokenAction($hash, Request $request)
    {
        $this->get('session')->set('pagseguro.card_token', $hash);
        return new Response();
    }

    function notificationAction(Request $request)
    {
        try {
            $gatewayConfig = $this->get('sylius.repository.gateway_config')->findOneBy(['factoryName' => 'pagseguro']);
            $configs = $gatewayConfig->getConfig();

            $url = 'https://ws.' . ($configs['environment'] == 'production' ? '' : 'sandbox.') .
                'pagseguro.uol.com.br/v2/transactions/notifications/' . $request->get('notificationCode') .
                '?email=' . $configs['email'] . '&token=' . $configs['token'];

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            $http = curl_getinfo($curl);

            if ($response == 'Unauthorized') {
                return new Response($response, 500);
            }

            curl_close($curl);
            $response = simplexml_load_string($response);

            if ($response !== false) {
                if (count($response->error) > 0) {
                    return new Response($response, 500);
                }
            }

            // 1 Aguardando pagamento: o comprador iniciou a transação, mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento.
            // 2 Em análise: o comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.
            // 3 Paga: a transação foi paga pelo comprador e o PagSeguro já recebeu uma confirmação da instituição financeira responsável pelo processamento.
            // 4 Disponível: a transação foi paga e chegou ao final de seu prazo de liberação sem ter sido retornada e sem que haja nenhuma disputa aberta.
            // 5 Em disputa: o comprador, dentro do prazo de liberação da transação, abriu uma disputa.
            // 6 Devolvida: o valor da transação foi devolvido para o comprador.
            // 7 Cancelada: a transação foi cancelada sem ter sido finalizada.

            $orderRepo = $this->get('sylius.repository.order');
            $order = $orderRepo->findOneBy(['number' => $response->reference]);

            /** @var Payment $payment */
            $payment = $order->getPayments()[0];
            $paymentDetails = $payment->getDetails();
            $paymentDetails['notificationCode'] = $request->get('notificationCode');
            $payment->setDetails($paymentDetails);

            switch ($response->status) {
                case 1:
                    $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
                    $payment->setState(Payment::STATE_PROCESSING);
                    break;
                case 2:
                    $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
                    $payment->setState(Payment::STATE_PROCESSING);
                    break;
                case 3:
                    $order->setPaymentState(OrderPaymentStates::STATE_PAID);
                    $payment->setState(Payment::STATE_COMPLETED);
                    break;
                case 4:
                    $order->setPaymentState(OrderPaymentStates::STATE_PAID);
                    $payment->setState(Payment::STATE_COMPLETED);
                    break;
                case 5:
                    $order->setPaymentState(OrderPaymentStates::STATE_PARTIALLY_REFUNDED);
                    $payment->setState(Payment::STATE_REFUNDED);
                    break;
                case 6:
                    $order->setPaymentState(OrderPaymentStates::STATE_REFUNDED);
                    $payment->setState(Payment::STATE_REFUNDED);
                    break;
                case 7:
                    $order->setPaymentState(OrderPaymentStates::STATE_CANCELLED);
                    $payment->setState(Payment::STATE_CANCELLED);
                    break;
                default:
                    break;
            }

            $em = $this->get('doctrine.orm.entity_manager');

            $em->merge($payment);
            $em->merge($order);
            $em->flush();

            return new Response('', 200);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }
    }
}