<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum\Action;

use Doctrine\ORM\EntityManagerInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Bundle\PayumBundle\Model\PaymentSecurityToken;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;

final class StatusAction extends BaseApiAwareAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param GetStatus $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $payment = $request->getModel();

        if ($payment instanceof PaymentSecurityToken) {
            $payment = $this->entityManager->find($payment->getDetails()->getClass(), $payment->getDetails()->getId());
        }

        if (!$payment instanceof PaymentInterface) {
            throw new \RuntimeException(sprintf('Payment with id "%s" was not found', $payment->getId()));
        }

        //$paymentDetails = $payment->getDetails();

        $transaction = $this->api->getTransactionData($payment)->getTransactions()[0];
//
//        echo '<body><pre>', var_dump($this->api->getTransactionData($payment)), '</pre></body>';
//        echo '<br/><br/><br/><br/>';
//
//        die();

//
//        $paymentDetails['transaction_id'] = $transaction->getCode();
//        $payment->setDetails($paymentDetails);
//
//        $this->entityManager->persist($payment);
//        $this->entityManager->flush();

        switch ($transaction->getStatus()) {

            // 1 Aguardando pagamento: o comprador iniciou a transação, mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento.
            // 2 Em análise: o comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.
            // 3 Paga: a transação foi paga pelo comprador e o PagSeguro já recebeu uma confirmação da instituição financeira responsável pelo processamento.
            // 4 Disponível: a transação foi paga e chegou ao final de seu prazo de liberação sem ter sido retornada e sem que haja nenhuma disputa aberta.
            // 5 Em disputa: o comprador, dentro do prazo de liberação da transação, abriu uma disputa.
            // 6 Devolvida: o valor da transação foi devolvido para o comprador.
            // 7 Cancelada: a transação foi cancelada sem ter sido finalizada.

            case  1 :
                $request->markPending();
                break;

            case 2 :
                $request->markPending();
                break;

            case 3:
                $request->markCaptured();
                break;

            case 4:
                $request->markCaptured();
                break;

            case 6:
                $request->markRefunded();
                break;

            case 7 :
                $request->markCanceled();
                break;

            default:
                $request->markUnknown();
                break;
        }

        if ($request->getModel() instanceof PaymentSecurityToken) {
            $request->setModel($payment);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        //die('StatusAction');
        return $request instanceof GetStatusInterface;
    }
}
