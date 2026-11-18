<?php
namespace Opencart\Catalog\Model\Extension\Neocarts\Total;

/**
 * The «Subscriber discount» line of orders created from webhooks. OpenCart 4.1's addHistory() loads
 * the model of every order total (extension/<extension>/total/<code>); it has nothing to confirm.
 */
class NeocartsDiscount extends \Opencart\System\Engine\Model {
}
