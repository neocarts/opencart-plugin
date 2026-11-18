<?php
namespace Neocarts;

/** Neocarts addresses the plugin shows to the merchant. One place, never hard-coded elsewhere. */
final class Links
{
    const ADMIN_URL = 'https://shop.neocarts.com/';
    const SUPPORT_EMAIL = 'support@neocarts.com';
    /** the shopper's own subscriptions (neocarts customer-portal) */
    const CUSTOMER_URL = 'https://account.neocarts.com/';
    const DOCS_URL = 'https://docs.neocarts.com/plugins/opencart/overview/';
    const DOCS_URL_EN = 'https://docs.neocarts.com/en/plugins/opencart/overview/';

    /** The docs in the admin's language: uk at the root, everything else in English. */
    public static function docs(string $ocLanguage): string
    {
        return Locale::resolve($ocLanguage) === 'uk' ? self::DOCS_URL : self::DOCS_URL_EN;
    }
}
