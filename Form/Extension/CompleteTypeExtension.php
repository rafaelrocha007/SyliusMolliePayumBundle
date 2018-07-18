<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace Evirtua\SyliusPagseguroPayumBundle\Form\Extension;

use Evirtua\SyliusPagseguroPayumBundle\Form\Type\BoletoType;
use Evirtua\SyliusPagseguroPayumBundle\Form\Type\CreditCardType;
use BitBag\SyliusMolliePlugin\MollieSubscriptionGatewayFactory;
use Evirtua\SyliusPagseguroPayumBundle\Form\Type\PagSeguroGatewayConfigurationType;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Valid;

final class CompleteTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var OrderInterface $order */
        $order = $builder->getData();

        /** @var PaymentMethodInterface|null $method */
        $method = null !== $order->getLastPayment() ? $order->getLastPayment()->getMethod() : null;

        if (
            null !== $method &&
            null !== $method->getGatewayConfig() &&
            isset($method->getGatewayConfig()->getConfig()['pagamento']) &&
            $method->getGatewayConfig()->getConfig()['pagamento'] === PagSeguroGatewayConfigurationType::CARTAO_CHECKOUT_TRANSPARENTE &&
            'pagseguro' === $method->getGatewayConfig()->getFactoryName()
        ) {
            $builder
                ->add('creditCard', CreditCardType::class, [
                    'mapped' => false,
                    'validation_groups' => ['sylius'],
                    'constraints' => [
                        new Valid(),
                    ],
                ]);
        }

        if (
            null !== $method &&
            null !== $method->getGatewayConfig() &&
            isset($method->getGatewayConfig()->getConfig()['pagamento']) &&
            $method->getGatewayConfig()->getConfig()['pagamento'] === PagSeguroGatewayConfigurationType::BOLETO_CHECKOUT_TRANSPARENTE &&
            'pagseguro' === $method->getGatewayConfig()->getFactoryName()
        ) {
            $builder
                ->add('boleto', BoletoType::class, [
                    'mapped' => false,
                    'validation_groups' => ['sylius'],
                    'constraints' => [
                        new Valid(),
                    ],
                ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType(): string
    {
        return CompleteType::class;
    }
}
