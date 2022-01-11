<?php

class gingerPaymentDefault extends gingerGateway
{
    /**
     * Payment method code.
     *
     * @var string
     */
    public $code;

    /**
     * Module settings variables.
     *
     * @var string
     */
    public $enabled, $title, $description, $sort_order, $email_footer;

    /**
     * Default call to constructor.
     */
    function __construct()
    {
        global $order;

        if ($this->code != GINGER_BANK_PREFIX) {
            $this->code = implode('_', [GINGER_BANK_PREFIX, $this->code]);
        }

        $this->loadLanguageFile();

        $this->title = constant("MODULE_PAYMENT_" . strtoupper($this->code) . "_TEXT_TITLE");
        $this->description = constant("MODULE_PAYMENT_" . strtoupper($this->code) . "_TEXT_DESCRIPTION");
        $this->sort_order = constant("MODULE_PAYMENT_" . strtoupper($this->code) . "_SORT_ORDER");
        $this->enabled = (constant("MODULE_PAYMENT_" . strtoupper($this->code) . "_STATUS") == 'True');

        if (is_object($order) && $this->enabled) {
            $this->update_status();

            if ($this->isKlarnaPayLater()) {
                $this->enabled = $this->gingerKlarnaPayLaterIpFiltering();
            } elseif ($this->isAfterPay()) {
                $this->enabled = $this->gingerAfterPayIpFiltering() && $this->gingerAfterPayCountriesValidation($order);
            } elseif ($this->isApplePay()) {
                $this->enabled = $this->applePayDetection();
            } else
                if ($this->code != GINGER_BANK_PREFIX) {
                    $this->enabled = $this->gingerCurrenciesValidation($order);
                }
        }

        if ($this->enabled == true) {
            try {
                $this->ginger = static::getClient();
            } catch (Exception $exception) {
                $this->title .= '<span class="alert">' . $exception->getMessage() . '</span>';
            }
            if ($this->ginger === null) {
                $this->title .= '<span class="alert">' . constant(MODULE_PAYMENT_ . strtoupper(GINGER_BANK_PREFIX) . _ERROR_API_KEY) . '</span>';
            }
        }


    }

    /**
     * Check if Klarna Pay Later payment method is limited to specific set of IPs.
     *
     * @return mixed
     */
    function gingerKlarnaPayLaterIpFiltering()
    {
        $gingerKlarnaPayLaterIpList = constant(MODULE_PAYMENT_ . strtoupper($this->code) . _IP_FILTERING);

        if (strlen($gingerKlarnaPayLaterIpList) > 0) {
            $ip_whitelist = array_map('trim', explode(",", $gingerKlarnaPayLaterIpList));
            if (!in_array($_SESSION['customers_ip_address'], $ip_whitelist)) {
                return false;
            }
        }

        return true;
    }

    public function getMethodNameWithoutBank()
    {
        $method_name = explode('_', $this->code);
        return end($method_name);
    }

    function validateCurrenciesListFromSettings()
    {
        global $messageStack;

        $currency_option = trim(constant(MODULE_PAYMENT_ . strtoupper($this->code) . _CURRENCIES));
        $currency_list = explode(',', $currency_option);
        $check = array_map(function ($code) {
            $regex = "/^[A-Z]{3}$/";
            if (!preg_match($regex, $code)) {
                return false;
            }
            return true;
        }, $currency_list);
        if (in_array(false, $check)) {
            $this->title .= '<span style="margin: 10px; background-color: yellow; color: black;">' . constant(MODULE_PAYMENT_ . strtoupper(GINGER_BANK_PREFIX) . _WARNING_BAD_CURRENCIES_LIST) . '</span>';
        }
    }

    /**
     * @param $orderId
     * @param $status
     */
    public function _doStatusUpdate($orderId, $status)
    {
        if ($status == (int)constant("MODULE_PAYMENT_" . strtoupper(constant("GINGER_BANK_PREFIX")) . "_ORDER_STATUS_SHIPPED")) {
            $orderHistory = $this->getOrderHistory($orderId);
            $gingerOrderId = $this->searchHistoryForOrderKey($orderHistory);
            if ($gingerOrderId) {
                $this->captureOrder($gingerOrderId);
            }
        }
    }

