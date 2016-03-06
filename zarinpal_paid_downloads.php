<?php
/*
Plugin Name: Zarinpal Paid Download(zarinGate)
Plugin URI: https://zarinpal.com
Description: دانلود به ازای پرداخت زرین پال
Version: 1
Author: Masoud Amini
Author URI: http://masoudamini.ir/
License: GPL2
*/

ZarinpalFileDownload::init();

class ZarinpalFileDownload {
	protected static $currencies = array(
		'USD' => array('United States Dollar','$'),
		'AUD' => array('Australian Dollar','AUD$'),
		'BRL' => array('Brazilian Real','R$'),
		'GBP' => array('British Pound','&pound;'),
		'CAD' => array('Canadian Dollar','CAD$'),
		'CNY' => array('Chinese Yuan','&#20803;'),
		'DKK' => array('Danish Krone','kr.'),
		'EUR' => array('European Euro','&#8364;'),
		'HKD' => array('Hong Kong Dollar','HK$'),
		'HUF' => array('Hungarian Forint','Ft'),
		'INR' => array('Indian Rupee','INR'),
		'IDR' => array('Indonesian Rupiah','Rp'),
		'JPY' => array('Japanese Yen','&yen;'),
		'MXN' => array('Mexican Peso','MEX$'),
		'NZD' => array('New Zealand Dollar','NZ$'),
		'NOK' => array('Norwegian Kroner','kr'),
		'PLN' => array('Polish Zloty','zl.'),
		'RUB' => array('Russian Ruble','RUB'),
		'SAR' => array('Saudi Riyal','SR'),
		'SGD' => array('Singapore Dollar','SGD$'),
		'ZAR' => array('South African Rand','R'),
		'SEK' => array('Swedish Krona','kr'),
		'CHF' => array('Swiss Franc','CHF'),
		'THB' => array('Thai Bhat','&#3647;'),
		'TRY' => array('Turkish Lira','TRY'),
		'TWD' => array('Taiwan Dollar','TWD')
	);

	const VERSION = '1.3';
	const DB_VERSION = "1.0";

	public static function init() {
		register_activation_hook(__FILE__, array(__CLASS__, 'install'));

		// admin stuff
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('admin_init', array(__CLASS__, 'admin_init'));

		// media buttons hook
		add_action('media_buttons_context', array(__CLASS__, 'media_button'));

		// insert form
		add_action('admin_footer', array(__CLASS__, 'add_pfd_form'));

		// listener for ipn activation
		add_action('template_redirect', array(__CLASS__, 'var_listener'));
		add_filter('query_vars', array(__CLASS__, 'register_vars'));

		add_action('admin_menu', array(__CLASS__, 'add_meta_box'));
	}

	protected static function transactioncode($length = "") {
		$code = md5(uniqid(rand(), true));
		if ($length != "") return strtoupper(substr($code, 0, $length));
		else return strtoupper($code);
	}

	protected static function relative_time($ptime) {
		$etime = time() - $ptime;

		if ($etime < 1) {
			return '0 seconds';
		}

		$a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
					30 * 24 * 60 * 60       =>  'month',
					24 * 60 * 60            =>  'day',
					60 * 60                 =>  'hour',
					60                      =>  'minute',
					1                       =>  'second'
					);

