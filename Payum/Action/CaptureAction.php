<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum\Action;

use Evirtua\SyliusPagseguroPayumBundle\Payum\Configuration;
use Doctrine\ORM\EntityManager;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\Capture;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;
use Sylius\Bundle\PayumBundle\Model\PaymentSecurityToken;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;

class CaptureAction extends BaseApiAwareAction implements GatewayAwareInterface, GenericTokenFactoryAwareInterface
{
    use GatewayAwareTrait;
    use GenericTokenFactoryAwareTrait;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Capture $request
     */
    public function execute($request)
    {
        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $notifyToken = $this->tokenFactory->createNotifyToken(
            $request->getToken()->getGatewayName(),
            $request->getToken()->getDetails()
        );

        $pagSeguroPaymentUrl = $this->api->createPaymentRequest($order, $request->getToken()->getAfterUrl(), $notifyToken->getTargetUrl());

        //$payment->setState(PaymentInterface::STATE_COMPLETED);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        throw new HttpResponse(null, 302, ['Location' => $pagSeguroPaymentUrl]);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface &&
            $request->getToken() instanceof PaymentSecurityToken &&
            $request->getToken()->getGatewayName() === Configuration::GATEWAY_NAME;
    }
}
