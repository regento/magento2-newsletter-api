<?php
/**
 * Regento
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Regento
 * @package     Regento_NewsletterAPI
 * @copyright   Copyright (c)  (https://regento.net/)
 */
namespace Regento\NewsletterAPI\Api;

/**
 * Newsletter interface.
 * @api
 */
interface NewsletterManagementInterface
{
    /**
     * Subscribe an email.
     *
     * @param string $email
     * @return \Regento\NewsletterAPI\Api\Data\NewsletterSubscribeInterface
     */
    public function subscribe($email);
}
