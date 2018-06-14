<?php

namespace Evirtua\SyliusPagseguroPayumBundle\Payum\Action;

use Doctrine\ORM\EntityManager;
use Evirtua\SyliusPagseguroPayumBundle\Form\Type\PagSeguroGatewayConfigurationType;
use Evirtua\SyliusPagseguroPayumBundle\Payum\Configuration;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;
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
    public function execute2($request)
    {
        /* * @var PaymentInterface $payment */
        die(twig_var_dump($request));
        $payment = $request->getModel();

        die(var_dump($payment->getOrder()));

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $notifyToken = $this->tokenFactory->createNotifyToken(
            $request->getToken()->getGatewayName(),
            $request->getToken()->getDetails()
        );

        $pagSeguroPaymentUrl = $this->api->createPaymentRequest($order, $request->getToken()->getAfterUrl(), $notifyToken->getTargetUrl());

        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        //throw new HttpResponse(null, 302, ['Location' => $pagSeguroPaymentUrl]);
        throw new HttpPostRedirect($payment->getCheckoutUrl());
    }

    /**
     * {@inheritdoc}
     *
     * @param Capture $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        /** @var PaymentInterface */
        $payment = $this->entityManager->find($request->getToken()->getDetails()->getClass(), $request->getToken()->getDetails()->getId());
        $order = $payment->getOrder();
        $gatewayConfigs = $payment->getMethod()->getGatewayConfig()->getConfig();


        /** @var TokenInterface $token */
        $token = $request->getToken();

        if (null === $this->tokenFactory) {
            throw new RuntimeException();
        }

        $notifyToken = $this->tokenFactory->createNotifyToken($token->getGatewayName(), $token->getDetails());
//        echo '<br/><br/><br/><br/>';
        $paymentDetails = [];

        if ($gatewayConfigs['pagamento'] == PagSeguroGatewayConfigurationType::CARTAO_CHECKOUT_TRANSPARENTE) {
            $paymentStatus = $this->api->directPaymentCreditCard($payment, $token->getAfterUrl(), $notifyToken->getTargetUrl());
        } else if ($gatewayConfigs['pagamento'] == PagSeguroGatewayConfigurationType::BOLETO_CHECKOUT_TRANSPARENTE) {
            $paymentStatus = $this->api->directPaymentBoleto($payment, $token->getAfterUrl(), $notifyToken->getTargetUrl());
            $paymentDetails['link_boleto'] = $paymentStatus->getPaymentLink();
//        echo '<br/><br/><br/><br/>';
        } else if ($gatewayConfigs['pagamento'] == PagSeguroGatewayConfigurationType::REDIRECIONAMENTO_PAGSEGURO) {
            $paymentStatus = $this->api->createPaymentRequest($payment, $token->getAfterUrl(), $notifyToken->getTargetUrl());
        }

        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        $paymentDetails['transaction_id'] = $paymentStatus->getCode();
        $payment->setDetails($paymentDetails);

        $this->entityManager->persist($order);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        throw new HttpRedirect($request->getToken()->getAfterUrl());
        //$this->gateway->execute(new GetStatus($payment));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            //$request->getModel() instanceof ArrayObject &&
            //$request->getToken() instanceof PaymentSecurityToken &&
            ($request->getToken()->getGatewayName() === 'pagseguro_cartao' ||
                $request->getToken()->getGatewayName() === 'pagseguro_boleto' ||
                $request->getToken()->getGatewayName() === 'pagseguro_redirecionamento');
    }
}
