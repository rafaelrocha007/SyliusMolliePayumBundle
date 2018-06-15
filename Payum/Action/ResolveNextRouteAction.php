<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum\Action;

use Doctrine\ORM\EntityManagerInterface;
use Payum\Core\Action\ActionInterface;
use Sylius\Bundle\PayumBundle\Request\ResolveNextRoute;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderShippingStates;

class ResolveNextRouteAction implements ActionInterface
{
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
     * @param ResolveNextRoute $request
     */
    public function execute($request)
    {
        /** @var Payment $payment */
        $payment = $request->getModel();

        /** @var Order $order */
        $order = $payment->getOrder();

        if (
            $payment->getState() === Payment::STATE_COMPLETED ||
            $payment->getState() === Payment::STATE_AUTHORIZED ||
            $payment->getState() === Payment::STATE_PROCESSING
        ) {
            $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
            //$this->orderEmailManager->sendConfirmationEmail($order);
            $request->setRouteName('sylius_shop_order_thank_you');
            $this->entityManager->merge($order);

            return;
        }

        $order->setState(OrderInterface::STATE_CART);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
        $order->setShippingState(OrderShippingStates::STATE_READY);
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $request->setRouteName('sylius_shop_checkout_complete');

//        /** @var PaymentInterface $payment */
//        $payment = $request->getModel();
//        if ($payment->getState() === PaymentInterface::STATE_COMPLETED) {
//            $request->setRouteName('sylius_shop_order_thank_you');
//            return;
//        }
//        $request->setRouteName('sylius_shop_checkout_complete');
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return $request instanceof ResolveNextRoute;
    }
}
