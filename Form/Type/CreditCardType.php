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

use PagSeguro\Resources\Factory\Request;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CreditCardType extends AbstractType
{
    /** @var SessionInterface */
    private $session;

    /**
     * @var Container
     */
    private $container;

    /**
     * @param SessionInterface $session
     */
    public function __construct(Container $container, SessionInterface $session)
    {
        $this->container = $container;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $order = $this->container->get('sylius.repository.order')->find($this->session->get('_sylius.cart.default'));
        $gatewayConfig = $order->getPayments()[0]->getMethod()->getGatewayConfig()->getConfig();

        $this->parameters = [];
        $this->parameters['token'] = $gatewayConfig['token'];
        $this->parameters['email'] = $gatewayConfig['email'];
        $this->parameters['sandbox'] = $gatewayConfig['environment'] !== 'production' ? 'sandbox.' : '';

        $securityContext = $this->container->get('security.token_storage');
        $user = $securityContext->getToken()->getUser();

        $addFields = false;
        if ($user === 'anon.' || !$user->getCustomer()->getCpf()) {
            $addFields = true;
        }

        if ($addFields) {
            $builder
                ->add('cpf', TextType::class, [
                    'label' => 'CPF do Titular',
                    'constraints' => [
                        new NotBlank([
                            'message' => 'CPF do Titular deve ser preenchido.',
                            'groups' => ['sylius'],
                        ]),
                    ],
                    'attr' => [
                        'data-mask' => '999.999.999-99'
                    ],
                    'data' => $this->session->get('pagseguro.credit_card')['cpf'] ?? null
                ])
                ->add('birthDate', DateType::class, [
                    'required' => true,
                    'label' => 'sylius.form.customer.birthday',
                    'widget' => 'single_text',
                    'format' => 'dd/MM/yyyy',
                    'attr' => [
                        'data-mask' => '99/99/9999'
                    ],
                    'data' => $this->session->get('pagseguro.credit_card')['birthDate'] ?? null
                ]);
        }

        $builder
            ->add('holder', TextType::class, [
                'label' => 'Nome do Titular',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Nome do Titular deve ser preenchido.',
                        'groups' => ['sylius'],
                    ]),
                ],
                'data' => $this->session->get('pagseguro.credit_card')['holder'] ?? null,
            ])
            ->add('card', TextType::class, [
                'label' => 'Número do Cartão',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Número do Cartão inválido',
                        'groups' => ['sylius'],
                    ]),
                ],
                'attr' => ['data-mask' => '9999 9999 9999 9999'],
                'data' => $this->session->get('pagseguro.credit_card')['card'] ?? null,
            ])
            ->add('cvv', NumberType::class, [
                'label' => 'CVV',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Número CVV Inválido',
                        'groups' => ['sylius'],
                    ]),
                ],
                'attr' => ['data-mask' => '999'],
                'data' => $this->session->get('pagseguro.credit_card')['cvv'] ?? null,
            ])
            ->add('month', NumberType::class, [
                'label' => 'Mês',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Mês Inválido',
                        'groups' => ['sylius'],
                    ]),
                ],
                'attr' => ['data-mask' => '99'],
                'data' => $this->session->get('pagseguro.credit_card')['month'] ?? null,
            ])
            ->add('year', NumberType::class, [
                'label' => 'Ano',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Ano Inválido',
                        'groups' => ['sylius'],
                    ]),
                ],
                'attr' => ['data-mask' => '9999'],
                'data' => $this->session->get('pagseguro.credit_card')['year'] ?? null,
            ])
            ->add('installments', ChoiceType::class, [
                'label' => 'Parcelamentos',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Selecione um parcelamento',
                        'groups' => ['sylius'],
                    ]),
                ],
                'choices' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                    '7' => '7',
                    '8' => '8',
                    '9' => '9',
                    '10' => '10',
                    '11' => '11',
                    '12' => '12'
                ],
                'data' => $this->session->get('pagseguro.credit_card')['installments'] ?? null,
            ])
            ->add('installmentAmount', HiddenType::class, [
                'label' => 'Parcelamentos',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Selecione um valor de parcelamento',
                        'groups' => ['sylius'],
                    ]),
                ],
                'data' => $this->session->get('pagseguro.credit_card')['installmentAmount'] ?? null,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

                $fields_string = '';
                foreach ($this->parameters as $key => $value) {
                    $fields_string .= $key . '=' . $value . '&';
                }
                rtrim($fields_string, '&');

                $ch = curl_init('https://ws.' . $this->parameters['sandbox'] . 'pagseguro.uol.com.br/v2/sessions');
                curl_setopt($ch, CURLOPT_POST, count($this->parameters));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);

                if(!$result) {
                    throw new \Exception('Não foi possível inciar a sessão de pagamento');
                }

                $xml = @simplexml_load_string($result);
                $json = @json_encode($xml);
                $array = @json_decode($json, TRUE);

                if ($array) {
                    $this->session->set('pagseguro.session_id', $array['id']);
                } else {
                    $this->session->set('pagseguro.session_id', $result);
                }

            })->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                $data = $event->getData();
                $this->session->set('pagseguro.credit_card', $data);
            });
    }
}
