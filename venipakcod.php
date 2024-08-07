<?php
if (!defined('_PS_VERSION_'))
	exit;

class VenipakCod extends PaymentModule
{
	private $_prefix = 'VENIPAKCOD_';

	public $_path;

    public static $_moduleDir = _PS_MODULE_DIR_ . 'venipakcod/';

	public function __construct()
	{
		$this->name = 'venipakcod';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.4';
		$this->author = 'Mijora';
		$this->need_instance = 1;
		$this->controllers = array('validation', 'payment');
		$this->is_eu_compatible = 1;

		$this->currencies = false;
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Venipak Cash on Delivery (COD)');
		$this->description = $this->l('Accept cash on delivery payments');
	}

	public function install()
	{
		if (!parent::install() or !$this->registerHook('payment') or !$this->registerHook('displayPaymentEU')
            or !$this->registerHook('paymentReturn') or !$this->registerHook('header') or !$this->registerHook('paymentOptions'))
			return false;

		Configuration::updateValue('VENIPAKCOD_STATUS', '1');
		return true;
	}

	public function postProcess()
	{
		// save settings
		foreach (array('FEE_TYPE', 'FEE_FLAT', 'FEE_PERC', 'TOTAL_MIN', 'TOTAL_MAX', 'TOTAL_FREE') as $key) {
			if ($key == 'FEE_TYPE') {
				$value = (int) Tools::getValue($this->_prefix . $key);
			} else {
				$value = (float) Tools::getValue($this->_prefix . $key);
			}

			Configuration::updateValue($this->_prefix . $key, $value);
		}

		// checkbox settings handling
		$carriers = array();
		foreach ($_POST as $key => $value) {
			if (strpos($key, $this->_prefix . 'CARRIERS_') !== false) {
				$carriers[] = str_replace($this->_prefix . 'CARRIERS_', '', $key);
			}
		}

		Configuration::updateValue($this->_prefix . 'CARRIERS', implode(',', $carriers));

		return $this->displayConfirmation($this->l('Settings updated'));
	}

	public function getContent()
	{
		$output = '';
		if (Tools::isSubmit('submit' . $this->name)) {
			$output .= $this->postProcess();
		}

		return $output .  $this->displaySettings(); //$output;
	}