		foreach ($a as $secs => $str) {
			$d = $etime / $secs;
			if ($d >= 1) {
				$r = round($d);
				return $r . ' ' . $str . ($r > 1 ? 's' : '');
			}
		}
	}

	public static function add_meta_box() {
		add_meta_box( 'pfd_sectionid', "Zarinpal File Download", array(__CLASS__, 'meta_box'), 'post', 'side', 'high' );
		add_meta_box( 'pfd_sectionid', "Zarinpal File Download", array(__CLASS__, 'meta_box'), 'page', 'side', 'high' );
	}
	
	public static function meta_box() {
//		echo '<div style="margin-top:5px;margin-bottom:5px;">';
		echo '<a href="#TB_inline?width=450&inlineId=paypal_file_download_form" class="thickbox button" title="قرارد دادن لينک پرداخت Zarinpal">قرارد دادن لينک پرداخت Zarinpal</a>';
//		echo '</div>';
	}

	public static function install() {
		global $wpdb;

		$message_default = <<<EOT
بابت خريد محصول [PRODUCT_NAME] تشکر مي کنيم! لينک دانلود در انتهاي  اين پيغام قرار گرفته. براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد.
EOT;

		$message_default_nofile = <<<EOT
بابت خريد محصول [PRODUCT_NAME] تشکر مي کنيم! لينک دانلود در انتهاي  اين پيغام قرار گرفته. براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد.
EOT;

		add_option("email_message", $message_default, '','yes');
		add_option("email_message_nofile", $message_default_nofile, '','yes');
		add_option("expire_links_after", 7, '','yes');
		add_option("paypal_email", "XXXX-XXXXX-XXXX-XXXXX-XXXX", '','yes');
		add_option("paypal_direct", 0, '','yes');
		add_option("paypal_return_url", get_option("siteurl"), '','yes');

		$table_name = $wpdb->prefix . "pfd_products";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				file VARCHAR(255) NOT NULL,
				downloads bigint(11) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		$table_name = $wpdb->prefix . "pfd_orders";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				product_id mediumint(9) NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				fulfilled mediumint(9) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}


		$table_name = $wpdb->prefix . "pfd_transactions";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				product_id mediumint(9) NOT NULL,
				order_id mediumint(9) NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				protection_eligibility VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				tax bigint(11) NULL,
				payment_date VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				first_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				last_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				business VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_street VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_city VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_state VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_zip VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_country_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_country VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				quantity VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				verify_sign VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				txn_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receiver_email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receiver_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
				txn_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				item_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				mc_currency VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				item_number VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				residence_country VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				custom VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receipt_id VARCHAR(255)  NULL,
				transaction_subject VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_fee bigint(11) NOT NULL,
				payment_gross bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		add_option("pfd_db_version", self::DB_VERSION);
	}

	protected static function get_currency() {
		if (get_option('pfd_currency')) {
			$cc = get_option('pfd_currency');
		} else {
			$cc = "USD";
		}

		return $cc;
	}

	protected static function get_currency_symbol() {
		$cc = self::get_currency();

		return self::$currencies[$cc][1];
	}

	public static function validate_currency($currency) {
		if (!empty(self::$currencies[$currency]))
			return $currency;
		return 'USD';
	}

	public static function admin_init() {
		register_setting('pfd_options', 'email_message');
		register_setting('pfd_options', 'email_message_nofile');
		register_setting('pfd_options', 'expire_links_after', 'intval');
		register_setting('pfd_options', 'paypal_email');
		register_setting('pfd_options', 'paypal_direct', 'intval');
		register_setting('pfd_options', 'paypal_return_url');
		register_setting('pfd_options', 'pfd_currency', array(__CLASS__, 'validate_currency'));
	}

	public static function admin_menu() {
		add_menu_page( "Zarinpal File Download", "Zarinpal File Download", 'manage_options', 'zarinpal-file-download', array(__CLASS__, 'admin_dashboard'), "https://ir.zarinpal.com/favicon.ico");

		add_submenu_page( 'zarinpal-file-download', "Zarinpal File Download Products", "محصول", 'manage_options', "zarinpal-file-download-products", array(__CLASS__, 'admin_products_router'));

		add_submenu_page( 'zarinpal-file-download', "Zarinpal File Download Settings", "تنظيمات", 'manage_options', "paypal-file-download-settings", array(__CLASS__, 'admin_settings'));

		add_submenu_page( 'zarinpal-file-download', "Zarinpal File Download Transactions", "تراکنش ها", 'manage_options', "paypal-file-download-transactions", array(__CLASS__,'admin_transactions'));

		global $submenu;
		$submenu['zarinpal-file-download'][0][0] = 'پشتيباني';
	}

	public static function admin_products_router() {
		$action = '';
		if (!empty($_REQUEST['action'])) {
			$action = $_REQUEST['action'];
		}

		switch ($action) {
			case 'edit':
				return self::admin_products_edit();
				break;
			case 'delete':
				return self::admin_products_delete();
				break;
			case 'add':
				return self::admin_products_add();
				break;
			default:
				return self::admin_products();
		}
	}

	protected static function admin_products_edit() {

		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";
		
		if (isset($_POST["product_name"])) {

			$name = $_POST["product_name"];
			$url = $_POST["product_url"];
			$cost = $_POST["product_cost"];

			$wpdb->update( $table_name, array('name' => $name, 'file' => $url, 'cost' => $cost), array('id' => $_GET["id"]), array( '%s', '%s', '%s'));
		}
		
		$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$_GET["id"]) , ARRAY_A, 0);
	?>
	<div class="wrap">
		<h2>ويرايش محصول: <?php echo $product['name'] ?></h2>
		<a href="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=zarinpal-file-download-products">&laquo; بازگشت به صفحه محصولات</a>
		<form action="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=zarinpal-file-download-products&action=edit&id=<?php echo $_GET['id'] ?>" method="post">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">نام محصول</th>
				<td><input type="text" name="product_name" style="width:250px;" value="<?php echo str_replace('"','\"',$product["name"]); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لينک محصول</th>
				<td><input type="text" name="product_url" style="width:400px;" value="<?php echo str_replace('"','\"',$product["file"]); ?>" /><br />(لطفا اطمينان حاصل کنيد که اين لينک مخفي است<br />اين لينک پس از خريد موفق به خريدار نشان داده مي شود )</td>
			</tr>
			<tr valign="top">
				<th scope="row">قيمت محصول(به ازاي هر بار دانلود)</th>
				<td><input type="text" name="product_cost" style="width:50px;" value="<?php echo str_replace('"','\"',$product["cost"]); ?>" />تومان</td>
			</tr>
			<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="ذخيره کن" />
				</td>
			</tr>
		</table>
		</form>
	</div>
	<?php
	}

	protected static function admin_products_delete() {
		// delete and redirect
		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";

		$id = $_GET["id"];

		$wpdb->query("DELETE FROM $table_name WHERE id = '$id'");
		?>
		<script type="text/javascript">
		<!--
		window.location = "<?php echo get_option('siteurl') . '/wp-admin/admin.php?page=zarinpal-file-download-products' ?>"
		//-->
		</script>
		<?php
	}

    public static function admin_dashboard() {


	?>
    <div class="wrap" style="font-size:16px">
    <br /><br /><br />
		هرگونه سوال ، انتقاد و يا پيشنهاد خود را در رابطه با اين پلاگين و يا سامانه پرداخت اينترنتي  ما  را با آدرس زير در ميان بگذاريد
<br />
<div align="left" style="font-size:20px"><br /><br />Email: me@masoudamini.ir</div>
	</div>
	<?php
    }

	protected static function admin_products() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	?>
	<div class="wrap">
		<h2>محصولات</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="5%" class="manage-column" style="">رديف</th>
					<th scope="col" id="name" width="50%" class="manage-column" style="">نام</th>
					<th scope="col" id="cost" class="manage-column" style="">قيمت</th>
					<th scope="col" id="downloads" class="manage-column num" style="">تعداد دانلود</th>
					<th scope="col" id="edit" class="manage-column num" style="">ويرايش</th>
					<th scope="col" id="delete" class="manage-column num" style="">حذف</th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<th scope="col" id="name" width="5%" class="manage-column" style="">رديف</th>
					<th scope="col" id="name" width="50%" class="manage-column" style="">نام</th>
					<th scope="col" id="cost" class="manage-column" style="">قيمت</th>
					<th scope="col" id="downloads" class="manage-column num" style="">تعداد دانلود</th>
					<th scope="col" id="edit" class="manage-column num" style="">ويرايش</th>
					<th scope="col" id="delete" class="manage-column num" style="">حذف</th>
				</tr>
			</tfoot>

			<tbody>
				<?php
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_products";
				$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC" ,ARRAY_A);
				if (count($products) == 0) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class="" colspan="5">هيچ محصولي موجود نيست</td>
				</tr>
				<?php
				} else {
				foreach ($products as $product) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class=""><?php echo $product['id'] ?></td>
					<td class="post-title column-title"><strong><a class="row-title" href="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=zarinpal-file-download-products&action=edit&id=<?php echo $product['id'] ?>"><?php echo $product['name'] ?></a></strong></td>
					<td class=""><?php echo $product['cost'] ?> ريال</td>
					<td class="" style="text-align:center;"><?php echo $product['downloads'] ?></td>
					<td class="" style="text-align:center;"><a href="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=zarinpal-file-download-products&action=edit&id=<?php echo $product['id'] ?>">ويرايش</a></td>
					<td class="" style="text-align:center;"><a href="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=zarinpal-file-download-products&action=delete&id=<?php echo $product['id'] ?>" onclick="if(confirm('آيا از حذف اين مورد اطمينان داريد؟ !')) { return true;} else { return false;}">حذف</a></td>
				</tr>
				<?php } } ?>
			</tbody>
		</table>

		<h2>اضافه نمودن محصول</h2>
		<form action="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=zarinpal-file-download-products&action=add" method="post">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">نام محصول</th>
				<td><input type="text" name="product_name" style="width:250px;" value="" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لينک محصول</th>
				<td><input type="text" name="product_url" style="width:400px;" value="" /><br />(لطفا اطمينان حاصل کنيد که اين لينک مخفي است<br />اين لينک پس از خريد موفق به خريدار نشان داده مي شود )</td>
			</tr>
			<tr valign="top">
				<th scope="row">قيمت محصول(به ازاي هر بار دانلود)</th>
				<td><input type="text" name="product_cost" style="width:50px;" value="" />ريال</td>
			</tr>
			<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="اضافه کن" />
				</td>
			</tr>
		</table>
		</form>
	</div>
	<?php
	}

	protected static function admin_products_add() {
		// get shit
		$name = $_POST["product_name"];
		$url = $_POST["product_url"];
		$cost = $_POST["product_cost"];

		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";

		$wpdb->insert( $table_name, array('name' => $name, 'file' => $url, 'cost' => $cost, 'downloads' => 0, 'created_at' => time()), array( '%s', '%s', '%s', '%d', '%d') );

		?>
		<script type="text/javascript">
		<!--
		window.location = "<?php echo get_option('siteurl') . '/wp-admin/admin.php?page=zarinpal-file-download-products' ?>"
		//-->
		</script>
		<?php
	}

	public static function admin_settings() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	?>
	<div class="wrap">
		<h2>تنظيمات درگاه Zarinpal</h2>