    /**
     * Obtain Ginger order id from order history.
     *
     * @param array $orderHistory
     * @return string|null
     */
    public function searchHistoryForOrderKey(array $orderHistory)
    {
        foreach ($orderHistory as $history) {
            preg_match('/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $history['comments'], $orderKey);
            if (count($orderKey) > 0) {
                return $orderKey[0];
            }
        }
        return null;
    }

    /**
     * Default call to install function.
     *
     * @return null
     */
    public function install()
    {
        global $messageStack;

        $sort_order = 0;

        if (defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS')) {
            $messageStack->add_session(constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ERROR_ALREADY_INSTALLED), 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=' . $this->code, 'SSL'));
            return 'failed';
        }
        $this->setConfigurationField([
            'configuration_title' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _STATUS_TEXT),
            'configuration_description' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _STATUS_DESCRIPTION),
            'configuration_key' => 'MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS',
            'configuration_value' => 'True',
            'configuration_group_id' => 6,
            'sort_order' => $sort_order,
            'set_function' => "zen_cfg_select_option(array('True', 'False'), ",
        ]);
        $sort_order += 1;

        $this->setConfigurationField([
            'configuration_title' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _DISPLAY_TITLE_TEXT),
            'configuration_description' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _DISPLAY_TITLE_DESCRIPTION),
            'configuration_key' => 'MODULE_PAYMENT_' . strtoupper($this->code) . '_DISPLAY_TITLE',
            'configuration_value' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _TEXT_TITLE),
            'configuration_group_id' => 6,
            'sort_order' => $sort_order
        ]);
        $sort_order += 1;

        $this->setConfigurationField([
            'configuration_title' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _SORT_ORDER_TEXT),
            'configuration_description' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _SORT_ORDER_DESCRIPTION),
            'configuration_key' => 'MODULE_PAYMENT_' . strtoupper($this->code) . '_SORT_ORDER',
            'configuration_value' => 0,
            'configuration_group_id' => 6,
            'sort_order' => $sort_order
        ]);
        $sort_order += 1;

        $this->setConfigurationField([
            'configuration_title' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ZONE_TEXT),
            'configuration_description' => constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ZONE_DESCRIPTION),
            'configuration_key' => 'MODULE_PAYMENT_' . strtoupper($this->code) . '_ZONE',
            'configuration_value' => 0,
            'configuration_group_id' => 6,
            'sort_order' => $sort_order,
            'use_function' => 'zen_get_zone_class_title',
            'set_function' => 'zen_cfg_pull_down_zone_classes(',
        ]);
        $sort_order += 1;

        return $sort_order;
    }

    /**
     * Load translation file based on selected language.
     *
     * @param string $code
     */
    public function loadLanguageFile($code = null)
    {
        $language = $_SESSION['language'] ?: GINGER_DEFAULT_LANGUAGE;

        require_once(zen_get_file_directory(
            DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/modules/payment/',
            $code ?? $this->code . '.php'
        ));
    }

    /**
     * Check to see if module is installed.
     *
     * @return boolean
     */
    public function check()
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute(
                "SELECT `configuration_value` 
                 FROM " . TABLE_CONFIGURATION . " 
                 WHERE `configuration_key` = 'MODULE_PAYMENT_" . strtoupper($this->code) . "_STATUS'"
            );

            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    /**
     * Method un-installs the plugin.
     */
    public function remove()
    {
        global $db;

        $db->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION . " 
             WHERE configuration_key in ('" . implode("', '", $this->keys()) . "')"
        );
    }

    /**
     * @param array $config
     */
    protected function setConfigurationField(array $config)
    {
        global $db;

        $sql = "INSERT INTO " . TABLE_CONFIGURATION;
        $sql .= ' (' . implode(', ', array_keys($config)) . ', date_added)';
        $sql .= ' VALUES ("' . implode('", "', array_values($config)) . '", now())';

        $db->Execute($sql);
    }

    /**
     * Default call to function which returns array of configurable field.
     *
     * @return array
     */
    public function keys(): array
    {
        return array(
            'MODULE_PAYMENT_' . strtoupper($this->code) . '_DISPLAY_TITLE',
            'MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS',
            'MODULE_PAYMENT_' . strtoupper($this->code) . '_SORT_ORDER',
            'MODULE_PAYMENT_' . strtoupper($this->code) . '_ZONE',
            'MODULE_PAYMENT_' . strtoupper($this->code) . '_CURRENCIES'
        );
    }

    /**
     * Default call to function which getting display view on storefront.
     *
     * @return array
     */
    public function selection()
    {
        $method_name = $this->getMethodNameFromCode();
        return array_filter(
            array_merge(
                [
                    'id' => $this->code,
                    'module' => "
            <img src='" . DIR_WS_IMAGES . "ginger/ginger_" . $method_name . ".png' />
            <span style='position: relative; margin: auto; top: -10px; bottom: +10px;'>$this->title</span>"
                ],
                [
                    'fields' => $this->getAdditionalPaymentFields()
                ]
            ));
    }

    /**
     * Get Additional Fields for checkout page.
     *
     * @return array|array[]|null
     */
    public function getAdditionalPaymentFields()
    {
        switch ($this->getMethodNameWithoutBank()) {
            case 'afterpay' :
                return $this->getAfterPayFields();
            case 'ideal' :
                return [
                    $this->getIdealFields()
                ];
            default :
                return null;
        }
    }

    /**
     * Default function which validate order before processing.
     */
    public function before_process()
    {
        global $messageStack;

        if (array_key_exists('order_id', $_GET)) {
            $order_id = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING);
            $ginger_order = $this->ginger->getOrder($order_id);
            $transaction = current($ginger_order['transactions']);

            if (key_exists('customer_message', $transaction)) {
                $messageStack->add_session('checkout_payment', $transaction['customer_message'], 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            }

            $this->statusRedirect(filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING));
        }
        if ($this->code == GINGER_BANK_PREFIX . '_afterpay' && !array_key_exists('order_id', $_GET)) {
            global $messageStack;

            if (empty($_POST[$this->code . '_gender'])) {
                $messageStack->add_session('checkout_payment', constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ERROR_GENDER), 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            }

            if (empty($_POST[$this->code . '_dob'])) {
                $messageStack->add_session('checkout_payment', constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ERROR_DOB), 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            }

            if (empty($_POST[$this->code . '_terms_and_conditions'])) {
                $messageStack->add_session('checkout_payment', constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ERROR_TERMS_AND_CONDITIONS), 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            }
        }
    }

    /**
     * Default function when user clicked on checkout, the order processing method.
     */
    public function after_process()
    {
        $gingerOrder = $this->getGingerOrder();
        if (in_array($this->code, [GINGER_BANK_PREFIX . '_banktransfer'])) {
            $this->saveBankReferences($gingerOrder);
            static::updateOrderStatus($this->getOrderId(), static::getZenStatusId($gingerOrder));
            static::addOrderHistory($this->getOrderId(), static::getZenStatusId($gingerOrder), current($gingerOrder['transactions'])['order_id']);
            static::addOrderHistory($this->getOrderId(), static::getZenStatusId($gingerOrder), $_SESSION[$this->code . '_reference']);
        } else {
            zen_redirect(current($gingerOrder['transactions'])['payment_url']);
        }
    }

    /**
     * Default function of check for availability payment method in the current zone.
     *
     * @return void;
     */
    public function update_status()
    {
        $this->updateModuleVisibility(constant(MODULE_PAYMENT_ . strtoupper($this->code) . _ZONE));
        return null;
    }


}