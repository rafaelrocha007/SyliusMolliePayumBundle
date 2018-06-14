# SyliusPagseguroPayumBundle

Welcome to the SyliusPagseguroPayumBundle - a Payum implementation of the UOL Pagseguro gateway to use in your Sylius (~beta) webshop.

For details on how to get started with SyliusMolliePayumBundle, keep on reading.

All code included in the SyliusMolliePayumBundle is released under the MIT or BSD license.

## Installation

### Step 1 - Install SyliusMolliePayumBundle using composer
Edit your composer.json to include the bundle as a dependency.

```js
{
    "require": {
        "evirtua/sylius-pagseguro-payum-bundle": "dev-master",
    }
}
```

Open up a command line window and tell composer to download the new dependency.

``` bash
$ php composer.phar update evirtua/sylius-pagseguro-payum-bundle
```

### Step 2 - Register the bundle in your AppKernel file


``` php
// app/AppKernel.php

<?php

public function registerBundles()
{
    $bundles = array(
        ...
        new Evirtua\SyliusPagseguroPayumBundle\SyliusMolliePayumBundle(),
    );
}
```

### Step 3 - Include the bundles payment gateway config

``` yml
// app/config/config.yml

imports:
    - { resource: "@SyliusPagseguroPayumBundle/Resources/config/config.yml" }

```

### Step 4 - Include the bundle routing

``` yml
// app/config/routing.yml

sylius_pagseguro_payum_bundle:
    resource: "@SyliusPagseguroPayumBundle/Resources/config/routing.yml"
    prefix:   /


```

## Modifying default behavior

You can always change behavior by changing the parameter value for the classes.

``` yml
// app/config/parameters.yml

parameters:
    evirtua.payum.action.capture.class: Your\Own\CaptureAction
    evirtua.payum.action.status.class: Your\Own\StatusAction
    evirtua.payum.action.notify.class: Your\Own\NotifyAction
    evirtua.payum.action.resolve_next_route.class: Your\Own\ResolveNextRouteAction
```

## Nice to know..
By default, Sylius moves an order's checkout state to completed before knowing if the payment is fulfilled or not. 

In my ResolveNextRouteAction you can see that I redirect to the last step in the checkout process if the payment has failed for some reason. But Sylius removes a cart if the state is not longer new. So you will end up with an empty cart upon returning from Mollie if the payment has been cancelled or has failed. 

To prevent this from happening you should either fiddle with the state machine, or you simply do this in your custom CaptureAction :

``` php
<?php

$order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
```

And this in your custom ResolveNextRouteAction

``` php
<?php

/** @var Payment $payment */
$payment = $request->getModel();

/** @var Order $order */
$order = $payment->getOrder();

if ($payment->getState() === Payment::STATE_COMPLETED) {
    $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
    $this->orderEmailManager->sendConfirmationEmail($order);
    $request->setRouteName('sylius_shop_order_thank_you');

    return;
}
$order->setState(OrderInterface::STATE_CART);
$order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
$order->setShippingState(OrderShippingStates::STATE_READY);
$order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

$request->setRouteName('sylius_shop_checkout_complete');
```
