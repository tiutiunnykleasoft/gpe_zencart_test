<?php

/**
 * Prefix for Bank functionality.
 */
const GINGER_BANK_PREFIX = 'emspay';

/**
 *  Ginger Endpoint
 */

const GINGER_BANK_ENDPOINT = 'https://api.online.emspay.eu';

/**
 *
 */
const GINGER_DEFAULT_LANGUAGE = 'english';

/**
 * Mapping statuses from Platform to EMS API
 */
const GINGER_PAYMENT_MAPPING = [
    'afterpay' => 'afterpay',
    'applepay' => 'apple-pay',
    'bancontact' => 'bancontact',
    'banktransfer' => 'bank-transfer',
    'creditcard' => 'credit-card',
    'ideal' => 'ideal',
    'klarnapaylater' => 'klarna-pay-later',
    'klarnapaynow' => 'klarna-pay-now',
    'payconiq' => 'payconiq',
    'paypal' => 'paypal',
    'amex' => 'amex',
    'tikkiepaymentrequest' => 'tikkie-payment-request',
    'wechat' => 'wechat',
    'googlepay' => 'google-pay'
];

function getBankRepoPrefix(): string
{
    return 'ems-online';
}

function getGingerPluginName(): string
{
    return 'ems-online-zencart';
}