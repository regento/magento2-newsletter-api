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
namespace Regento\NewsletterAPI\Model;

use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\DataObject;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * {@inheritDoc}
 */
class NewsletterManagement implements \Regento\NewsletterAPI\Api\NewsletterManagementInterface
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var CustomerAccountManagement
     */
    protected $customerAccountManagement;

    /**
     * @var SubscriberFactory
     */
    protected $_subscriberFactory;

    /**
     * Initialize dependencies.
     *
     * @param Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Session $customerSession
     * @param CustomerAccountManagement $customerAccountManagement
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(
        Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Session $customerSession,
        CustomerAccountManagement $customerAccountManagement,
        SubscriberFactory $subscriberFactory
    ) {
        $this->_objectManager = $context->getObjectManager();
        $this->_storeManager = $storeManager;
        $this->_customerSession = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->_subscriberFactory = $subscriberFactory;
    }

    /**
     * Validates the format of the email address
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     *
     * @param string $email
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    protected function validateEmailFormat($email)
    {
        if (!\Zend_Validate::is($email, \Magento\Framework\Validator\EmailAddress::class)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please enter a valid email address.'));
        }
    }

    /**
     * Validates that if the current user is a guest, that they can subscribe to a newsletter.
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    protected function validateGuestSubscription()
    {
        if ($this->_objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class)
                ->getValue(
                    \Magento\Newsletter\Model\Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ) != 1
            && !$this->_customerSession->isLoggedIn()
        ) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Sorry, but the administrator denied subscription for guests. Please <a href="%1">register</a>.',
                    $this->_customerUrl->getRegisterUrl()
                )
            );
        }
    }

    /**
     * Validates that the email address isn't being used by a different account.
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     *
     * @param string $email
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    protected function validateEmailAvailable($email)
    {
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        if ($this->_customerSession->getCustomerDataObject()->getEmail() !== $email
            && !$this->customerAccountManagement->isEmailAvailable($email, $websiteId)
        ) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('This email address is already assigned to another user.')
            );
        }
    }

    /**
     * {@inheritDoc}
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     */
    public function subscribe($email)
    {
        $success = false;
        $message = '';

        try {
            $this->validateEmailFormat($email);
            $this->validateGuestSubscription();
            $this->validateEmailAvailable($email);

            $subscriber = $this->_subscriberFactory->create()->loadByEmail($email);
            if ($subscriber->getId()
                && $subscriber->getSubscriberStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
            ) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('This email address is already subscribed.')
                );
            }

            $status = $this->_subscriberFactory->create()->subscribe($email);
            if ($status == \Magento\Newsletter\Model\Subscriber::STATUS_NOT_ACTIVE) {
                $success = true;
                $message = __('The confirmation request has been sent.');
            } else {
                $success = true;
                $message = __('Thank you for your subscription.');
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $message = __('There was a problem with the subscription: %1', $e->getMessage());
        } catch (\Exception $e) {
            $message = __('There was a problem with the subscription: %1', $e->getMessage());
        }

        $data = array(
            'success' => $success,
            'message' => $message,
        );

        $result = new DataObject();
        $result->setData($data);
        return $result;
    }
}
