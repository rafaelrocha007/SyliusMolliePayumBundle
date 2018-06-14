<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace Evirtua\SyliusPagseguroPayumBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class
PagSeguroGatewayConfigurationType extends AbstractType
{
    const CARTAO_CHECKOUT_TRANSPARENTE = 'cartao_transparente';
    const BOLETO_CHECKOUT_TRANSPARENTE = 'boleto_transparente';
    const REDIRECIONAMENTO_PAGSEGURO = 'redirecionar_pagseguro';

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('token', TextType::class, [
                'label' => 'Token do Vendedor',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Token deve ser preenchido',
                        'groups' => ['sylius'],
                    ]),
                    new Length([
                        'minMessage' => 'Token deve ter 32 caracteres',
                        'groups' => ['sylius'],
                        'min' => 32,
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email do vendedor',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Email do Vendedor deve ser preenchido',
                        'groups' => ['sylius'],
                    ])
                ],
            ])
            ->add('environment', ChoiceType::class, [
                'label' => 'Ambiente (Sandbox ou Produção)',
                'choices' => [
                    'Sandbox' => 'sandbox',
                    'Produção' => 'production'
                ]
            ])
            ->add('pagamento', ChoiceType::class, [
                'label' => 'Escolha o Tipo do Pagamento',
                'choices' => [
                    'Pagamento Transparente Com Cartão' => 'cartao_transparente',
                    'Pagamento Transparente Com Boleto' => 'boleto_transparente',
                    'Pagamento no Site do PagSeguro' => 'redirecionar_pagseguro'
                ]
            ])
            ->add('charset', ChoiceType::class, [
                'label' => 'Escolha o Charset',
                'choices' => [
                    'UTF-8' => 'UTF-8',
                    'ISO-8859-1' => 'ISO-8859-1'
                ]
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $data = $event->getData();

                //$data['payum.http_client'] = '@pagseguro_plugin.mollie_api_client';

                $event->setData($data);
            });
    }
}
