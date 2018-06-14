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
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Order\Model\OrderInterface;
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

final class BoletoType extends AbstractType
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


        /** @var Order $order */
        $order = $this->container->get('sylius.repository.order')->find($this->session->get('_sylius.cart.default'));
        $gatewayConfig = $order->getPayments()[0]->getMethod()->getGatewayConfig()->getConfig();

        $this->parameters = [];
        $this->parameters['token'] = $gatewayConfig['token'];
        $this->parameters['email'] = $gatewayConfig['email'];
        $this->parameters['sandbox'] = $gatewayConfig['environment'] !== 'production' ? 'sandbox.' : '';
        //echo '<body><pre>' . var_dump($this->container->get('session')) . '</pre></body>';
//        echo '<body><pre>' . var_dump($gatewayConfig) . '</pre></body>';
//        die();

        $securityContext = $this->container->get('security.token_storage');
        $user = $securityContext->getToken()->getUser();

        $addFields = false;
        if ($user === 'anon.' || !$user->getCustomer()->getCpf()) {
            $addFields = true;
        }

        if ($addFields) {
            $builder
                ->add('cpf', TextType::class, [
                    'label' => 'Informe seu CPF',
                    'constraints' => [
                        new NotBlank([
                            'message' => 'CPF do Titular deve ser preenchido.',
                            'groups' => ['sylius'],
                        ]),
                    ],
                    'attr' => [
                        'data-mask' => '999.999.999-99'
                    ],
                    'data' => $this->session->get('pagseguro.boleto')['cpf'] ?? null
                ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

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
            $this->session->set('pagseguro.boleto', $data);
        });
    }
}
