<?php

/**
 */
class Simi_Simiconnector_Helper_Customer extends Mage_Core_Helper_Abstract
{

    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

     public function renewCustomerSesssion($data) 
     {
        if (isset($data['params']['quote_id']) && $data['params']['quote_id']) {
            if (($quote = Mage::getModel('sales/quote')->load($data['params']['quote_id'])) && $quote->getId()) {
                if (Mage::app()->getStore()->getId() == $quote->getData('store_id')) {
                    $checkoutsession = Mage::getSingleton('checkout/session');
                    $checkoutsession->setQuoteId($quote->getId());
                }
            }
        }

        if (($data['resource'] == 'customers') && (($data['resourceid'] == 'login') || ($data['resourceid'] == 'sociallogin')))
            return;
        
        if (isset($data['params']['email']) && isset($data['params']['simi_hash'])) {
            $data['params']['password'] = $data['params']['simi_hash'];
        } else if (isset($data['contents_array']['email'])) {
            if (isset($data['contents_array']['password'])) {
                $data['params']['email']    = $data['contents_array']['email'];
                $data['params']['password'] = $data['contents_array']['password'];
            } else if (isset($data['contents_array']['simi_hash'])) {
                $data['params']['email']    = $data['contents_array']['email'];
                $data['params']['password'] = $data['contents_array']['simi_hash'];
            }
        }

        if ((!isset($data['params']['email'])) || (!isset($data['params']['password'])))
            return;
        
        if ((Mage::getSingleton('customer/session')->isLoggedIn()) && (Mage::getSingleton('customer/session')->getCustomer()->getEmail() == $data['params']['email'])) 
            return;
            
        try {
            $this->loginByEmailAndPass($data['params']['email'], $data['params']['password']);
        } catch (Exception $e) {
        }
     }

    public function loginByEmailAndPass($username, $password)
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId);
        if ($this->validateSimiPass($username, $password)) {
            $customer = $this->getCustomerByEmail($username);
            if ($customer->getId()) {
                $this->loginByCustomer($customer);
                return true;
            }
        } else if ($customer->authenticate($username, $password)) {
            $this->loginByCustomer($customer);
            return true;
        }

        return false;
    }

    public function getCustomerByEmail($email)
    {
        return Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($email);
    }

    public function loginByCustomer($customer)
    {
        $this->_getSession()->setCustomerAsLoggedIn($customer);
    }

    public function validateSimiPass($username, $password)
    {
        $tokenModel = Mage::getModel('simiconnector/customertoken')
            ->getCollection()
            ->addFieldToFilter('token', $password)
            ->getFirstItem();
        if ($tokenModel->getId() && $customerId = $tokenModel->getData('customer_id')) {
            $customerModel = Mage::getModel('customer/customer')->load($customerId);
            if ($customerEmail = $customerModel->getData('email')) {
                if ($customerEmail == $username)
                    return true;
            }
        }
        /*
        if ($password == md5(Mage::getStoreConfig('simiconnector/general/secret_key') . $username)) {
            return true;
        }
        */
        return false;
    }


    public function getToken($data) {
        $customerSession = $this->_getSession();
        if ($customerSession->isLoggedIn()) {
            $customerId = $this->_getSession()->getCustomer()->getId();
            if ($customerId) {
                $createNewToken = false;
                if ($data && isset($data['resourceid']) && $data['resourceid'] == 'login')
                    $createNewToken = true;
                else if ($data && isset($data['resource']) && $data['resource'] == 'sociallogins')
                    $createNewToken = true;

                $tokenModel = Mage::getModel('simiconnector/customertoken')
                    ->getCollection()
                    ->addFieldToFilter('customer_id', $customerId)
                    ->getFirstItem();

                if (!$tokenModel->getId() || $createNewToken) {
                    $newToken = 'tk_'
                        . md5(rand(pow(10, 9), pow(10, 10)))
                        . md5(microtime());
                    $tokenModel->setData('token', $newToken);
                    $tokenModel->setData('customer_id', $customerId);
                    $tokenModel->setData('created_time', time());
                    $tokenModel->save();
                }
                return $tokenModel->getData('token');
            }
        }
        return '';
    }
}
