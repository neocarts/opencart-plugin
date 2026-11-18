<?php
/**
 * The `neocarts_discount` order total (the subscriber discount of orders created from webhooks) is not
 * an installable total: addOrderHistory() loads a model for every total code of an order, so it needs one.
 */
class ModelExtensionTotalNeocartsDiscount extends Model
{
}
