<?php
/*
  Plugin Name: «Экспресс Платежи» для WooCommerce
  Plugin URI: https://express-pay.by/cms-extensions/wordpress
  Description: «Экспресс Платежи» - плагин для интеграции с сервисом «Экспресс Платежи» (express-pay.by) через API. Плагин позволяет выставить счет в системе ЕРИП, получить и обработать уведомление о платеже в системе ЕРИП, выставлять счета для оплаты банковскими картами, получать и обрабатывать уведомления о платеже по банковской карте. Описание плагина доступно по адресу: <a target="blank" href="https://express-pay.by/cms-extensions/wordpress">https://express-pay.by/cms-extensions/wordpress</a>
  Version: 3.0.2
  Author: ООО «ТриИнком»
  Author URI: https://express-pay.by/
  WC requires at least: 2.6
  WC tested up to: 3.7
 */

if(!defined('ABSPATH')) exit;

define("ERIP_EXPRESSPAY_VERSION", "3.0.2");

add_action('plugins_loaded', 'init_gateway', 0);

function add_wordpress_erip_expresspay($methods) {
	$methods[] = 'wordpress_erip_expresspay';

	return $methods;
}

function init_gateway() {
	if(!class_exists('WC_Payment_Gateway'))
		return;

	add_filter('woocommerce_payment_gateways', 'add_wordpress_erip_expresspay');

	load_plugin_textdomain("wordpress_erip_expresspay", false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');

	class Wordpress_Erip_Expresspay extends WC_Payment_Gateway {
		private $plugin_dir;

		public function __construct() {
			$this->id = "expresspay_erip";
            $this->method_title = __('Экспресс Платежи: ЕРИП');
            $this->method_description = __('Прием платежей в системе ЕРИП сервис «Экспресс Платежи»');
			$this->plugin_dir = plugin_dir_url(__FILE__);

			$this->init_form_fields();
			$this->init_settings();

			$this->title = __("ЕРИП", 'wordpress_erip_expresspay');
			$this->path_to_erip = $this->get_option('path_to_erip');
			$this->service_id = $this->get_option('service_id');
			$this->secret_word = $this->get_option('secret_key');
			$this->secret_key_notify = $this->get_option('secret_key_notify');
			$this->token = $this->get_option('token');
			$this->url = ( $this->get_option('test_mode') != 'yes' ) ? $this->get_option('url_api') : $this->get_option('url_sandbox_api');
			$this->url .= "/v1/web_invoices";
			$this->name_editable = ( $this->get_option('name_editable') == 'yes' ) ? 1 : 0;
			$this->address_editable = ( $this->get_option('address_editable') == 'yes' ) ? 1 : 0;
			$this->amount_editable = ( $this->get_option('amount_editable') == 'yes' ) ? 1 : 0;
			$this->test_mode = ( $this->get_option('test_mode') == 'yes' ) ? 1 : 0;
			$this->send_client_email = ( $this->get_option('send_client_email') == 'yes' ) ? 1 : 0;
			$this->is_use_signature_notify = ( $this->get_option('is_use_signature_notify') == 'yes' ) ? 1 : 0;
			$this->url_qr_code = ( $this->get_option('test_mode') != 'yes' ) ? $this->get_option('url_api') : $this->get_option('url_sandbox_api');
			$this->url_qr_code .= '/v1/qrcode/getqrcode/';
			$this->show_qr_code = ( $this->get_option('show_qr_code') == 'yes' ) ? 1 : 0;

			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_wordpress_erip_expresspay', array($this, 'check_ipn_response'));
		}

		public function admin_options() {
			?>
			<h3><?php _e('«Экспресс Платежи: ЕРИП»', 'wordpress_erip_expresspay'); ?></h3>
            <div style="float: left; display: inline-block;">
                 <a target="_blank" href="https://express-pay.by"><img src="<?php echo $this->plugin_dir; ?>assets/images/erip_expresspay_big.png" width="270" height="91" alt="exspress-pay.by" title="express-pay.by"></a>
            </div>
            <div style="margin-left: 6px; margin-top: 15px; display: inline-block;">
				<?php _e('«Экспресс Платежи: ЕРИП» - плагин для интеграции с сервисом «Экспресс Платежи» (express-pay.by) через API. 
				<br/>Плагин позволяет выставить счет в системе ЕРИП, получить и обработать уведомление о платеже в системе ЕРИП.
				<br/>Описание плагина доступно по адресу: ', 'wordpress_erip_expresspay'); ?><a target="blank" href="https://express-pay.by/cms-extensions/wordpress#woocommerce_2_x">https://express-pay.by/cms-extensions/wordpress#woocommerce_2_x</a>
            </div>

			<table class="form-table">
				<?php		
					$this->generate_settings_html();
				?>
			</table>
			<div class="copyright" style="text-align: center;">
				<?php _e("© Все права защищены | ООО «ТриИнком»,", 'wordpress_erip_expresspay'); ?> 2013-<?php echo date("Y"); ?><br/>
				<?php echo __('Версия', 'wordpress_erip_expresspay') . " " . ERIP_EXPRESSPAY_VERSION ?>			
			</div>
			<?php
		}

		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __('Включить/Выключить', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'default' => 'no'
				),
				'token' => array(
					'title'   => __('Токен', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Генерирутся в панели express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'service_id' => array(
					'title'   => __('Номер услуги', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Номер услуги в express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'handler_url' => array(
					'title'   => __('Адрес для уведомлений', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'css' => 'display: none;',
					'description' => get_site_url() . '/?wc-api=wordpress_erip_expresspay&action=notify'
				),
				'secret_key' => array(
					'title'   => __('Секретное слово для подписи счетов', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Секретного слово, которое известно только серверу и клиенту. Используется для формирования цифровой подписи. Задается в панели express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'is_use_signature_notify' => array(
					'title'   => __('Использовать цифровую подпись для уведомлений', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Использовать цифровую подпись для уведомлений', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'secret_key_norify' => array(
					'title'   => __('Секретное слово для подписи уведомлений', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'description' => __('Секретного слово, которое известно только серверу и клиенту. Используется для формирования цифровой подписи. Задается в панели express-pay.by', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'show_qr_code' => array(
					'title'   => __('Показывать QR код для оплаты', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Показывать QR код для оплаты', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'name_editable' => array(
					'title'   => __('Разрешено изменять ФИО плательщика', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Разрешается при оплате счета изменять ФИО плательщика', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'address_editable' => array(
					'title'   => __('Разрешено изменять адрес плательщика', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Разрешается при оплате счета изменять адрес плательщика', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'amount_editable' => array(
					'title'   => __('Разрешено изменять сумму оплаты', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Разрешается при оплате счета изменять сумму платежа', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'send_client_email' => array(
					'title'   => __('Отправлять email-уведомление клиенту', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox'
				),
				'test_mode' => array(
					'title'   => __('Использовать тестовый режим', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox'
				),
				'url_api' => array(
					'title'   => __('Адрес API', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'default' => 'https://api.express-pay.by'
				),
				'url_sandbox_api' => array(
					'title'   => __('Адрес тестового API', 'wordpress_erip_expresspay'),
					'type'    => 'text',
					'default' => 'https://sandbox-api.express-pay.by'
				),
				'path_to_erip' => array(
					'title'   => __('Путь к ЕРИП', 'wordpress_erip_expresspay'),
					'description' => __('Путь по ветке ЕРИП который записан в личном кабинете express-pay.by', 'wordpress_erip_expresspay'),
					'type'    => 'textarea',
					'default' => __('Интернет-магазины\Сервисы -> "Первая буква доменного имени интернет-магазина" -> "Доменное имя интернет-магазина"', 'wordpress_erip_expresspay'),
					'css'	  => 'min-height: 160px;'
				)
			);
		}

		function process_payment($order_id) {
			$order = new WC_Order($order_id);	

			return array(
				'result' => 'success',
				'redirect'	=> add_query_arg('order-pay', $order->get_order_number( ), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id('pay'))))
			);
		}

		function receipt_page($order_id) {
			$this->log_info('receipt_page', 'Initialization request for add invoice');
			$order = new WC_Order($order_id);

			$price = preg_replace('#[^\d.]#', '', $order->get_total());
			$price = str_replace('.', ',', $price);
            $client_email = "";
            //$price = floatval($price);
            
            if($this->send_client_email){
                $client_email = $order->get_billing_email();
            }

			$currency = (date('y') > 16 || (date('y') <= 16 && date('n') >= 7)) ? '933' : '974';

	        $request_params = array(
				"ServiceId" => $this->service_id ,
	            "AccountNo" => $order_id,
	            "Amount" => $price,
	            "Currency" => $currency,
	            "Surname" => $order->get_billing_last_name(),
	            "FirstName" => $order->get_billing_first_name(),
	            "City" => $order->get_billing_city(),
	            "IsNameEditable" => $this->name_editable,
	            "IsAddressEditable" => $this->address_editable,
	            "IsAmountEditable" => $this->amount_editable,
				"EmailNotification" => $client_email,
				"ReturnType" => "json"
			);

        	$request_params['Signature'] = $this->compute_signature($request_params, $this->secret_word, 'add_invoice');

    		$request_params = http_build_query($request_params);

    		$this->log_info('receipt_page', 'Send POST request; ORDER ID - ' . $order_id . '; URL - ' . $this->url . '; REQUEST - ' . $request_params);

	        $response = "";

	        try {
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, $this->url);
		        curl_setopt($ch, CURLOPT_POST, 1);
		        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_params);
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		        $response = curl_exec($ch);
		        curl_close($ch);
	    	} catch (Exception $e) {
				$this->log_error_exception('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response, $e);

	    		$this->fail($order_id);
	    	}

	    	$this->log_info('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response);

			try {
	        	$response = json_decode($response);
	    	} catch (Exception $e) {
	    		$this->log_error_exception('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response, $e);

	    		$this->fail($order_id);
	    	}

	        if(isset($response->ExpressPayInvoiceNo))
	        	$this->success($order_id, $response->ExpressPayInvoiceNo);
	        else
	        	$this->fail($order_id);
		}

		private function success($order_id, $invoiceId) {
			global $woocommerce;

			$order = new WC_Order($order_id);

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('success', 'Initialization render success page; ORDER ID - ' . $order->get_order_number());

			$woocommerce->cart->empty_cart();

			$order->update_status('pending', __('Счет успешно выставлен и ожидает оплаты', 'wordpress_erip_expresspay'));

			$message_success = '<h3>Счет добавлен в систему ЕРИП для оплаты </h3><h4>Ваш номер заказа: ##order_id##</h4><br/>Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).<br/>1. Для этого в перечне услуг ЕРИП перейдите в раздел:<br/><b>##erip_path##</b><br/>2. Далее введите номер заказа <b>##order_id##</b> и нажмите "Продолжить"<br/>3. Проверить корректность информации<br/>4. Совершить платеж.';

			$message_success = str_replace("##order_id##", $order->get_order_number(), nl2br($message_success, true));
			$message_success = str_replace("##erip_path##", $this->path_to_erip, nl2br($message_success, true));
		

			if($this->show_qr_code){
				$request_params = array(
					'Token' => $this->token,
					'InvoiceId' => $invoiceId,
					'ViewType' => 'base64'
				);

				$request_params["Signature"] =  $this->compute_signature($request_params, $this->secret_word, 'get_qr_code');
				
				$request_params = http_build_query($request_params);

				$this->log_info('success', 'Send POST request; INVOICE ID - ' . $invoiceId . '; URL - ' . $this->url_qr_code . '; REQUEST - ' . $request_params);

				$response = "";
				
				$url = $this->url_qr_code . '?' . $request_params;

				try {
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					$response = curl_exec($ch);
					curl_close($ch);
				} catch (Exception $e) {
					$this->log_error_exception('success', 'Get response; INVOICE ID - ' . $invoiceId . '; RESPONSE - ' . $response, $e);
					return;
				}

				$this->log_info('success', 'Get response; INVOICE ID - ' . $invoiceId . '; RESPONSE - ' . $response);

				try {
					$response = json_decode($response);
				} catch (Exception $e) {
					$this->log_error_exception('receipt_page', 'Get response; ORDER ID - ' . $order_id . '; RESPONSE - ' . $response, $e);
					return;
				}

				if(isset($response->QrCodeBody))
				{
					$qr_code = $response->QrCodeBody;
					$message_success = "<h3>Счет добавлен в систему ЕРИП для оплаты </h3><h4>Ваш номер заказа: ##order_id##</h4><table style=\"width: 100%;text-align: left;\"><tbody><tr><td valign=\"top\" style=\"text-align:left;\">Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).<br/>1. Для этого в перечне услуг ЕРИП перейдите в раздел:<br/><b>##erip_path##</b><br/>2. Далее введите номер заказа <b>##order_id##</b> и нажмите \"Продолжить\"<br/>3. Проверить корректность информации<br/>4. Совершить платеж.</td><td style=\"text-align: center;padding: 40px 20px 0 0;vertical-align: middle\"><br/>##qr_code##<br/><p><b>Отсканируйте QR-код для оплаты</b></p></td></tr></tbody></table>";
					$message_success = str_replace("##order_id##", $order->get_order_number(), nl2br($message_success, true));
					$message_success = str_replace("##erip_path##", $this->path_to_erip, nl2br($message_success, true));
					$message_success = str_replace("##qr_code##", '<img src="data:image/jpeg;base64,' . $qr_code . '" />', nl2br($message_success, true));
					$message_success = trim($message_success);
					echo $message_success;
				}

			} else echo $message_success;
			

			echo '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . get_permalink( wc_get_page_id( "shop" ) ) . '">' . __('Продолжить', 'wordpress_erip_expresspay') . '</a></p>';

			$signature_success = $signature_cancel = "";

			if($this->is_use_signature_notify) {
				$signature_success = $this->compute_signature_from_json('{"CmdType": 1, "AccountNo": ' . $order->get_order_number() . '}', $this->secret_key_notify);
				$signature_cancel = $this->compute_signature_from_json('{"CmdType": 2, "AccountNo": ' . $order->get_order_number() . '}', $this->secret_key_notify);
			}

			if($this->test_mode) : ?>
				<div class="test_mode">
			        <?php _e('Тестовый режим:', 'wordpress_erip_expresspay'); ?> <br/>
    				<input type="button" style="margin: 6px 0;" class="button" id="send_notify_success" value="<?php _e('Отправить уведомление об успешной оплате', 'wordpress_erip_expresspay'); ?>" />
			        <input type="button" class="button" style="margin: 6px 0;" id="send_notify_cancel" class="btn btn-primary" value="<?php _e('Отправить уведомление об отмене оплаты', 'wordpress_erip_expresspay'); ?>" />

				      <script type="text/javascript">
				        jQuery(document).ready(function() {
				          jQuery('#send_notify_success').click(function() {
				            send_notify(1, '<?php echo $signature_success; ?>');
				          });

				          jQuery('#send_notify_cancel').click(function() {
				            send_notify(2, '<?php echo $signature_cancel; ?>');
				          });

				          function send_notify(type, signature) {
				            jQuery.post('<?php echo get_site_url() . "/?wc-api=wordpress_erip_expresspay&action=notify" ?>', 'Data={"CmdType": ' + type + ', "AccountNo": <?php echo $order->get_order_number(); ?>}&Signature=' + signature, function(data) {alert(data);})
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

		private function fail($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('fail', 'Initialization render fail page; ORDER ID - ' . $order->get_order_number());

			$order->update_status('failed', __('Счет не был выставлен', 'wordpress_erip_expresspay'));

			echo '<h2>' . __('Ошибка выставления счета в системе ЕРИП', 'wordpress_erip_expresspay') . '</h2>';
			echo __("При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина", 'wordpress_erip_expresspay');

			echo '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . wc_get_checkout_url() . '">' . __('Попробовать заново', 'wordpress_erip_expresspay') . '</a></p>';

			$this->log_info('fail', 'End render fail page; ORDER ID - ' . $order->get_order_number());

			die();
		}

		function check_ipn_response() {
			$this->log_info('check_ipn_response', 'Get notify from server; REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action']) && $_REQUEST['action'] == 'notify') {
				$data = ( isset($_REQUEST['Data']) ) ? htmlspecialchars_decode($_REQUEST['Data']) : '';
				$data = stripcslashes($data);
				$signature = ( isset($_REQUEST['Signature']) ) ? $_REQUEST['Signature'] : '';

			    if($this->is_use_signature_notify) {
			    	if($signature == $this->compute_signature_from_json($data, $this->secret_key_notify))
				        $this->notify_success($data);
				    else  
				    	$this->notify_fail($data, $signature, $this->compute_signature_from_json($data, $this->secret_key_notify), $this->secret_key_notify);
			    } else 
			    	$this->notify_success($data);
			}

			$this->log_info('check_ipn_response', 'End (Get notify from server); REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			die();
		}

		private function notify_success($dataJSON) {
			global $woocommerce;

			try {
	        	$data = json_decode($dataJSON);
	    	} catch(Exception $e) {
				$this->log_error('notify_success', "Fail to parse the server response; RESPONSE - " . $dataJSON);

	    		$this->notify_fail($dataJSON);
	    	}

            $order = new WC_Order($data->AccountNo);

	        if(isset($data->CmdType)) {
	        	switch ($data->CmdType) {
	        		case '1':
	                    $order->update_status('processing', __('Счет успешно оплачен', 'wordpress_erip_expresspay'));
	                    $this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет успешно оплачен; RESPONSE - ' . $dataJSON);

	        			break;
	        		case '2':
						$order->update_status('cancelled', __('Платеж отменён', 'wordpress_erip_expresspay'));
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Платеж отменён; RESPONSE - '. $dataJSON);

	        			break;
                    case '3':
                        if($data->Status === '2'){
                        
                            $order->update_status('cancelled', __('Счет просрочен', 'wordpress_erip_expresspay'));
                            $this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет просрочен; RESPONSE - '. $dataJSON);
                            
						}
						elseif($data->Status === '5'){
                        
                            $order->update_status('cancelled', __('Счет отменен', 'wordpress_erip_expresspay'));
                            $this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет отменен; RESPONSE - '. $dataJSON);
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

		private function notify_fail($dataJSON, $signature='', $computeSignature='', $secret_key='') {
			$this->log_error('notify_fail', "Fail to update status; RESPONSE - " . $dataJSON . ', signature - ' . $signature . ', Compute signature - '. $computeSignature . ', secret key - ' . $secret_key);
			
			header("HTTP/1.0 400 Bad Request");
			echo 'FAILED | Incorrect digital signature';
		}

		private function compute_signature_from_json($json, $secret_word) {
		    $hash = NULL;
		    $secret_word = trim($secret_word);
		    
		    if(empty($secret_word))
				$hash = strtoupper(hash_hmac('sha1', $json, ""));
		    else
		        $hash = strtoupper(hash_hmac('sha1', $json, $secret_word));

		    return $hash;
		}	

	    private function compute_signature($request_params, $secret_word, $method = 'add_invoice') {
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
									"failurl"),
				'get_qr_code' => array(
									"invoiceid",
									"viewtype",
									"imagewidth",
									"imageheight")
	        );

	        $result = $this->token;

	        foreach ($api_method[$method] as $item)
	            $result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';

			$this->log_info('compute_signature', 'RESULT - ' . $result);
			
	        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

	        return $hash;
		}

	    private function log_error_exception($name, $message, $e) {
	    	$this->log($name, "ERROR" , $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
	    }

	    private function log_error($name, $message) {
	    	$this->log($name, "ERROR" , $message);
	    }

	    private function log_info($name, $message) {
	    	$this->log($name, "INFO" , $message);
	    }

		private function log($name, $type, $message) 
		{
			$log_url = wp_upload_dir();
			$log_url = $log_url['basedir'] . "/erip_expresspay";

			if(!file_exists($log_url)) 
			{
				$is_created = mkdir($log_url, 0777);

				if(!$is_created)
					return;
			}

			$log_url .= '/express-pay-' . date('Y.m.d') . '.log';

			file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - " .date("Y-m-d H:i:s"). "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
		
		}
	}
}
?>