<?php
/*
  Plugin Name: Express Payments: ERIP
  Plugin URI: https://express-pay.by/cms-extensions/wordpress
  Description: Express Payments: ERIP - is a plugin for integration with the «Express Payments» (express-pay.by) via API. The plugin allows you to issue an invoice in the ERIP system, receive and process a payment notification in the ERIP system, issue invoices for payment by bank cards, receive and process notifications of payment by a bank card. The plugin description is available at: <a target="blank" href="https://express-pay.by/cms-extensions/wordpress">https://express-pay.by/cms-extensions/wordpress</a>
  Version: 1.0.4
  Author: LLC «TriInkom»
  Author URI: https://express-pay.by/
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
  WC requires at least: 4.0
  WC tested up to: 5.6
  Text Domain: wordpress_erip_expresspay
  Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define("ERIP_EXPRESSPAY_VERSION", "1.0.4");

add_action('plugins_loaded', 'erip_expresspay_gateway', 0);

function add_wordpress_erip_expresspay($methods)
{
	$methods[] = 'wordpress_erip_expresspay';

	return $methods;
}

function erip_expresspay_gateway()
{
	if (!class_exists('WC_Payment_Gateway') or class_exists('Wordpress_Erip_Expresspay'))
		return;

	add_filter('woocommerce_payment_gateways', 'add_wordpress_erip_expresspay');

	load_plugin_textdomain("wordpress_erip_expresspay", false, basename(dirname(__FILE__)) . '/languages');

	class Wordpress_Erip_Expresspay extends WC_Payment_Gateway
	{
		private $plugin_dir;

		public function __construct()
		{
			$this->id = "expresspay_erip";
			$this->method_title = __('Express Payments: ERIP', 'wordpress_erip_expresspay');
			$this->method_description = __('Acceptance of payments in the ERIP system, service «Express Payments»', 'wordpress_erip_expresspay');
			$this->plugin_dir = plugin_dir_url(__FILE__);

			$this->erip_expresspay_form_fields();

			$this->title = $this->get_option('payment_method_title');
			$this->description = $this->get_option('payment_method_description');

			$this->path_to_erip = $this->get_option('path_to_erip');
			$this->service_id = $this->get_option('service_id');
			$this->secret_word = $this->get_option('secret_key');
			$this->secret_key_notify = $this->get_option('secret_key_notify');
			$this->token = $this->get_option('token');
			$this->url = ($this->get_option('test_mode') != 'yes') ? $this->get_option('url_api') : $this->get_option('url_sandbox_api');
			$this->url .= "/v1/web_invoices";
			$this->name_editable = ($this->get_option('name_editable') == 'yes') ? 1 : 0;
			$this->address_editable = ($this->get_option('address_editable') == 'yes') ? 1 : 0;
			$this->amount_editable = ($this->get_option('amount_editable') == 'yes') ? 1 : 0;
			$this->test_mode = ($this->get_option('test_mode') == 'yes') ? 1 : 0;
			$this->send_client_email = ($this->get_option('send_client_email') == 'yes') ? 1 : 0;
			$this->is_use_signature_notify = ($this->get_option('is_use_signature_notify') == 'yes') ? 1 : 0;
			$this->url_qr_code = ($this->get_option('test_mode') != 'yes') ? $this->get_option('url_api') : $this->get_option('url_sandbox_api');
			$this->url_qr_code .= '/v1/qrcode/getqrcode/';
			$this->show_qr_code = ($this->get_option('show_qr_code') == 'yes') ? 1 : 0;

			$this->status_after_payment = $this->get_option('status_after_payment');
			$this->status_after_cancellation = $this->get_option('status_after_cancellation');
			$this->status_after_placing = $this->get_option('status_after_placing');

			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_wordpress_erip_expresspay', array($this, 'check_ipn_response'));
		}

		public function admin_options()
		{
?>
			<h3><?php echo __('Express Payments: ERIP', 'wordpress_erip_expresspay'); ?></h3>
			<div style="display: inline-block;">
				<a target="_blank" href="https://express-pay.by"><img src="<?php echo $this->plugin_dir; ?>assets/images/erip_expresspay_big.png" alt="exspress-pay.by" title="express-pay.by"></a>
			</div>
			<div style="margin-left: 6px; display: inline-block;">
				<?php echo __('Express Payments: ERIP - is a plugin for integration with the «Express Payments» (express-pay.by) via API. 
				<br/>The plugin allows you to issue an invoice for a card payment, receive and process a payment notification. 
				<br/>The plugin description is available at:', 'wordpress_erip_expresspay'); ?><a target="blank" href="https://express-pay.by/cms-extensions/wordpress#woocommerce_4_x">https://express-pay.by/cms-extensions/wordpress#woocommerce_4_x</a>
			</div>

			<table class="form-table">
				<?php
				$this->generate_settings_html();
				?>
			</table>
			<div class="copyright" style="text-align: center;">
				<?php echo __('© All rights reserved | LLC «TriInkom»', 'wordpress_erip_expresspay'); ?> 2013-<?php echo date("Y"); ?><br />
				<?php echo __('Version', 'wordpress_erip_expresspay') . " " . ERIP_EXPRESSPAY_VERSION ?>
			</div>
			<?php
		}

		function erip_expresspay_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __('Enable/Disable', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'default' => 'no'
				),
				'token' => array(
					'title'   => __('Token', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Generated in the panel express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'service_id' => array(
					'title'   => __('Service number', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Service number in express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'handler_url' => array(
					'title'   => __('Address for notifications', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'css' => 'display: none;',
					'description' => get_site_url() . '/?wc-api=wordpress_erip_expresspay&action=notify'
				),
				'secret_key' => array(
					'title'   => __('Secret word for signing invoices', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('A secret word that is known only to the server and the client. Used to generate a digital signature. Set in the panel express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'is_use_signature_notify' => array(
					'title'   => __('Digitally sign notifications', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Use digitally sign notifications', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'secret_key_notify' => array(
					'title'   => __('Secret word for signing notifications', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('A secret word that is known only to the server and the client. Used to generate a digital signature. Set in the panel express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'show_qr_code' => array(
					'title'   => __('Show QR code for payment', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Show QR code for payment', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'name_editable' => array(
					'title'   => __('It is allowed to change the name of the payer', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('It is allowed to change the name of the payer when paying the invoice', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'address_editable' => array(
					'title'   => __("Allowed to change the payer's address", 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __("It is allowed to change the payer's address when paying an invoice", 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'amount_editable' => array(
					'title'   => __('Allowed to change the amount of payment', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('It is allowed to change the payment amount when paying an invoice', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'send_client_email' => array(
					'title'   => __('Send email notification to client', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox'
				),
				'test_mode' => array(
					'title'   => __('Use test mode', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox'
				),
				'url_api' => array(
					'title'   => __('API address', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'default' => 'https://api.express-pay.by'
				),
				'url_sandbox_api' => array(
					'title'   => __('Test API address', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'default' => 'https://sandbox-api.express-pay.by'
				),
				'path_to_erip' => array(
					'title'   => __('The path to ERIP', 'wordpress_erip_expresspay'),
					'description' => __('The path along the ERIP branch which is recorded in the personal account express-pay.by', 'wordpress_erip_expresspay'),
					'type'    => 'textarea',
					'default' => __('Online stores \ Services -> "The first letter of the domain name of the online store" -> "The domain name of the online store"', 'wordpress_erip_expresspay'),
					'css'	  => 'min-height: 160px;'
				),
				'status_after_placing' => array(
					'title'       => __('Status after invoicing', 'wordpress_erip_expresspay'),
					'type'        => 'select',
					'description' => __('The status that the order will have after invoicing', 'wordpress_erip_expresspay'),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'status_after_payment' => array(
					'title'       => __('Status after payment', 'wordpress_erip_expresspay'),
					'type'        => 'select',
					'description' => __('The status that the order will have after payment of the invoice', 'wordpress_erip_expresspay'),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'status_after_cancellation' => array(
					'title'       => __('Status after cancellation', 'wordpress_erip_expresspay'),
					'type'        => 'select',
					'description' => __('The status that the order will have after the invoice is canceled', 'wordpress_erip_expresspay'),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'payment_method_title' => array(
					'title'   => __('Payment method name', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('The name that will be displayed in the cart when choosing a payment method', 'wordpress_erip_expresspay'),
					'default' 	=> __("Express Payments: ERIP", 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'payment_method_description' => array(
					'title'   => __('Description of the payment method', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Description that will be displayed in the payment method settings', 'wordpress_erip_expresspay'),
					'default' 	=> __("Issuing an invoice through the Express Payments service", 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
			);
		}

		function process_payment($order_id)
		{
			$order = new WC_Order($order_id);

			return array(
				'result' => 'success',
				'redirect'	=> add_query_arg('order-pay', $order->get_order_number(), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id('pay'))))
			);
		}

		function receipt_page($order_id)
		{
			$this->log_info('receipt_page', 'Initialization request for add invoice');
			$order = new WC_Order($order_id);

			$price = preg_replace('#[^\d.]#', '', $order->get_total());
			$price = str_replace('.', ',', $price);
			$client_email = "";

			$client_phone = preg_replace('/[^0-9]/', '', $order->get_billing_phone());
			$client_phone = substr($client_phone, -9);
			$client_phone = "375$client_phone";

			if ($this->send_client_email) {
				$client_email = $order->get_billing_email();
			}

			$currency = (date('y') > 16 || (date('y') <= 16 && date('n') >= 7)) ? '933' : '974';

			$request_params = array(
				"ServiceId" => $this->service_id,
				"AccountNo" => $order_id,
				"Amount" => $price,
				"Currency" => $currency,
				"Surname" => mb_strimwidth($order->get_billing_last_name(), 0, 30),
				"FirstName" => mb_strimwidth($order->get_billing_first_name(), 0, 30),
				"City" => mb_strimwidth($order->get_billing_city(), 0, 30),
				"IsNameEditable" => $this->name_editable,
				"IsAddressEditable" => $this->address_editable,
				"IsAmountEditable" => $this->amount_editable,
				"EmailNotification" => $client_email,
				"SmsPhone" => $client_phone,
				"ReturnType" => "json",
				"ReturnUrl" => get_site_url(),
				"FailUrl" => get_site_url(),
				"ReturnInvoiceUrl" => 0
			);

			$request_params['Signature'] = $this->compute_signature($request_params, $this->secret_word, 'add_invoice');

			$args = array(
				'body'        => $request_params,
				'timeout'     => '5',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(),
				'cookies'     => array(),
			);

			$response = wp_remote_post($this->url, $args);

			$this->log_info('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response['body']);

			try {
				$response = json_decode($response['body']);
			} catch (Exception $e) {
				$this->log_error_exception('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response, $e);

				$this->fail($order_id, $e);
			}

			if (isset($response->ExpressPayInvoiceNo))
				$this->success($order_id, $response->ExpressPayInvoiceNo);
			else
				$this->fail($order_id, $response->Errors);
		}

		private function success($order_id, $invoiceId)
		{
			global $woocommerce;

			$order = new WC_Order($order_id);

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('success', 'Initialization render success page; ORDER ID - ' . $order->get_order_number());

			$woocommerce->cart->empty_cart();

			$order->update_status($this->status_after_placing, __('Invoice successfully issued and awaiting payment', 'wordpress_erip_expresspay'));

			$message_success = __('<h3>Account added to the ERIP system for payment</h3>
			<h4>Your order number: ##order_id##</h4>
			You need to make a payment in any system that allows you to pay through ERIP (items banking services, ATMs,
			payment terminals, Internet banking systems, client banking, etc.).
			<br/> 1. To do this, in the list of ERIP services go to the section:<br/><b>##erip_path##</b>
			<br/> 2. Next, enter the order number <b>##order_id##</b> and click "Continue"
			<br/> 3. Check if the information is correct. 
			<br/> 4. Make a payment.</td> ', 'wordpress_erip_expresspay');

			$message_success = str_replace("##order_id##", $order->get_order_number(), $message_success);
			$message_success = str_replace("##erip_path##", $this->path_to_erip, $message_success);


			if ($this->show_qr_code) {
				$request_params = array(
					'Token' => $this->token,
					'InvoiceId' => $invoiceId,
					'ViewType' => 'base64'
				);

				$request_params["Signature"] =  $this->compute_signature($request_params, $this->secret_word, 'get_qr_code');

				$request_params = http_build_query($request_params);

				$response = wp_remote_get($this->url_qr_code . '?' . $request_params);

				try {
					$response = json_decode($response['body']);
				} catch (Exception $e) {
					$this->log_error_exception('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response['body'], $e);
					return;
				}

				if (isset($response->QrCodeBody)) {
					$qr_code = $response->QrCodeBody;

					$message_success = $message_success . "<td style=\"text-align: center;padding: 40px 20px 0 0;vertical-align: middle\">
					<br/>##OR_CODE##<br/><p><b>##OR_CODE_DESCRIPTION##</b></p></td></tr></tbody></table>";

					$message_success = str_replace('##OR_CODE##', '<img src="data:image/jpeg;base64,' . $qr_code . '"  width="200" height="200"/>',  $message_success);
					$message_success = str_replace('##OR_CODE_DESCRIPTION##', __('Scan the QR code to pay', 'wordpress_erip_expresspay'),  $message_success);
					echo $message_success;
				}
			} else echo $message_success;


			echo '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . get_permalink(wc_get_page_id("shop")) . '">' . __('Proceed', 'wordpress_erip_expresspay') . '</a></p>';

			$signature_success = $signature_cancel = "";

			if ($this->is_use_signature_notify) {
				$signature_success = $this->compute_signature_from_json('{"CmdType": 1, "AccountNo": ' . $order->get_order_number() . '}', $this->secret_key_notify);
				$signature_cancel = $this->compute_signature_from_json('{"CmdType": 2, "AccountNo": ' . $order->get_order_number() . '}', $this->secret_key_notify);
			}

			if ($this->test_mode) : ?>
				<div class="test_mode">
					<?php echo __('Test mode:', 'wordpress_erip_expresspay'); ?> <br />
					<input type="button" style="margin: 6px 0;" class="button" id="send_notify_success" value="<?php echo __('Send notification of successful payment', 'wordpress_erip_expresspay'); ?>" />
					<input type="button" class="button" style="margin: 6px 0;" id="send_notify_cancel" class="btn btn-primary" value="<?php echo __('Send payment cancellation notification', 'wordpress_erip_expresspay'); ?>" />

					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery('#send_notify_success').click(function() {
								send_notify(1, '<?php echo $signature_success; ?>');
							});

							jQuery('#send_notify_cancel').click(function() {
								send_notify(2, '<?php echo $signature_cancel; ?>');
							});

							function send_notify(type, signature) {
								jQuery.post('<?php echo get_site_url() . "/?wc-api=wordpress_erip_expresspay&action=notify" ?>', 'Data={"CmdType": ' + type + ', "AccountNo": <?php echo $order->get_order_number(); ?>}&Signature=' + signature, function(data) {
										alert(data);
									})
									.fail(function(data) {
										alert(data.responseText);
									});
							}
						});
					</script>

				</div>
<?php
			endif;

			$this->log_info('success', 'End render success page; ORDER ID - ' . $order->get_order_number());
		}

		private function fail($order_id, $errors)
		{
			global $woocommerce;

			$order = new WC_Order($order_id);

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('fail', 'Initialization render fail page; ORDER ID - ' . $order->get_order_number());

			$order->update_status($this->status_after_cancellation, $errors[0]);

			echo '<h2>' . __('Error billing in the ERIP system', 'wordpress_erip_expresspay') . '</h2>';
			echo __("An unexpected error occurred while executing the request. Please try again later or contact the store's technical support", 'wordpress_erip_expresspay');

			echo '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . wc_get_checkout_url() . '">' . __('Try again', 'wordpress_erip_expresspay') . '</a></p>';

			$this->log_info('fail', 'End render fail page; ORDER ID - ' . $order->get_order_number());

			die();
		}

		function check_ipn_response()
		{
			$this->log_info('check_ipn_response', 'Get notify from server; REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			if (sanitize_text_field($_SERVER['REQUEST_METHOD']) === 'POST' && isset($_REQUEST['action']) && $_REQUEST['action'] == 'notify') {
				$data = isset($_POST['Data']) ? sanitize_text_field($_POST['Data']) : '';
				$data = stripcslashes($data);
				$signature = isset($_POST['Signature']) ? sanitize_text_field($_POST['Signature']) : '';

				if ($this->is_use_signature_notify) {
					if ($signature == $this->compute_signature_from_json($data, $this->secret_key_notify))
						$this->notify_success($data);
					else
						$this->notify_fail($data, $signature, $this->compute_signature_from_json($data, $this->secret_key_notify), $this->secret_key_notify);
				} else
					$this->notify_success($data);
			}

			$this->log_info('check_ipn_response', 'End (Get notify from server); REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			die();
		}

		private function notify_success($dataJSON)
		{
			global $woocommerce;

			try {
				$data = json_decode($dataJSON);
			} catch (Exception $e) {
				$this->log_error('notify_success', "Fail to parse the server response; RESPONSE - " . $dataJSON);
			}

			try {
				$order = new WC_Order($data->AccountNo);
			} catch (Exception $e) {
				$this->log_error('notify_success', "Fail find to order!");
				die();
			}

			if (isset($data->CmdType)) {
				switch ($data->CmdType) {
					case '1':
						$order->update_status($this->status_after_payment, __('The bill is paid', 'wordpress_erip_expresspay'));
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет оплачен; RESPONSE - ' . $dataJSON);
						break;
					case '2':
						$order->update_status($this->status_after_cancellation, __('Payment canceled', 'wordpress_erip_expresspay'));
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Платеж отменён; RESPONSE - ' . $dataJSON);

						break;
					case '3':
						if ($data->Status == '1') {
							$order->update_status($this->status_after_placing, __('Invoice awaiting payment', 'wordpress_erip_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет ожидает оплаты; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '2') {
							$order->update_status($this->status_after_cancellation, __('Invoice expired', 'wordpress_erip_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет просрочен; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '3') {
							$order->update_status($this->status_after_payment, __('The bill is paid', 'wordpress_erip_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет оплачен; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '5') {
							$order->update_status($this->status_after_cancellation, __('Invoice canceled', 'wordpress_erip_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет отменен; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '6') {
							$order->update_status($this->status_after_payment, __('Invoice paid by card', 'wordpress_erip_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет оплачен картой; RESPONSE - ' . $dataJSON);
						}
						break;
					default:
						$this->notify_fail($dataJSON);
						die();
				}

				header("HTTP/1.0 200 OK");
				echo 'SUCCESS';
			} else
				$this->notify_fail($dataJSON);
		}

		private function notify_fail($dataJSON, $signature = '', $computeSignature = '', $secret_key = '')
		{
			$this->log_error('notify_fail', "Fail to update status; RESPONSE - " . $dataJSON . ', signature - ' . $signature . ', Compute signature - ' . $computeSignature . ', secret key - ' . $secret_key);

			header("HTTP/1.0 400 Bad Request");
			echo 'FAILED | Incorrect digital signature';
		}

		private function compute_signature_from_json($json, $secret_word)
		{
			$hash = NULL;
			$secret_word = trim($secret_word);

			if (empty($secret_word))
				$hash = strtoupper(hash_hmac('sha1', $json, ""));
			else
				$hash = strtoupper(hash_hmac('sha1', $json, $secret_word));

			return $hash;
		}

		private function compute_signature($request_params, $secret_word, $method = 'add_invoice')
		{
			$secret_word = trim($secret_word);
			$normalized_params = array_change_key_case($request_params, CASE_LOWER);
			$api_method = array(
				'add_invoice' => array(
					"serviceid",
					"accountno",
					"amount",
					"currency",
					"expiration",
					"info",
					"surname",
					"firstname",
					"patronymic",
					"city",
					"street",
					"house",
					"building",
					"apartment",
					"isnameeditable",
					"isaddresseditable",
					"isamounteditable",
					"emailnotification",
					"smsphone",
					"returntype",
					"returnurl",
					"failurl",
					"returninvoiceurl"
				),
				'get_qr_code' => array(
					"invoiceid",
					"viewtype",
					"imagewidth",
					"imageheight"
				)
			);

			$result = $this->token;

			foreach ($api_method[$method] as $item)
				$result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

			$this->log_info('compute_signature', 'RESULT - ' . $result);

			$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

			return $hash;
		}

		private function log_error_exception($name, $message, $e)
		{
			$this->log($name, "ERROR", $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
		}

		private function log_error($name, $message)
		{
			$this->log($name, "ERROR", $message);
		}

		private function log_info($name, $message)
		{
			$this->log($name, "INFO", $message);
		}

		private function log($name, $type, $message)
		{
			$log_url = wp_upload_dir();
			$log_url = $log_url['basedir'] . "/erip_expresspay";

			if (!file_exists($log_url)) {
				$is_created = mkdir($log_url, 0777);

				if (!$is_created)
					return;
			}

			$log_url .= '/express-pay-' . date('Y.m.d') . '.log';

			file_put_contents($log_url, $type . " - IP - " . sanitize_text_field($_SERVER['REMOTE_ADDR']) . "; DATETIME - " . date("Y-m-d H:i:s") . "; USER AGENT - " . sanitize_text_field($_SERVER['HTTP_USER_AGENT']) . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
		}
	}
}
?>