	public function displaySettings()
	{
		// Get default language
		$defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

		$carriers = Carrier::getCarriers($defaultLang, false, false, false, null, 0); //array();

		// Init Fields form array
		$fieldsForm[0]['form'] = [
			'legend' => array(
				'title' => 'Module Settings',
			),
			'input' => array(
				array(
					'type' => 'html',
					'label' => $this->l('Fee:'),
					'name' => 'venipak_fee_html',
					'html_content' => '',
				),
				array(
					'type' => 'switch',
					'is_bool' => true,
					'label' => $this->l('Use % fee type?'),
					'name' => 'VENIPAKCOD_FEE_TYPE',
					'values' => array(
						array(
							'value' => 1,
							'label' => $this->l('YES')
						),
						array(
							'value' => 0,
							'label' => $this->l('NO')
						)
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Flat fee'),
					'name' => 'VENIPAKCOD_FEE_FLAT',
					'size' => 20
				),
				array(
					'type' => 'text',
					'label' => $this->l('% fee'),
					'name' => 'VENIPAKCOD_FEE_PERC',
					'size' => 20,
				),
				array(
					'type' => 'html',
					'label' => $this->l('Order total:'),
					'name' => 'venipak_enable_html',
					'html_content' => '',
				),
				array(
					'type' => 'text',
					'label' => $this->l('MIN'),
					'name' => 'VENIPAKCOD_TOTAL_MIN',
					'size' => 20,
				),
				array(
					'type' => 'text',
					'label' => $this->l('MAX'),
					'name' => 'VENIPAKCOD_TOTAL_MAX',
					'size' => 20,
					'desc' => $this->l('MIN MAX defines range where fee is active')
				),
				array(
					'type' => 'html',
					'label' => '',
					'name' => 'venipak_carriers_html',
					'html_content' => '',
				),
				array(
					'type' => 'checkbox',
					'label' => $this->l('Shipping carriers'),
					'name' => 'VENIPAKCOD_CARRIERS',
					'desc' => $this->l('Select shipping carriers to use Venipak COD with. Select none to enable for all.'),
					'values' => array(
						'query' => $carriers,
						'id' => 'id_reference',
						'name' => 'name'
					)
				),
			),
			'submit' => [
				'title' => $this->l('Save'),
				'class' => 'btn btn-default pull-right'
			]
		];

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

		// Language
		$helper->default_form_language = $defaultLang;
		$helper->allow_employee_form_lang = $defaultLang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit' . $this->name;
		$helper->toolbar_btn = [
			'save' => [
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
					'&token=' . Tools::getAdminTokenLite('AdminModules'),
			],
			'back' => [
				'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			]
		];

		// load saved settings
		foreach (array('FEE_TYPE', 'FEE_FLAT', 'FEE_PERC', 'TOTAL_MIN', 'TOTAL_MAX', 'TOTAL_FREE') as $key) {
			$helper->fields_value[$this->_prefix . $key] = Configuration::get($this->_prefix . $key);
		}

		// check cod module boxes
		$enabled_carriers = explode(',', Configuration::get($this->_prefix . 'CARRIERS'));

		if (!$enabled_carriers) {
			$enabled_carriers = array();
		}

		foreach ($enabled_carriers as $carrier) {
			$helper->fields_value[$this->_prefix . 'CARRIERS_' . $carrier] = true;
		}

		return $helper->generateForm($fieldsForm);
	}

	public function hasProductDownload($cart)
	{
		foreach ($cart->getProducts() as $product) {
			$pd = ProductDownload::getIdFromIdProduct((int) ($product['id_product']));
			if ($pd and Validate::isUnsignedInt($pd))
				return true;
		}
		return false;
	}

	/**
	 * Return calculated fee or false if not within range
	 * @param mixed $cart	Cart object
	 * 
	 * @return bool|float
	 */
	public function getCodFee($cart)
	{
		$cart_total = (float) $cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING);
		$min = (float) Configuration::get($this->_prefix . 'TOTAL_MIN');
		$max = (float) Configuration::get($this->_prefix . 'TOTAL_MAX');

		// if min or max is set (not 0) check total is within range
		if (($min && $cart_total < $min) || ($max && $cart_total > $max)) {
			return false;
		}

		if (Configuration::get($this->_prefix . 'FEE_TYPE')) {
			// percentile fee
			$fee = $cart_total * ((float) Configuration::get($this->_prefix . 'FEE_PERC') / 100);
		} else {
			// flat fee
			$fee = (float) Configuration::get($this->_prefix . 'FEE_FLAT');
		}

		return $fee > 0 ? $fee : false;
	}

	public function hookDisplayHeader($params)
    {
        $add_content = false;
        if(version_compare(_PS_VERSION_, '1.7', '>='))
        {
            $add_content = $this->context->controller->php_self == 'order' && $this->check17PaymentStep($this->context->cart);
        }
        // 1.6
        else
        {
            $add_content = ($this->context->controller->php_self == 'order' && isset($this->context->controller->step) && $this->context->controller->step == 3) ||  $this->context->controller->php_self == 'order-opc';
        }
        if ($add_content)
        {
            $venipakModule = Module::getInstanceByName('mijoravenipak');

            $address = new Address($params['cart']->id_address_delivery);
            $filter = [];
            if($this->context->controller->php_self != 'order-opc')
                $filter = ['cod_enabled' => 1];
            $filtered_terminals = $venipakModule->getFilteredTerminals($filter);

            $address_query = $address->address1 . ' ' . $address->postcode . ', ' . $address->city;
            Media::addJsDef(array(
                    'cod_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax'),
                    'mjvp_front_controller_url' => $this->context->link->getModuleLink($venipakModule->name, 'front'),
                    'address_query' => $address_query,
                    'mjvp_translates' => array(
                        'loading' => $this->l('Loading'),
                    ),
                    'images_url' => $this->_path . 'views/images/',
                    'mjvp_terminal_select_translates' => array(
                        'modal_header' => $this->l('Pickup points map'),
                        'terminal_list_header' => $this->l('Pickup points list'),
                        'seach_header' => $this->l('Search around'),
                        'search_btn' => $this->l('Find'),
                        'modal_open_btn' => $this->l('Select a pickup point'),
                        'geolocation_btn' => $this->l('Use my location'),
                        'your_position' => $this->l('Distance calculated from this point'),
                        'nothing_found' => $this->l('Nothing found'),
                        'no_cities_found' => $this->l('There were no cities found for your search term'),
                        'geolocation_not_supported' => $this->l('Geolocation is not supported'),
                        'select_pickup_point' => $this->l('Select a pickup point'),
                        'search_placeholder' => $this->l('Enter postcode/address'),
                        'workhours_header' => $this->l('Workhours'),
                        'contacts_header' => $this->l('Contacts'),
                        'no_pickup_points' => $this->l('No points to select'),
                        'select_btn' => $this->l('select'),
                        'back_to_list_btn' => $this->l('reset search'),
                        'no_information' => $this->l('No information'),
                    ),
                    'mjvp_terminals' => $filtered_terminals
                )
            );

            // 1.7
            if(version_compare(_PS_VERSION_, '1.7', '>='))
            {
                $this->context->smarty->assign(
                    ['images_url' => $this->_path . 'views/images/']
                );
                $this->context->controller->registerJavascript('payment-terminal', 'modules/' . $this->name . '/views/js/payment-terminal.js');
                Media::addJsDef([
                        'mjvp_map_template' => $this->context->smarty->fetch(__DIR__ . '/views/templates/front/map-template.tpl'),
                    ]
                );
                $this->context->controller->registerJavascript('modules-mjvp-terminals-mapping-js', 'modules/' . $this->name . '/views/js/terminal-mapping.js');
                $this->context->controller->registerJavascript('modules-mjvp-terminals-mapinit-js', 'modules/' . $this->name . '/views/js/terminals_map_init.js');

            }
            // 1.6
            else
            {
                $this->context->controller->addJS('modules/' . $this->name . '/views/js/payment-terminal.js');
                $this->context->controller->addJS('modules/' . $this->name . '/views/js/terminal-mapping.js');
                $this->context->controller->addJS('modules/' . $this->name . '/views/js/terminals_map_init.js');
            }
            $this->context->controller->addCSS($this->_path . 'views/css/global.css');
            $this->context->controller->addCSS($this->_path . 'views/css/three-dots.min.css');
            $this->context->controller->addCSS($this->_path . 'views/css/terminal-mapping.css');
        }
    }