<?php
		if (isset($_GET['settings-updated'])) {
			echo '<div id="message" class="updated"><p>تنظيمات به روز شد!</p></div>';
		}
?>
		<form method="post" action="options.php">
			<?php settings_fields('pfd_options'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">API</th>
					<td><input type="text" name="paypal_email" style="width:300px;" value="<?php echo get_option('paypal_email'); ?>" /><br />مرچنت کد دریافتی از سایت زرین پال</td>
				</tr>
				<tr valign="top">
					<th scope="row">تاريخ انقضاي لينک بعد از...</th>
					<td><input type="text" name="expire_links_after" style="width:50px;" value="<?php echo get_option('expire_links_after'); ?>" /> روز (0 براي بي نهايت)<br />فعال کردن اين قسمت باعث مي شود لينک هاي شما پس از مدت تعيين شده غير فعال شوند</td>
				</tr>
                <tr valign="top">
					<th scope="row">مستقیم کردن لینک</th>
					<td><input type="text" name="paypal_direct" style="width:50px;" value="<?php echo get_option('paypal_direct'); ?>" /> روز (1 به معنای فعال)<br />فعال کردن اين قسمت باعث مي شود لينک هاي شما پس از پرداخت بصورت مستقیم نمایش داده شوند</td>
				</tr>
				<tr valign="top">
					<th scope="row">آدرس بازگشتي</th> 
					<td><input type="text" name="paypal_return_url" style="width:250px;" value="<?php echo get_option('paypal_return_url'); ?>" /><br />لينک بازگشت به سايت شما پس از انجام تراکنش در درگاه زرین پال</td>
				</tr>
				<tr valign="top">
					<th scope="row">اطلاع رساني</th>
					<td><textarea name="email_message" style="width:400px;height:200px;"><?php echo get_option('email_message'); ?></textarea><br />پس از خريد موفق اين متن براي خريدار به نمايش در خواهد آمد<br /><strong>لينک دانلود بصورت اتوماتيک در انتهاي اين متن قرار مي گيرد</strong><br />شما مي توانيد از متغير هاي زير استفاده کنيد: <br />[DOWNLOAD_LINK] [PRODUCT_NAME] [TRANSACTION_ID]<br /></td>
				</tr>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					</td>
				</tr>
			</table>
		</form>
	</div>
	<?php
	}

	public static function admin_transactions() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	?>
	<div class="wrap">
		<h2>تراکنش ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="40%" class="manage-column" style="">شماره تراکنش, محصول</th>
					<th scope="col" id="name" width="20%" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">قيمت</th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<th scope="col" id="name" width="40%" class="manage-column" style="">شماره تراکنش, محصول</th>
					<th scope="col" id="name" width="20%" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">قيمت</th>
				</tr>
			</tfoot>

			<tbody>
				<?php
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_transactions";
				$products_name = $wpdb->prefix . "pfd_products";
				$orders_name = $wpdb->prefix . "pfd_orders";

				$transactions = $wpdb->get_results( "SELECT $table_name.order_code, $products_name.name, $table_name.first_name, $table_name.created_at, $table_name.last_name, $table_name.address_street, $table_name.address_city, $table_name.address_state, $table_name.address_zip, $table_name.address_country, $table_name.payment_fee, $table_name.payer_email, $orders_name.cost FROM $table_name JOIN $products_name ON $table_name.product_id = $products_name.id JOIN $orders_name ON $table_name.order_id = $orders_name.id ORDER BY $table_name.id DESC" ,ARRAY_A);

				if (count($transactions) == 0) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class="" colspan="7">هيج تراکنش وجود ندارد.</td>
				</tr>
				<?php
				} else {
				foreach ($transactions as $transaction) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class="post-title column-title"><?php echo $transaction['order_code'] ?><br /><strong><?php echo $transaction['name'] ?></strong></td>
					<td class=""><?php echo strftime("%a, %B %e, %Y %r", $transaction['created_at']) ?><br />(<?php echo self::relative_time($transaction["created_at"]) ?> ago)</td>
                    <td class=""><?php echo $transaction['payer_email'] ?></td>
					<td class=""><?php echo $transaction['cost'] ?> ريال</td>
				</tr>

				<?php } } ?>
			</tbody>
		</table>
	</div>
	<?php
	}

	public static function media_button($context){
		$image_url = "http://ir.zarinpal.com/favicon.ico";
		$more = '<a href="#TB_inline?width=450&inlineId=paypal_file_download_form" class="thickbox" title="قرارد دادن لينک پرداخت Zarinpal"><img src="' . $image_url . '" alt="قرارد دادن لينک پرداخت Zarinpal" /></a>';
		return $context . $more;
	}

	public static function add_pfd_form() {
	?>
	<script type="text/javascript">
		function insert_pfd_button(){
			product_id = jQuery("#product_selector").val()
			image = jQuery("#button_image_url").val()

			//construct = '<a href="<?php echo get_option('siteurl') ?>/?checkout=' + product_id + '"><img src="' + image + '" /></a>';
            construct = '<form name="frm_zarinpal' + product_id + '" action="<?php echo get_option('siteurl') ?>/?checkout=' + product_id + '" method="post"><input type="image" name="submit" src="' + image + '" value="1"></form>';

			var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}

		function insert_pfd_link(){
			product_id = jQuery("#product_selector").val()

			//construct = '<a href="<?php echo get_option('siteurl') ?>/?checkout=' + product_id + '"><?php echo get_option('siteurl') ?>/?checkout=' + product_id + '</a>';
            construct = '<form name="frm_zarinpal' + product_id + '" action="<?php echo get_option('siteurl') ?>/?checkout=' + product_id + '" method="post"><input type="image" src="" value="' + image + '"></form>';

			var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}
	</script>

	<div id="paypal_file_download_form" style="display:none;">
		<div class="wrap">
			<div>
				<div style="padding:15px 15px 0 15px;">
					<h3 style="font-size:16pt !important;line-height:1em !important;color:#555555 !important; font-family: Georgia, Times New Roman, Times, serif !important;"><span style="font-weight:normal; ">Zarinpal File Download</span><br />قرارد دادن لينک پرداخت Zarinpal</h3>
					<span>لطفا محصول مورد نظرتان را از لينک زير انتخاب نماييد</span>
				</div>
				<div style="padding:15px 15px 0 15px;">
					<table width="100%">
						<tr>
							<td width="150"><strong>محصول</strong></td>
							<td>
								<select id="product_selector">
									<?php
									global $wpdb;
									$table_name = $wpdb->prefix . "pfd_products";
									$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC;" ,ARRAY_A);
									if (count($products) == 0) {
									?>
									محصولي وجود ندارد. <a href="<?php echo get_option('siteurl') . "/wp-admin/admin.php?page=paypal-file-download-products" ?>">نوشته خود را ذخيره کنيد و سپس اينجا کليک نماييد.</a>
									<?php
									} else {
										foreach($products as $product) {
									?>
											<option value="<?php echo $product["id"] ?>"><?php echo $product["name"] ?> (<?php echo $product["cost"] ?> ريال)</option>
									<?php
									}
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<td width="135"><strong>لينک تصوير پرداخت:</strong></td>
							<td><input type="text" id="button_image_url" value="http://www.zarinpal.com/img/merchant/merchant-6.png" style="width:220px;" /></td>
						</tr>
					</table>
				</div>

				<div style="padding:15px;">
					<input type="button" class="button-primary" value="قرار دادن Button" onclick="insert_pfd_button();"/>&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="button" value="قرار دادن لينک" onclick="insert_pfd_link();"/>&nbsp;&nbsp;&nbsp;&nbsp;<a style="font-size:0.9em;text-decoration:none;color:#555555;" href="#" onclick="tb_remove(); return false;">بستن</a>
				</div>
			</div>
		</div>
	</div>
	<?php
	}

	protected static function ipn() {
		echo "<br/><div align='center' dir='rtl' style='font-family:tahoma;font-size:12px;'><b>نتیجـــه تـــراکنـش</b></div><br />";
		@session_start();
		require_once('zarinpal.class.php');
		$p = new zarinpal_class;
        $api = get_option('paypal_email');;
        $au = $_GET['Authority'];
        $amount = $_SESSION['m-amount'];
        $result = self::get($api,$au,$amount);
		



		$this_script = get_option('siteurl');


		if ($result->Status == 100) {
			// find with order id
			global $wpdb;
			$table_name = $wpdb->prefix . "pfd_orders";
			$trans_id = $result->RefID;
                     	$aut = $_GET['Authority'];
			$order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_code = '$aut' AND fulfilled = 0",$trans_id) , ARRAY_A, 0);
			
			$wpdb->update( $table_name, array('fulfilled' => 1), array('id' => $order["id"]));



			$table_name = $wpdb->prefix . "pfd_products";
			$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$order["product_id"]) , ARRAY_A, 0);

			$wpdb->update( $table_name, array('downloads' => $product["downloads"] + 1), array('id' => $product["id"]));

			$trans = array();

			// vars we want to extract
			$fields = "protection_eligibility address_status payer_id tax payment_date payment_status first_name last_name payer_status business address_name address_street address_city address_state address_zip address_country_code address_country quantity verify_sign payer_email txn_id payment_type receiver_email receiver_id txn_type item_name mc_currency item_number residence_country custom receipt_id transaction_subject payment_fee payment_gross";

			$fields_a = explode(" ",$fields);

			foreach($fields_a as $field) {
				$trans[$field] = isset($_POST[$field]) ? $_POST[$field] : NULL;
			}

			$trans["product_id"] = $product["id"];
			$trans["order_code"] = $trans_id;
			$trans["order_id"] = $order["id"];
			@session_start();
			$trans["payer_email"] = $_SESSION['email'];
			$trans["created_at"] = time();

			// insert into transactions
			$table_name = $wpdb->prefix . "pfd_transactions";
			$wpdb->insert($table_name, $trans);


			// download link
			if(get_option("paypal_direct") == 1){
				$download_link = $product["file"];
				$download_name = $product["name"];
				$download_link = "<a href='$download_link'>$download_name</a>";
			}else{
				$download_link = get_option('siteurl') . "/?download=" . $trans_id;
				$download_link = "<a href='$download_link'>$download_link</a>";
			}
            


			// get email text
			$emailtext = get_option('email_message');
			$emailtext = str_replace("[DOWNLOAD_LINK]",$download_link,$emailtext);
			$emailtext = str_replace("[PRODUCT_NAME]",$product["name"],$emailtext);
			$emailtext = str_replace("[TRANSACTION_ID]",$trans_id,$emailtext);

			$emailtext = $emailtext . "<br /><br />لينک دانلود شما:<br />" . $download_link;

			// fantastic, now send them a message

			$message = $emailtext;
			echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='green'><b>مـوفق بود</b></font>.<br/><p align='right' style='margin-right:15px'>".nl2br($message)."</p><a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a><br/><br/></div>";
			@session_start();
			$headers = "From: <no-reply>\n";
			$headers .= "MIME-Version: 1.0\n";
			$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
			mail($_SESSION['email'],'اطلاعات پرداخت',$emailtext,$headers);

		}else{
            echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.
			<br/><p align='right' style='margin-right:15px'> ممکن است به یکی از دلایل زیر باشد:
			<br/>1- ممکن است ارتباط شما با دروازه پرداخت بانک برقرار نشده باشد
			<br/>2- ممکن است از انجام عملیات پرداخت منصرف شده باشید
			<br/>3- ممکن است سرعت اینترنت شما درحال حاضر کم باشد و قادر به باز کردن درگاه پرداخت بانک نباشید.
			<br/>4- کد خطا : " .$result->Status . "
			<br/> لطفا به صفحه اصلی سایت بازگشته و مجددا خرید خود را انجام دهید.
			</p><a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a><br/><br/></div>";
        }
	}

	public static function send($desc,$api,$amount,$redirect,$payer_mail){
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
	$res = $client->PaymentRequest(
	array(
					'MerchantID' 	=> $api ,
					'Amount' 		=> $amount ,
					'Description' 	=> $desc ,
					'Email' 		=> $payer_mail ,
					'Mobile' 		=> '' ,
					'CallbackURL' 	=> $redirect

					)
	 );
        return $res;
    }
    public static function get($api,$au,$amount){
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
	$res = $client->PaymentVerification(
			array(
					'MerchantID'	 => $api ,
					'Authority' 	 => $au ,
					'Amount'	 	=> $amount
				)
		);
        return $res;
    }
	
	protected static function get_email() {
		echo "<br/><div align='center' dir='rtl' style='font-family:tahoma;font-size:12px;'><b>اطلاعات تکمیلی</b></div><br />";
		@session_start();
		$rand = rand(10,99);
		$_SESSION['captcha'] = $rand;
		echo '<div align="center" dir="rtl" style="font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%"><form name="frm1" method="post">
		<table>
		<tr>
			<td>ایمیل: </td><td><input type="email" name="email" id="email" required value="'.$_POST['email'].' " /> </td>
		</tr>
		<tr>
		<td>لطفاً عدد '.$rand.' را وارد کنید:</td><td><input type="number" min="10" max="99" name="captcha" required/> </td>
		</tr>
		<tr>
			<td></td><td><input type="submit" name="submit" value="پرداخت" style="font-family:tahoma"/></td>
		</tr>
		</table>
		</form>
		</div><div style="display:none">
		';
	}
	public static function var_listener() {
         if(get_query_var("checkout")==NULL) {
			if(get_query_var("download")==NULL) {
				if (get_query_var("pfd_action") == "ipn") {
					self::ipn();
					exit();
				}
			} else {
				$id = $_GET["download"];
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_transactions";
				$transaction = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_code = %s",$id ), ARRAY_A, 0);
				if ($transaction==NULL) {
					die("فايل مورد نظر يافت نشد.");
				} else {
					$table_name = $wpdb->prefix . "pfd_products";
					$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$transaction["product_id"]), ARRAY_A, 0);
					// get option for days
					$daysexpire = get_option('expire_links_after');
					if ($daysexpire == 0) {
						// don't check
					} else {
						// check for expiry
						// transaction created at should be larger than now - x days
						$nowminus = time() - ($daysexpire*86400);
						if ($transaction["created_at"] > $nowminus) {
							// good
						} else {
							die("مدت زمان دانلود اين فايل به اتمام رسيده است.");
						}
					}

					// force download

					header('Content-disposition: attachment; filename=' . basename($product["file"]));
					header('Content-Type: application/octet-stream');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Expires: 0');



					$result = wp_remote_get($product["file"]);

					echo $result['body'];
					//die();
				}
			}
		} else {
			@session_start();
			if(isset($_POST['submit']) && ($_SESSION['captcha'] == $_POST['captcha']) && $_SESSION['captcha'] != ''){
				$_SESSION['email'] = $_POST['email'];
				$product_id = get_query_var("checkout");
				$payer_mail = $_POST['email'];
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_products";
	
				// get product
				$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$product_id) , ARRAY_A, 0);
	
				// construct order
				$table_name = $wpdb->prefix . "pfd_orders";
	
	
	
	
				$tozih = $product["name"];
				$api = get_option('paypal_email');
				$amount = $product["cost"];
				$_SESSION['m-amount'] = $amount;
				$redirect = get_option('siteurl') . "/?pfd_action=ipn";
				$result = self::send($tozih,$api,$amount,$redirect,$payer_mail);
				if($result->Status == 100){
				
					$go = "https://www.zarinpal.com/pg/StartPay/" . $result->Authority ."/ZarinGate" ;
					$wpdb->insert( $table_name, array('product_id' => $product_id, 'order_code' => $result->Authority, 'fulfilled' => 0, 'created_at' => time(), 'cost' => $product["cost"]), array( '%d', '%s', '%d', '%d', '%s') );
					header("Location: $go");
				}else{
					echo "در برقراري ارتباط با درگاه پرداخت Zarinpal مشکلي بوجود آمده است".$result->Status;
					exit();
				}
			}else{
				self::get_email();
				exit();
			}

	}
    }
	// make sure we have the paypal action listener available
	public static function register_vars($vars) {
		$vars[] = "pfd_action";
		$vars[] = "checkout";
		$vars[] = "download";
		return $vars; // return to wordpress
	}
}
