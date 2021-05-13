<?php 
class ControllerQuickCheckoutConfirm extends Controller { 
	public function index() {
		$redirect = '';
		
		if ($this->cart->hasShipping()) {
			// Validate if shipping address has been set.
			if (!isset($this->session->data['shipping_address'])) {
				$redirect = $this->url->link('checkout/checkout', '', true);
			}

			// Validate if shipping method has been set.
			if (!isset($this->session->data['shipping_method'])) {
				$redirect = $this->url->link('checkout/checkout', '', true);
			}
		} else {
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}
		
		// Validate if payment address has been set.
		if (!isset($this->session->data['payment_address'])) {
			$redirect = $this->url->link('checkout/checkout', '', true);
		}

		// Validate if payment method has been set.
		if (!isset($this->session->data['payment_method'])) {
			$redirect = $this->url->link('checkout/checkout', '', true);
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$redirect = $this->url->link('checkout/cart');
		}
		
		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$redirect = $this->url->link('checkout/cart');

				break;
			}
		}
		
		if (!$redirect) {
			$order_data = array();

			$order_data['totals'] = array();
			$total = 0;
			$taxes = $this->cart->getTaxes();
		
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			// Part - To calculate total amount of reward points to be earned
			$total2 = 0;
			$taxes2 = $this->cart->getTaxes();
		
			$total_data2 = array(
				'totals' => &$totals2,
				'taxes'  => &$taxes2,
				'total'  => &$total2
			);
			
			// order totals code to exclude
			$to_exclude = array('shipping');

			$this->load->model('extension/extension');

			$sort_order = array();

			$results = $this->model_extension_extension->getExtensions('total');

			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get($result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);

					$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
				}

				// To accumulate total amount of reward points to be earned
				if ($this->config->get($result['code'] . '_status') && !in_array($result['code'], $to_exclude)) {
					$this->load->model('extension/total/' . $result['code']);

					$this->{'model_extension_total_' . $result['code']}->getTotal($total_data2);
				}
			}

			$order_data['totals'] = $totals;

			$sort_order = array();

			foreach ($order_data['totals'] as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $order_data['totals']);

			$data = $this->load->language('checkout/checkout');
			$data = array_merge($data, $this->load->language('quickcheckout/checkout'));

			$data['totals'] = array();

			foreach ($order_data['totals'] as $total) {
                            
                                if($total['code'] == "shipping" || $total['code'] == "total"){
                                    if($this->session->data['delivery_date'] != "" && $this->session->data['delivery_time'] != ""){
                                        $language_id = $this->config->get('config_language_id');

                                        if($this->session->data['shipping_method']['code'] == "formula_based.formula_based_0"){
                                            $modulename  = 'self_pickup';
                                        }else{
                                            $modulename  = 'delivery_times';
                                        }

                                        $default_setting = $this->modulehelper->get_field ( $this, $modulename, $language_id, 'default_slot');

                                        $delivery_time = array();
                                        $i = 0;

                                        if(gettype($default_setting) == "array"){
                                            foreach($default_setting as $setting){
                                                foreach($setting as $key1 => $value1){
                                                    if($key1 == "delivery_times" && $value1 == $this->session->data['delivery_time']){
                                                        $total['value'] += (int)$setting['surcharge'];
                                                    }
                                                }
                                            }
                                        }
                                        
                                        $special_setting = $this->modulehelper->get_field ( $this, $modulename, $language_id, 'special_setting');
                                        if(gettype($special_setting) == "array"){
                                            foreach($special_setting as $arr => $setting){
                                                if($setting['product_group'] == ""){
                                                    if($setting["type"] == 2 && $setting['add_time'] == $this->session->data['delivery_time'] && $setting['delivery_date'] == $this->session->data['delivery_date']){
                                                        $total['value'] += (int)$setting['surcharge2'];
                                                    }
                                                    if($setting["type"] == 3 && $setting['delivery_time'] == $this->session->data['delivery_time'] && $setting['delivery_date'] == $this->session->data['delivery_date']){
                                                        $total['value'] += (int)$setting['surcharge2'];
                                                    }
                                                }else{
                                                    $group_array = array();
                                                    $modulename  = 'product_group';
                                                    $product_groups = $this->modulehelper->get_field ( $this, $modulename, $language_id, 'set_group');
                                                    if(gettype($product_groups) == "array"){
                                                        foreach($product_groups as $group){
                                                            if($setting['product_group'] == $group['group']){
                                                                $group_array[] = $group['type'];
                                                            }
                                                        }
                                                    }
                                                    foreach($this->cart->getProducts() as $product){
                                                        $block = 0;
                                                        if(!in_array($product['product_id'], $group_array)){
                                                            $block = 1;
                                                        }
                                                    }
                                                    if($block == 0){
                                                        if($setting["type"] == 2 && $setting['add_time'] == $this->session->data['delivery_time'] && $setting['delivery_date'] == $this->session->data['delivery_date']){
                                                            $total['value'] += (int)$setting['surcharge2'];
                                                        }
                                                        if($setting["type"] == 3 && $setting['delivery_time'] == $this->session->data['delivery_time'] && $setting['delivery_date'] == $this->session->data['delivery_date']){
                                                            $total['value'] += (int)$setting['surcharge2'];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        if($total['code'] == "total"){
                                            $final_total = $total['value'];
                                        }
                                    }
                                }
				$text = $this->currency->format($total['value'], $this->session->data['currency']);
				
				$data['totals'][] = array(
					'title' => $total['title'],
					'text'  => $text,
				);
			}

			$order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
			$order_data['store_id'] = $this->config->get('config_store_id');
			$order_data['store_name'] = $this->config->get('config_name');

			if ($order_data['store_id']) {
				$order_data['store_url'] = $this->config->get('config_url');
			} else {
				$order_data['store_url'] = HTTP_SERVER;
			}

			if ($this->customer->isLogged()) {
				$this->load->model('account/customer');

				$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

				$order_data['customer_id'] = $this->customer->getId();
				$order_data['customer_group_id'] = $customer_info['customer_group_id'];
				$order_data['firstname'] = $customer_info['firstname'];
				$order_data['lastname'] = $customer_info['lastname'];
				$order_data['email'] = $customer_info['email'];
				$order_data['telephone'] = isset($this->session->data['telephone']) ? $this->session->data['telephone'] : $customer_info['telephone'];
				$order_data['fax'] = $customer_info['fax'];
				$order_data['custom_field'] = json_decode($customer_info['custom_field']);
			} elseif (isset($this->session->data['guest'])) {
				$order_data['customer_id'] = 0;
				$order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
				$order_data['firstname'] = $this->session->data['guest']['firstname'];
				$order_data['lastname'] = $this->session->data['guest']['lastname'];
				$order_data['email'] = $this->session->data['guest']['email'];
				$order_data['telephone'] = $this->session->data['guest']['telephone'];
				$order_data['fax'] = $this->session->data['guest']['fax'];
				$order_data['custom_field'] = $this->session->data['guest']['custom_field'];
			}

			$order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
			$order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
			$order_data['payment_company'] = $this->session->data['payment_address']['company'];
			$order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
			$order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
			$order_data['payment_unit_no'] = $this->session->data['payment_address']['unit_no'];
			$order_data['payment_city'] = $this->session->data['payment_address']['city'];
			$order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
			$order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
			$order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
			$order_data['payment_country'] = $this->session->data['payment_address']['country'];
			$order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
			$order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
			$order_data['payment_custom_field'] = $this->session->data['payment_address']['custom_field'];

			if (isset($this->session->data['payment_method']['title'])) {
				$order_data['payment_method'] = $this->session->data['payment_method']['title'];
			} else {
				$order_data['payment_method'] = '';
			}

			if (isset($this->session->data['payment_method']['code'])) {
				$order_data['payment_code'] = $this->session->data['payment_method']['code'];
			} else {
				$order_data['payment_code'] = '';
			}

			if ($this->cart->hasShipping()) {
				$order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
				$order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
				$order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
				if(isset($this->session->data['shipping_address']['telephone'])) {
				    $order_data['shipping_telephone'] = $this->session->data['shipping_address']['telephone'];
				} else {
				    $order_data['shipping_telephone'] = '';
				}
				$order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
				$order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
				$order_data['shipping_unit_no'] = $this->session->data['shipping_address']['unit_no'];
				$order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
				$order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
				$order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
				$order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
				$order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
				$order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
				$order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
				$order_data['shipping_custom_field'] = $this->session->data['shipping_address']['custom_field'];

				if (isset($this->session->data['shipping_method']['title'])) {
					$order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
				} else {
					$order_data['shipping_method'] = '';
				}

				if (isset($this->session->data['shipping_method']['code'])) {
					$order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
				} else {
					$order_data['shipping_code'] = '';
				}
			} else {
				$order_data['shipping_firstname'] = '';
				$order_data['shipping_lastname'] = '';
				$order_data['shipping_company'] = '';
				$order_data['shipping_telephone'] = '';
				$order_data['shipping_address_1'] = '';
				$order_data['shipping_address_2'] = '';
				$order_data['shipping_unit_no'] = '';
				$order_data['shipping_city'] = '';
				$order_data['shipping_postcode'] = '';
				$order_data['shipping_zone'] = '';
				$order_data['shipping_zone_id'] = '';
				$order_data['shipping_country'] = '';
				$order_data['shipping_country_id'] = '';
				$order_data['shipping_address_format'] = '';
				$order_data['shipping_custom_field'] = array();
				$order_data['shipping_method'] = '';
				$order_data['shipping_code'] = '';
			}

			$order_data['products'] = array();

			foreach ($this->cart->getProducts() as $product) {
				$option_data = array();

				foreach ($product['option'] as $option) {
					$option_data[] = array(
						'product_option_id'       => $option['product_option_id'],
						'product_option_value_id' => $option['product_option_value_id'],
						'option_id'               => $option['option_id'],
						'option_value_id'         => $option['option_value_id'],
						'name'                    => $option['name'],
						'value'                   => $option['value'],
						'type'                    => $option['type']
					);
				}

				$order_data['products'][] = array(
					'product_id' => $product['product_id'],
					'name'       => $product['name'],
					'model'      => $product['model'],
					'sku'      	 => $product['sku'],
					'option'     => $option_data,
					'download'   => $product['download'],
					'quantity'   => $product['quantity'],
					'subtract'   => $product['subtract'],
					'price'      => $product['price'],
					'total'      => $product['total'],
					'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
					'reward'     => $product['reward']
				);
			}

			// Gift Voucher
			$order_data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $voucher) {
					$order_data['vouchers'][] = array(
						'description'      => $voucher['description'],
						'code'             => substr(md5(mt_rand()), 0, 10),
						'to_name'          => $voucher['to_name'],
						'to_email'         => $voucher['to_email'],
						'from_name'        => $voucher['from_name'],
						'from_email'       => $voucher['from_email'],
						'voucher_theme_id' => $voucher['voucher_theme_id'],
						'message'          => $voucher['message'],
						'amount'           => $voucher['amount'],
						'delivery_date'    => $voucher['delivery_date'],
						'headerline'    => $voucher['headerline'],
					);
				}
			}
			
			if (!isset($this->session->data['order_comment'])) { 
				$this->session->data['order_comment'] = '';
			}
			
			if (!isset($this->session->data['survey'])) { 
				$this->session->data['survey'] = '';
			}
			
			if (!isset($this->session->data['delivery_date'])) {
				$this->session->data['delivery_date'] = '';
			}
			
			if (!isset($this->session->data['delivery_time'])) {
				$this->session->data['delivery_time'] = '';
			}
			
			$this->session->data['comment'] = '';
			
			if ($this->session->data['order_comment'] != '') {
				$this->session->data['comment'] .= $this->language->get('text_order_comments') . ' ' . $this->session->data['order_comment'];
			}
			
			if ($this->session->data['survey'] != '') {
				$this->session->data['comment'] .= "\n\n" . $this->language->get('text_survey') . ' ' . $this->session->data['survey'];
			}
			
			$order_data['delivery_date'] = '';
			$order_data['delivery_time'] = '';

			if ($this->session->data['delivery_date'] != '') {
				$this->session->data['comment'] .= "\n\n" . "Confirmed Date: " . $this->session->data['delivery_date'];
				$order_data['delivery_date'] = $this->session->data['delivery_date'];
				
				if ($this->session->data['delivery_time'] != '') {
					$this->session->data['comment'] .= ' ' . $this->session->data['delivery_time'];
					$order_data['delivery_time'] = $this->session->data['delivery_time'];
				}
			}

			$order_data['comment'] = $this->session->data['comment'];
			$order_data['total'] = $final_total;

			if (isset($this->request->cookie['tracking'])) {
				$order_data['tracking'] = $this->request->cookie['tracking'];

				$subtotal = $this->cart->getSubTotal();

				// Affiliate
				$this->load->model('affiliate/affiliate');

				$affiliate_info = $this->model_affiliate_affiliate->getAffiliateByCode($this->request->cookie['tracking']);

				if ($affiliate_info) {
					$order_data['affiliate_id'] = $affiliate_info['affiliate_id'];
					$order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
				} else {
					$order_data['affiliate_id'] = 0;
					$order_data['commission'] = 0;
				}

				// Marketing
				$this->load->model('checkout/marketing');

				$marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

				if ($marketing_info) {
					$order_data['marketing_id'] = $marketing_info['marketing_id'];
				} else {
					$order_data['marketing_id'] = 0;
				}
			} else {
				$order_data['affiliate_id'] = 0;
				$order_data['commission'] = 0;
				$order_data['marketing_id'] = 0;
				$order_data['tracking'] = '';
			}

			$order_data['language_id'] = $this->config->get('config_language_id');
			
			$order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
			$order_data['currency_code'] = $this->session->data['currency'];
			$order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
			
			$order_data['ip'] = $this->request->server['REMOTE_ADDR'];

			if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
			} else {
				$order_data['forwarded_ip'] = '';
			}

			if (isset($this->request->server['HTTP_USER_AGENT'])) {
				$order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
			} else {
				$order_data['user_agent'] = '';
			}

			if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
				$order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
			} else {
				$order_data['accept_language'] = '';
			}

			$order_data['reward_earn'] = 0;
			
			// No use/spend reward point then earn it.
			if( $this->config->get('reward_status') && 
			(!isset($this->session->data['reward']) || (int)$this->session->data['reward'] < 1) 
			){
				$customer_group_id = $this->customer->getGroupId();

				$this->load->model('extension/total/reward');

				$reward_info = $this->model_extension_total_reward->getRewardInfoByCustomerGroup($customer_group_id);

				if($reward_info){
					$reward_point_earn_rate = $reward_info['reward_point_earn_rate']/100;
					$subtotal = $this->cart->getSubTotal();

					//$order_data['reward_earn'] = $subtotal * $reward_point_earn_rate;
					
					// to count on other discounts, coupon, gift card when calculating reward points to earn
					$order_data['reward_earn'] = $total2 * $reward_point_earn_rate;
				}
			}

			$this->load->model('checkout/order');

			$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

			$data['text_recurring_item'] = $this->language->get('text_recurring_item');
			$data['text_payment_recurring'] = $this->language->get('text_payment_recurring');

			$data['column_name'] = $this->language->get('column_name');
			$data['column_model'] = $this->language->get('column_model');
			$data['column_quantity'] = $this->language->get('column_quantity');
			$data['column_price'] = $this->language->get('column_price');
			$data['column_total'] = $this->language->get('column_total');

			$this->load->model('tool/upload');

			$data['products'] = array();

			foreach ($this->cart->getProducts() as $product) {
				$option_data = array();

				foreach ($product['option'] as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$recurring = '';

				if ($product['recurring']) {
					$frequencies = array(
						'day'        => $this->language->get('text_day'),
						'week'       => $this->language->get('text_week'),
						'semi_month' => $this->language->get('text_semi_month'),
						'month'      => $this->language->get('text_month'),
						'year'       => $this->language->get('text_year'),
					);

					if ($product['recurring']['trial']) {
						$recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
					}

					if ($product['recurring']['duration']) {
						$recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					} else {
						$recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					}
				}
				
				$price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$total = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']);

				$data['products'][] = array(
					'key'        => isset($product['key']) ? $product['key'] : $product['cart_id'],
					'cart_id'	 => isset($product['cart_id']) ? $product['cart_id'] : $product['key'],
					'product_id' => $product['product_id'],
					'name'       => $product['name'],
					'model'      => $product['model'],
					'option'     => $option_data,
					'recurring'  => $recurring,
					'quantity'   => $product['quantity'],
					'subtract'   => $product['subtract'],
					'price'      => $price,
					'total'      => $total,
					'href'       => $this->url->link('product/product', 'product_id=' . $product['product_id']),
				);
			}

			// Gift Voucher
			$data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $voucher) {
					$data['vouchers'][] = array(
						'description' => $voucher['description'],
						'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency'])
					);
				}
			}

			$data['payment'] = $this->load->controller('extension/payment/' . $this->session->data['payment_method']['code']);
		} else {
			$data['redirect'] = $redirect;
		}

		// All variables
		$data['confirmation_page'] = $this->config->get('quickcheckout_confirmation_page');
		$data['auto_submit'] = $this->config->get('quickcheckout_auto_submit');
		$data['button_back'] = $this->language->get('button_back');
		$data['payment_target'] = html($this->config->get('quickcheckout_payment_target'));
		$data['back'] = $this->url->link('quickcheckout/checkout', '', true);
		
		$this->load->model('catalog/information');
		$information_info = $this->model_catalog_information->getInformation(5);
        $data['tnc_title'] = $information_info['title'];
        $data['tnc_description'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');
        
		$this->response->setOutput($this->load->view('quickcheckout/confirm', $data));
  	}
}