	// Separate method, as methods of gettign a checkout step on 1.7 are inconsistent among minor versions.
    public function check17PaymentStep($cart)
    {
        if(version_compare(_PS_VERSION_, '1.7', '>'))
        {
            $rawData = Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int) $cart->id
            );
            $data = json_decode($rawData, true);
            if (!is_array($data)) {
                $data = [];
            }
			
            if((isset($data['checkout-delivery-step']) && $data['checkout-delivery-step']['step_is_complete']) &&
                (isset($data['checkout-payment-step']) && !$data['checkout-payment-step']['step_is_complete'])
            )
            {
                return true;
            }
        }
        return false;
    }

	public function hookPayment($params)
	{
        if (!$this->active)
            return;

        if (!$this->isCarrierAllowed($params['cart']->id_carrier))
            return;

        global $smarty;

        // Check if cart has product download
        if ($this->hasProductDownload($params['cart']))
            return false;

        $smarty->assign($this->getTemplateVarInfos($params));
        return $this->display(__FILE__, 'payment.tpl');
        }

	public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->isCarrierAllowed($params['cart']->id_carrier)) {
            return [];
        }
        $this->smarty->assign(
            $this->getTemplateVarInfos($params)
        );

		$info_txt = $this->l('You will be able to pay with bank card on delivery.');
		$fee = $this->getCodFee($params['cart']);
		if ( $fee > 0 ) {
			$fee = (float) Tools::ps_round($fee, 2);
			$info_txt .= '<span class="venipakCOD_info">' . sprintf($this->l('This payment method will add an additional fee (%s) to the shipping price.'), '<b>' . Tools::displayPrice($fee, $this->context->currency, false) . '</b>') . '</span>';
		}

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->l('Pay by cash on delivery (COD)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation($info_txt)
            ->setLogo(Media::getMediaPath(dirname(__FILE__) . '/views/images/logo_small.png'));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

	public function getTemplateVarInfos($params)
    {
        return [
            'title' => $this->l('Venipak COD'),
            'cod_fee' => Tools::displayPrice(Tools::convertPrice($this->getCodFee($params['cart']))),
            'this_path' => $this->_path, //keep for retro compat
            'this_path_cod' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ];
    }

	public function isCarrierAllowed($id_carrier)
	{
		$carrier = new Carrier((int) $id_carrier);
		$enabled_carriers = explode(',', Configuration::get($this->_prefix . 'CARRIERS'));

		// if nothing selected allow all carriers
		if (!$enabled_carriers) {
			return true;
		}

		return in_array($carrier->id_reference, $enabled_carriers);
	}

	public function hookDisplayPaymentEU($params)
	{
        // 1.7
        if(version_compare(_PS_VERSION_, '1.7', '>='))
        {
            if (!$this->active) {
                return [];
            }
            return [];
        }
        // 1.6
        else
        {
            if (!$this->active)
                return;

            if (!$this->isCarrierAllowed($params['cart']->id_carrier))
                return;

            // Check if cart has product download
            if ($this->hasProductDownload($params['cart']))
                return false;

            return array(
                'cta_text' => $this->l('Pay with cash on delivery (COD)'),
                'logo' => Media::getMediaPath(dirname(__FILE__) . '/logo.png'),
                'action' => $this->context->link->getModuleLink($this->name, 'validation', array('confirm' => true), true)
            );
        }
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;

        $this->smarty->assign(array(
            'shop_name' => $this->context->shop->name,
        ));

		return $this->display(__FILE__, 'confirmation.tpl');
	}

	/**
	 * Validate an order in database
	 * Function called from a payment module
	 *
	 * @param integer $id_cart Value
	 * @param integer $id_order_state Value
	 * @param float $amount_paid Amount really paid by customer (in the default currency)
	 * @param string $payment_method Payment method (eg. 'Credit cash')
	 * @param string $message Message to attach to order
	 */
	public function validateOrder(
		$id_cart,
		$id_order_state,
		$amount_paid,
		$payment_method = 'Unknown',
		$message = null,
		$extra_vars = array(),
		$currency_special = null,
		$dont_touch_amount = false,
		$secure_key = false,
		Shop $shop = null,
		string $order_reference = null
	) {
		$this->context->cart = new Cart($id_cart);
		$this->context->customer = new Customer($this->context->cart->id_customer);
		$this->context->language = new Language($this->context->cart->id_lang);
		$this->context->shop = ($shop ? $shop : new Shop($this->context->cart->id_shop));
		$id_currency = $currency_special ? (int) $currency_special : (int) $this->context->cart->id_currency;
		$this->context->currency = new Currency($id_currency, null, $this->context->shop->id);
		if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery')
			$context_country = $this->context->country;

		$order_status = new OrderState((int) $id_order_state, (int) $this->context->language->id);
		if (!Validate::isLoadedObject($order_status))
			throw new PrestaShopException('Can\'t load Order state status');

		if (!$this->active)
			die(Tools::displayError());
		// Does order already exists ?
		if (Validate::isLoadedObject($this->context->cart) && $this->context->cart->OrderExists() == false) {
			if ($secure_key !== false && $secure_key != $this->context->cart->secure_key)
				die(Tools::displayError());

			// For each package, generate an order
			$delivery_option_list = $this->context->cart->getDeliveryOptionList();
			$package_list = $this->context->cart->getPackageList();
			$cart_delivery_option = $this->context->cart->getDeliveryOption();

			// If some delivery options are not defined, or not valid, use the first valid option
			foreach ($delivery_option_list as $id_address => $package)
				if (!isset($cart_delivery_option[$id_address]) || !array_key_exists($cart_delivery_option[$id_address], $package))
					foreach ($package as $key => $val) {
						$cart_delivery_option[$id_address] = $key;
						break;
					}

			$order_list = array();
			$order_detail_list = array();
			$reference = Order::generateReference();
			$this->currentOrderReference = $reference;

			$order_creation_failed = false;
			$cart_total_paid = (float) Tools::ps_round((float) $this->context->cart->getOrderTotal(true, Cart::BOTH), 2);

			if ($this->context->cart->orderExists()) {
				$error = Tools::displayError('An order has already been placed using this cart.');
				Logger::addLog($error, 4, '0000001', 'Cart', intval($this->context->cart->id));
				die($error);
			}

			foreach ($cart_delivery_option as $id_address => $key_carriers)
				foreach ($delivery_option_list[$id_address][$key_carriers]['carrier_list'] as $id_carrier => $data)
					foreach ($data['package_list'] as $id_package) {
						// Rewrite the id_warehouse
						/////////////////////////////////////////////////////////////////////
						if (method_exists($this->context->cart, 'getPackageIdWarehouse'))
							$package_list[$id_address][$id_package]['id_warehouse'] = (int) $this->context->cart->getPackageIdWarehouse($package_list[$id_address][$id_package], (int) $id_carrier);
						$package_list[$id_address][$id_package]['id_carrier'] = $id_carrier;
					}
			// Make sure CarRule caches are empty
			CartRule::cleanCache();

			foreach ($package_list as $id_address => $packageByAddress)
				foreach ($packageByAddress as $id_package => $package) {
					$order = new Order();
					$order->product_list = $package['product_list'];

					if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
						$address = new Address($id_address);
						$this->context->country = new Country($address->id_country, $this->context->cart->id_lang);
					}

					$carrier = null;
					if (!$this->context->cart->isVirtualCart() && isset($package['id_carrier'])) {
						$carrier = new Carrier($package['id_carrier'], $this->context->cart->id_lang);
						$order->id_carrier = (int) $carrier->id;
						$id_carrier = (int) $carrier->id;
					} else {
						$order->id_carrier = 0;
						$id_carrier = 0;
					}

					$order->id_customer = (int) $this->context->cart->id_customer;
					$order->id_address_invoice = (int) $this->context->cart->id_address_invoice;
					$order->id_address_delivery = (int) $id_address;
					$order->id_currency = $this->context->currency->id;
					$order->id_lang = (int) $this->context->cart->id_lang;
					$order->id_cart = (int) $this->context->cart->id;
					$order->reference = $reference;
					$order->id_shop = (int) $this->context->shop->id;
					$order->id_shop_group = (int) $this->context->shop->id_shop_group;

					$order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($this->context->customer->secure_key));
					$order->payment = $payment_method;
					if (isset($this->name))
						$order->module = $this->name;
					$order->recyclable = $this->context->cart->recyclable;
					$order->gift = (int) $this->context->cart->gift;
					$order->gift_message = $this->context->cart->gift_message;
					$order->conversion_rate = $this->context->currency->conversion_rate;
					$amount_paid = !$dont_touch_amount ? Tools::ps_round((float) $amount_paid, 2) : $amount_paid;
					$order->total_paid_real = 0;

					$order->total_products = (float) $this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);
					$order->total_products_wt = (float) $this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);

					$order->total_discounts_tax_excl = (float) abs($this->context->cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
					$order->total_discounts_tax_incl = (float) abs($this->context->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
					$order->total_discounts = $order->total_discounts_tax_incl;

					/////////////////////////////////////////////////////////////  
					$fee = Tools::convertPrice((float) $this->getCodFee($this->context->cart), $order->id_currency);
					///////////////////////////////////////////////////////////// 
					if (!is_null($carrier) && Validate::isLoadedObject($carrier))
						$order->carrier_tax_rate = $carrier->getTaxesRate(new Address($this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));

					$feewithout = $fee;
					// fee already contains tax
					if ($order->carrier_tax_rate > 0 && $fee > 0) {
						$feewithout = (float) Tools::ps_round($fee -  (float) $fee / (100 +  $order->carrier_tax_rate) * $order->carrier_tax_rate, 2);
					}


					$order->total_shipping_tax_excl = (float) $this->context->cart->getPackageShippingCost((int) $id_carrier, false, null, $order->product_list) + $feewithout;
					$order->total_shipping_tax_incl = (float) $this->context->cart->getPackageShippingCost((int) $id_carrier, true, null, $order->product_list) + $fee;;
					$order->total_shipping = $order->total_shipping_tax_incl;


					$order->total_wrapping_tax_excl = (float) abs($this->context->cart->getOrderTotal(false, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
					$order->total_wrapping_tax_incl = (float) abs($this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
					$order->total_wrapping = $order->total_wrapping_tax_incl;

					/////////////////////////////////////////////////////////////
					$order->total_paid_tax_excl = (float) Tools::ps_round((float) $this->context->cart->getOrderTotal(false, Cart::BOTH, $order->product_list, $id_carrier) + $feewithout, 2);
					$order->total_paid_tax_incl = (float) Tools::ps_round((float) $this->context->cart->getOrderTotal(true, Cart::BOTH, $order->product_list, $id_carrier) + $fee, 2);
					$order->total_paid = $order->total_paid_tax_incl;

					$order->invoice_date = '0000-00-00 00:00:00';
					$order->delivery_date = '0000-00-00 00:00:00';

					// Creating order
					$result = $order->add();

					if (!$result)
						throw new PrestaShopException('Can\'t save Order');

					// Amount paid by customer is not the right one -> Status = payment error
					// We don't use the following condition to avoid the float precision issues : http://www.php.net/manual/en/language.types.float.php
					// if ($order->total_paid != $order->total_paid_real)
					// We use number_format in order to compare two string
					/////////////////////////////////////////////////////////////
					if ($order_status->logable && number_format($cart_total_paid + $fee, 2) != number_format($amount_paid + $fee, 2))
						$id_order_state = Configuration::get('PS_OS_ERROR');

					$order_list[] = $order;

					// Insert new Order detail list using cart for the current order
					$order_detail = new OrderDetail(null, null, $this->context);
					$order_detail->createList($order, $this->context->cart, $id_order_state, $order->product_list, 0, true, $package_list[$id_address][$id_package]['id_warehouse']);
					$order_detail_list[] = $order_detail;

					// Adding an entry in order_carrier table
					if (!is_null($carrier)) {
						$order_carrier = new OrderCarrier();
						$order_carrier->id_order = (int) $order->id;
						$order_carrier->id_carrier = (int) $id_carrier;
						$order_carrier->weight = (float) $order->getTotalWeight();
						$order_carrier->shipping_cost_tax_excl = (float) $order->total_shipping_tax_excl;
						$order_carrier->shipping_cost_tax_incl = (float) $order->total_shipping_tax_incl;
						$order_carrier->add();
					}
				}

			// The country can only change if the address used for the calculation is the delivery address, and if multi-shipping is activated
			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery')
				$this->context->country = $context_country;

			// Register Payment only if the order status validate the order
			if ($order_status->logable) {
				// $order is the last order loop in the foreach
				// The method addOrderPayment of the class Order make a create a paymentOrder
				//     linked to the order reference and not to the order id
				if (isset($extra_vars['transaction_id']))
					$transaction_id = $extra_vars['transaction_id'];
				else
					$transaction_id = null;

				if (!$order->addOrderPayment($amount_paid, null, $transaction_id))
					throw new PrestaShopException('Can\'t save Order Payment');
			}

			// Next !
			$only_one_gift = false;
			$cart_rule_used = array();
			$products = $this->context->cart->getProducts();
			$cart_rules = $this->context->cart->getCartRules();

			// Make sure CarRule caches are empty
			CartRule::cleanCache();

			foreach ($order_detail_list as $key => $order_detail) {
				$order = $order_list[$key];
				if (!$order_creation_failed & isset($order->id)) {
					if (!$secure_key)
						$message .= '<br />' . Tools::displayError('Warning: the secure key is empty, check your payment account before validation');
					// Optional message to attach to this order
					if (isset($message) & !empty($message)) {
						$msg = new Message();
						$message = strip_tags($message, '<br>');
						if (Validate::isCleanHtml($message)) {
							$msg->message = $message;
							$msg->id_order = intval($order->id);
							$msg->private = 1;
							$msg->add();
						}
					}

					// Insert new Order detail list using cart for the current order
					//$orderDetail = new OrderDetail(null, null, $this->context);
					//$orderDetail->createList($order, $this->context->cart, $id_order_state);

					// Construct order detail table for the email
					$product_var_tpl_list = [];
					$virtual_product = true;
		            foreach ($products as $product) {
		                $price = Product::getPriceStatic((int) $product['id_product'], false, ($product['id_product_attribute'] ? (int) $product['id_product_attribute'] : null), 6, null, false, true, $product['cart_quantity'], false, (int) $order->id_customer, (int) $order->id_cart, (int) $order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}, $specific_price, true, true, null, true, $product['id_customization']);
		                $price_wt = Product::getPriceStatic((int) $product['id_product'], true, ($product['id_product_attribute'] ? (int) $product['id_product_attribute'] : null), 2, null, false, true, $product['cart_quantity'], false, (int) $order->id_customer, (int) $order->id_cart, (int) $order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}, $specific_price, true, true, null, true, $product['id_customization']);

		                $product_price = Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, Context::getContext()->getComputingPrecision()) : $price_wt;

		                $product_var_tpl = [
		                    'id_product' => $product['id_product'],
		                    'id_product_attribute' => $product['id_product_attribute'],
		                    'reference' => $product['reference'],
		                    'name' => $product['name'] . (isset($product['attributes']) ? ' - ' . $product['attributes'] : ''),
		                    'price' => Tools::getContextLocale($this->context)->formatPrice($product_price * $product['quantity'], $this->context->currency->iso_code),
		                    'quantity' => $product['quantity'],
		                    'customization' => [],
		                ];

		                if (isset($product['price']) && $product['price']) {
		                    $product_var_tpl['unit_price'] = Tools::getContextLocale($this->context)->formatPrice($product_price, $this->context->currency->iso_code);
		                    $product_var_tpl['unit_price_full'] = Tools::getContextLocale($this->context)->formatPrice($product_price, $this->context->currency->iso_code)
		                        . ' ' . $product['unity'];
		                } else {
		                    $product_var_tpl['unit_price'] = $product_var_tpl['unit_price_full'] = '';
		                }

		                $customized_datas = Product::getAllCustomizedDatas((int) $order->id_cart, null, true, null, (int) $product['id_customization']);
		                if (isset($customized_datas[$product['id_product']][$product['id_product_attribute']])) {
		                    $product_var_tpl['customization'] = [];
		                    foreach ($customized_datas[$product['id_product']][$product['id_product_attribute']][$order->id_address_delivery] as $customization) {
		                        $customization_text = '';
		                        if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD])) {
		                            foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text) {
		                                $customization_text .= '<strong>' . $text['name'] . '</strong>: ' . $text['value'] . '<br />';
		                            }
		                        }

		                        if (isset($customization['datas'][Product::CUSTOMIZE_FILE])) {
		                            $customization_text .= $this->trans('%d image(s)', [count($customization['datas'][Product::CUSTOMIZE_FILE])], 'Admin.Payment.Notification') . '<br />';
		                        }

		                        $customization_quantity = (int) $customization['quantity'];

		                        $product_var_tpl['customization'][] = [
		                            'customization_text' => $customization_text,
		                            'customization_quantity' => $customization_quantity,
		                            'quantity' => Tools::getContextLocale($this->context)->formatPrice($customization_quantity * $product_price, $this->context->currency->iso_code),
		                        ];
		                    }
		                }

		                $product_var_tpl_list[] = $product_var_tpl;
		                // Check if is not a virtual product for the displaying of shipping
		                if (!$product['is_virtual']) {
		                    $virtual_product &= false;
		                }
		            }

					$product_list_txt = '';
			        $product_list_html = '';
			        if (count($product_var_tpl_list) > 0) {
			            $product_list_txt = $this->getEmailTemplateContent('order_conf_product_list.txt', Mail::TYPE_TEXT, $product_var_tpl_list);
			            $product_list_html = $this->getEmailTemplateContent('order_conf_product_list.tpl', Mail::TYPE_HTML, $product_var_tpl_list);
			        }

			        $total_reduction_value_ti = 0;
		            $total_reduction_value_tex = 0;

		            $cart_rules_list = $this->createOrderCartRules(
		                $order,
		                $this->context->cart,
		                $order_list,
		                $total_reduction_value_ti,
		                $total_reduction_value_tex,
		                $id_order_state
		            );

		            $cart_rules_list_txt = '';
		            $cart_rules_list_html = '';
		            if (count($cart_rules_list) > 0) {
		                $cart_rules_list_txt = $this->getEmailTemplateContent('order_conf_cart_rules.txt', Mail::TYPE_TEXT, $cart_rules_list);
		                $cart_rules_list_html = $this->getEmailTemplateContent('order_conf_cart_rules.tpl', Mail::TYPE_HTML, $cart_rules_list);
		            }

					// Specify order id for message
					$old_message = Message::getMessageByCartId((int) $this->context->cart->id);
					if ($old_message) {
						$update_message = new Message((int) $old_message['id_message']);
						$update_message->id_order = (int) $order->id;
						$update_message->update();

						// Add this message in the customer thread
						$customer_thread = new CustomerThread();
						$customer_thread->id_contact = 0;
						$customer_thread->id_customer = (int) $order->id_customer;
						$customer_thread->id_shop = (int) $this->context->shop->id;
						$customer_thread->id_order = (int) $order->id;
						$customer_thread->id_lang = (int) $this->context->language->id;
						$customer_thread->email = $this->context->customer->email;
						$customer_thread->status = 'open';
						$customer_thread->token = Tools::passwdGen(12);
						$customer_thread->add();

						$customer_message = new CustomerMessage();
						$customer_message->id_customer_thread = $customer_thread->id;
						$customer_message->id_employee = 0;
						$customer_message->message = htmlentities($update_message->message, ENT_COMPAT, 'UTF-8');
						$customer_message->private = 0;

						if (!$customer_message->add())
							$this->errors[] = Tools::displayError('An error occurred while saving message');
					}

					// Hook validate order
					Hook::exec('actionValidateOrder', array(
						'cart' => $this->context->cart,
						'order' => $order,
						'customer' => $this->context->customer,
						'currency' => $this->context->currency,
						'orderStatus' => $order_status
					));

					foreach ($this->context->cart->getProducts() as $product)
						if ($order_status->logable)
							ProductSale::addProductSale((int) $product['id_product'], (int) $product['cart_quantity']);

					if (Configuration::get('PS_STOCK_MANAGEMENT') && $order_detail->getStockState()) {
						$history = new OrderHistory();
						$history->id_order = (int) $order->id;
						$history->changeIdOrderState(Configuration::get('PS_OS_OUTOFSTOCK'), $order, true);
						$history->addWithemail();
					}

					// Set order state in order history ONLY even if the "out of stock" status has not been yet reached
					// So you migth have two order states
					$new_history = new OrderHistory();
					$new_history->id_order = (int) $order->id;
					$new_history->changeIdOrderState((int) $id_order_state, $order, true);
					$new_history->addWithemail(true, $extra_vars);

					unset($order_detail);

					// Order is reloaded because the status just changed
					$order = new Order($order->id);

					// Send an e-mail to customer (one order = one email)
					if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && $this->context->customer->id) {
						$invoice = new Address($order->id_address_invoice);
						$delivery = new Address($order->id_address_delivery);
						$delivery_state = $delivery->id_state ? new State($delivery->id_state) : false;
						$invoice_state = $invoice->id_state ? new State($invoice->id_state) : false;

						$data = array(
							'{firstname}' => $this->context->customer->firstname,
							'{lastname}' => $this->context->customer->lastname,
							'{email}' => $this->context->customer->email,
							'{delivery_block_txt}' => $this->_getFormatedAddress($delivery, "\n"),
							'{invoice_block_txt}' => $this->_getFormatedAddress($invoice, "\n"),
							'{delivery_block_html}' => $this->_getFormatedAddress($delivery, '<br />', array(
								'firstname'    => '<span style="font-weight:bold;">%s</span>',
								'lastname'    => '<span style="font-weight:bold;">%s</span>'
							)),
							'{invoice_block_html}' => $this->_getFormatedAddress($invoice, '<br />', array(
								'firstname'    => '<span style="font-weight:bold;">%s</span>',
								'lastname'    => '<span style="font-weight:bold;">%s</span>'
							)),
							'{delivery_company}' => $delivery->company,
							'{delivery_firstname}' => $delivery->firstname,
							'{delivery_lastname}' => $delivery->lastname,
							'{delivery_address1}' => $delivery->address1,
							'{delivery_address2}' => $delivery->address2,
							'{delivery_city}' => $delivery->city,
							'{delivery_postal_code}' => $delivery->postcode,
							'{delivery_country}' => $delivery->country,
							'{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
							'{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
							'{delivery_other}' => $delivery->other,
							'{invoice_company}' => $invoice->company,
							'{invoice_vat_number}' => $invoice->vat_number,
							'{invoice_firstname}' => $invoice->firstname,
							'{invoice_lastname}' => $invoice->lastname,
							'{invoice_address2}' => $invoice->address2,
							'{invoice_address1}' => $invoice->address1,
							'{invoice_city}' => $invoice->city,
							'{invoice_postal_code}' => $invoice->postcode,
							'{invoice_country}' => $invoice->country,
							'{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
							'{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
							'{invoice_other}' => $invoice->other,
							'{order_name}' => $order->getUniqReference(),
							'{date}' => (version_compare(_PS_VERSION_, '8.0', '>=')) ? Tools::displayDate(date('Y-m-d H:i:s'), true) : Tools::displayDate(date('Y-m-d H:i:s'), (int) $order->id_lang, true),
							'{carrier}' => $virtual_product ? Tools::displayError('No carrier') : $carrier->name,
							'{payment}' => $order->payment,
							'{products}' => $product_list_html,
							'{products_txt}' => $product_list_txt,
							'{discounts}' => $cart_rules_list_html,
							'{discounts_txt}' => $cart_rules_list_txt,
							'{total_paid}' => Tools::displayPrice($order->total_paid, $this->context->currency, false),
							'{total_tax_paid}' => Tools::displayPrice(($order->total_paid_tax_incl - $order->total_paid_tax_excl), $this->context->currency, false),
							'{total_products}' => Tools::displayPrice($order->total_paid - $order->total_shipping - $order->total_wrapping + $order->total_discounts, $this->context->currency, false),
							'{total_discounts}' => Tools::displayPrice($order->total_discounts, $this->context->currency, false),
							'{total_shipping}' => Tools::displayPrice($order->total_shipping, $this->context->currency, false),
							'{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $this->context->currency, false),
							'{recycled_packaging_label}' => $order->recyclable ? $this->trans('Yes', [], 'Shop.Theme.Global') : $this->trans('No', [], 'Shop.Theme.Global'),
						);

						if (is_array($extra_vars))
							$data = array_merge($data, $extra_vars);

						// Join PDF invoice
						if ((int) Configuration::get('PS_INVOICE') && $order_status->invoice && $order->invoice_number) {
							$pdf = new PDF($order->getInvoicesCollection(), PDF::TEMPLATE_INVOICE, $this->context->smarty);
							$file_attachement['content'] = $pdf->render(false);
							$file_attachement['name'] = Configuration::get('PS_INVOICE_PREFIX', (int) $order->id_lang) . sprintf('%06d', $order->invoice_number) . '.pdf';
							$file_attachement['mime'] = 'application/pdf';
						} else
							$file_attachement = null;

						if (Validate::isEmail($this->context->customer->email))
							Mail::Send(
								(int) $order->id_lang,
								'order_conf',
								Mail::l('Order confirmation', (int) $order->id_lang),
								$data,
								$this->context->customer->email,
								$this->context->customer->firstname . ' ' . $this->context->customer->lastname,
								null,
								null,
								$file_attachement,
								null,
								_PS_MAIL_DIR_,
								false,
								(int) $order->id_shop
							);
					}

					// updates stock in shops
					if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
						$product_list = $order->getProducts();
						foreach ($product_list as $product) {
							// if the available quantities depends on the physical stock
							if (StockAvailable::dependsOnStock($product['product_id'])) {
								// synchronizes
								StockAvailable::synchronize($product['product_id'], $order->id_shop);
							}
						}
					}
				} else {
					$error = Tools::displayError('Order creation failed');
					Logger::addLog($error, 4, '0000002', 'Cart', intval($order->id_cart));
					die($error);
				}
			} // End foreach $order_detail_list
			// Use the last order as currentOrder
			$this->currentOrder = (int) $order->id;
			return true;
		} else {
			$error = Tools::displayError('Cart cannot be loaded or an order has already been placed using this cart');
			Logger::addLog($error, 4, '0000001', 'Cart', intval($this->context->cart->id));
			die($error);
		}
	}

	public function formatProductAndVoucherForEmail($content)
	{
		Tools::displayAsDeprecated();
		return $content;
	}
}
