<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wp_dsfaq_plus_install() {

	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name = $wpdb->prefix . "dsfaq_name";
	$table_quest = $wpdb->prefix . "dsfaq_quest";

	$charset_collate = $wpdb->get_charset_collate();

	$sql1 = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name_faq text NOT NULL,
		sort int NOT NULL DEFAULT 0,
		mode int NOT NULL DEFAULT 0,
		sort_key varchar(50) DEFAULT '',
		sort_order varchar(10) DEFAULT '',
		visible int NOT NULL DEFAULT 1,
		PRIMARY KEY (id)
	) $charset_collate;";

	$sql2 = "CREATE TABLE $table_quest (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		id_book int NOT NULL,
		date datetime NOT NULL,
		quest text NOT NULL,
		answer text NOT NULL,
		sort int NOT NULL DEFAULT 0,
		PRIMARY KEY (id)
	) $charset_collate;";

	dbDelta($sql1);
	dbDelta($sql2);
}

register_activation_hook(
	WP_DSFAQ_PLUS_BOOTSTRAP_FILE,
	'wp_dsfaq_plus_install'
);

class dsfaq{
    var $plugurl; // 設定からの変更 2011.03.19変更
    var $plugurl_front;  // FAQページからの直接変更 2011.03.19
    var $plugdir;
    var $wp_ds_faq_default_array;
	var $settings_editor_permission=false; // 編集者権限があるかどうかのチェック 2011.08.25 (1.0.11)
	var $settings_admin_permission=false; // 管理者権限があるかどうかのチェック 2011.08.25 (1.0.11)
    var $debug=true;  // デバッグ用（本運用はfalseにすること） 2011.08.29 (1.0.12)
    var $wp_ds_faq_plus_default_array;

    ##############################################################
    # dsfaq()                                                    #
    #   Конструктор                                              #
    ##############################################################------------------------------------------------------------#
    function __construct(){
        $this->plugurl = plugin_dir_url( WP_DSFAQ_PLUS_BOOTSTRAP_FILE );

/**		SSL対策が必要。
		- 1: ログイン後の設定画面からの編集（https://）
		- 2: FAQ閲覧画面からの変更（http://）
	    このように２通りあることが問題
	   「２」については、$this->plugurl_frontを使うように分けることで対処する
        つまり下記の関数のうち＊がついているものを変更
			dsfaq_open_quest *
			dsfaq_close_quest
			dsfaq_front_edit_quest *
			dsfaq_front_cancel_edit *
			dsfaq_front_update_quest *
			dsfaq_front_delete_quest *
			dsfaq_front_bg_color

**/
    // ここでwp-config.phpにFORCE_SSL_ADMINが設定されていて、その値がtrueなら（2011.04.22）
    // 管理画面内でのアクセスのみURLをhttpsに書き換え
		$this->plugurl_front = $this->plugurl; 
		if ( defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN == true )
			$this->plugurl = str_replace('http://', 'https://', $this->plugurl);
	
		// 2011.08.24 (1.0.11): 「編集者」以上でFAQにアクセスできるように調整
		// ただし設定については、管理者でないとアクセスできないように調整
		if( !defined('WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY') )
			define( 'WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY', 'level_7');
		if( !defined('WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY') )
			define( 'WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY', 'level_8'); 
			
        $this->plugdir = plugin_dir_path( WP_DSFAQ_PLUS_BOOTSTRAP_FILE );
        
        $this->wp_ds_faq_default_array['wp_ds_faq_db_ver']        = '0.3';
        $this->wp_ds_faq_plus_default_array['wp_ds_faq_plus_db_ver']   = '0.1'; // 2011.08.29 (1.0.12) Add custom_mode to dsfaq_name table for custom sort.
        $this->wp_ds_faq_default_array['wp_ds_faq_showcopyright'] = true;
        $this->wp_ds_faq_default_array['wp_ds_faq_ver']           = '133'; // 2011.08.22 (1.0.10): Change 132 to 133
        $this->wp_ds_faq_plus_default_array['wp_ds_faq_plus_ver']      = '1502'; // 2011.08.29 (1.0.12): Version 
        $this->wp_ds_faq_default_array['wp_ds_faq_h1']            = '<h3>';
        $this->wp_ds_faq_default_array['wp_ds_faq_h2']            = '</h3>';
        $this->wp_ds_faq_default_array['wp_ds_faq_css']           = "<style type='text/css'>\n".
                                                                    ".dsfaq_qa_block{ border-top: 1px solid #aaaaaa; margin-top: 20px; }\n".
                                                                    ".dsfaq_ol_quest{ }\n".
                                                                    ".dsfaq_ol_quest li{ }\n".
                                                                    ".dsfaq_ol_quest li a{ }\n".
                                                                    ".dsfaq_quest_title{ font-weight: bold; }\n".
                                                                    ".dsfaq_quest{ }\n".
                                                                    ".dsfaq_answer_title{ font-weight: bold; }\n".
                                                                    ".dsfaq_answer{ border: 1px solid #f0f0f0; padding: 5px 5px 5px 5px; }\n".
                                                                    ".dsfaq_tools{ text-align: right; font-size: smaller; }\n".
                                                                    ".dsfaq_copyright{ display: block; text-align: right; font-size: smaller; }\n".
                                                                    "</style>";
        
        add_action('init', array(&$this, 'enable_getext'));
        add_action('wp_head', array(&$this,'add_to_wp_head'));
        add_action('admin_menu', array(&$this, 'add_to_settings_menu'));
        add_action('admin_head', array(&$this, 'add_to_admin_head'));

        $ajax_actions = array('add_faq','delete_faqbook','save_quest','delete_quest','edit_quest','front_edit_quest','front_cancel_edit','update_quest','front_update_quest','q_change','save_settings','edit_name_book','save_name_book','change_faqdisplay','change_faqdisplaysort','change_faqdisplayorder','dsfaq_faqdisplay_visible','open_quest','restore_settings');
        foreach ( $ajax_actions as $ajax_action ) {
            add_action( 'wp_ajax_' . $ajax_action, array( &$this, 'dsfaq_ajax_bridge' ) );
        }
        add_action( 'wp_ajax_nopriv_open_quest', array( &$this, 'dsfaq_ajax_bridge' ) );

        add_shortcode('dsfaq', array(&$this, 'faq_shortcode'));
        
        add_filter('the_content', array(&$this, 'faq_hook'), 10 ,2);
        
        register_activation_hook(WP_DSFAQ_PLUS_BOOTSTRAP_FILE, array(&$this, 'installer'));
        register_deactivation_hook(WP_DSFAQ_PLUS_BOOTSTRAP_FILE, array(&$this, 'deactivate'));
    }
    # END dsfaq ##################################################------------------------------------------------------------#

    function dsfaq_ajax_bridge(){
        $hook_action = current_action();
        if ( 0 === strpos( $hook_action, 'wp_ajax_nopriv_' ) ) {
            $_POST['action'] = substr( $hook_action, 16 );
        } elseif ( 0 === strpos( $hook_action, 'wp_ajax_' ) ) {
            $_POST['action'] = substr( $hook_action, 8 );
        }

        dsfaq_plus_handle_ajax_action();
    }

    ##############################################################
    # installer()                                                #
    #   Функция вызываемая при активации плагина                 #
    ##############################################################------------------------------------------------------------#
    public function installer(){
        global $wpdb;
//        $wpdb->show_errors();
        $wpdb->hide_errors(); // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        // custome_mode is for Custom Display Mode of WP DS FAQ Plus 1.0.9 (2011.08.29: 1.0.12)
        // visible: you can control about the visible setting 2011.09.07 (1.0.13)
        $table_name = $wpdb->prefix."dsfaq_name";
            $sql = ' CREATE TABLE '.$table_name.' (
                    `id` INT NOT NULL AUTO_INCREMENT ,
                    `name_faq` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
                    `mode` INT NOT NULL ,
                    `custom_mode` INT NOT NULL ,
                    `visible` INT NOT NULL ,
                     PRIMARY KEY  ( `id` )
                     ) ENGINE = MYISAM DEFAULT CHARSET=utf8 ';
            dbDelta($sql);

        $table_name = $wpdb->prefix."dsfaq_quest";
            $sql = ' CREATE TABLE '.$table_name.' (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `id_book` INT NOT NULL,
                    `date` DATETIME NOT NULL,
                    `quest` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                    `answer` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                    `sort` INT NOT NULL,
                     PRIMARY KEY  ( `id` )
                     ) ENGINE = MYISAM DEFAULT CHARSET=utf8 '; 
            dbDelta($sql);

        // Если плагин ставиться впервые то сохраняем настройки по умолчанию
        if(!get_option('wp_ds_faq_array')){
            add_option('wp_ds_faq_array', $this->wp_ds_faq_default_array);
        }

        $settings = get_option('wp_ds_faq_array');

		// 2011.08.29 (1.0.12): 初めてplusのver変数をみたときに初期設定の見直し（1.0.12以上を初めてインストール・有効化した場合）
		if(!isset($settings['wp_ds_faq_plus_ver'])){
            foreach($this->wp_ds_faq_default_array as $key=>$value)
              if($settings[$key] == "") $settings[$key] = $value;

			// modeにWP DS FAQ Plus用の拡張データ（1.0.9以降）が入っていたなら、それをcustom_modeに移行。modeの方は従来に戻す（１の位の値を適応）
        	$table_name = $wpdb->prefix."dsfaq_name";

	        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i", $table_name ), ARRAY_A );

    	    if($select){
        	    foreach ($select as $rows=>$data) {
        	    	$mode = (int) k_escape($data['mode']);
        	    	$sort = $order = 0;
        	    	if(!is_int($mode) || $mode < 0) $mode = 0;
        	    	else if($mode >= 10){
					    $sort = (($mode - ($mode % 10)) / 10) % 10; 
					    if($mode >= 100)
	       					$order = (( $mode - ($sort * 10) - ($mode % 10) ) / 100 ) % 100;
	            	    		// Plus用のカスタム拡張がされている。1の位を取ればいい
        	    		$mode = $mode % 10;
        	    	}else $mode = 0; // 変な値が入っている場合

			        $custom_mode = (int) ($mode + $sort*10 + $order*100);


        	    	// modeデータをmode_cunstomにコピーし、カスタムしたmodeデータを元に戻し、modeを差し替える
			        // visibleの初期値は1（default value of visible is 1. All FAQ is published. (2011.09.07: 1.0.13)
        			// In case of displaying latest FAQ ([dsfaq latest=10 /]), you may have invisible data.
	       			$wpdb->update(
	       				$table_name,
	       				array(
	       					'visible' => 1,
	       					'mode' => $mode,
	       					'custom_mode' => $custom_mode,
	       				),
	       				array( 'id' => (int) $data['id'] ),
	       				array( '%d', '%d', '%d' ),
	       				array( '%d' )
	       			);
	            }
    	    }
        }
        // visibleの初期値は1（default value of visible is 1. All FAQ is published. (2011.09.07: 1.0.13)
        // In case of displaying latest FAQ ([dsfaq latest=10 /]), you may have invisible data.
   		if( isset($settings['wp_ds_faq_plus_ver']) && $settings['wp_ds_faq_plus_ver'] < 1013 ){
        	$table_name = $wpdb->prefix."dsfaq_name";
	        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i", $table_name ), ARRAY_A );

	    	    if($select){
	        	    foreach ($select as $rows=>$data) {
				    $wpdb->update(
					$table_name,
					array( 'visible' => 1 ),
					array( 'id' => (int) $data['id'] ),
					array( '%d' ),
					array( '%d' )
				    );
				}
			}
   		}
		$settings['wp_ds_faq_plus_ver'] = $this->wp_ds_faq_plus_default_array['wp_ds_faq_plus_ver']; // 2011.09.22 (1.0.14)
   		
		// 2011.08.29 (1.0.12): 新設したPlus用初期設定変数が$settingsに無ければ、追加
		if(isset($this->wp_ds_faq_plus_default_array) && is_array($this->wp_ds_faq_plus_default_array))
			foreach($this->wp_ds_faq_plus_default_array as $key=>$value)
              if(!isset($settings[$key])) $settings[$key] = $value;


		// ユーザが設定を定義していない、あるいはデータを消した場合には初期値を代入（2011.08.25: 1.0.11）
		if(!isset($settings['wp_dsfaq_plus_enable_ratings']))
			$settings['wp_dsfaq_plus_enable_ratings'] = 0;
		if(!isset($settings['wp_dsfaq_plus_editor_permission']) || empty($settings['wp_dsfaq_plus_editor_permission']))
			$settings['wp_dsfaq_plus_editor_permission'] = WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY;
		if(!isset($settings['wp_dsfaq_plus_admin_permission']) || empty($settings['wp_dsfaq_plus_admin_permission']))
			$settings['wp_dsfaq_plus_admin_permission'] = WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY;
		if(!isset($settings['wp_dsfaq_plus_disable_all_delete']))
			$settings['wp_dsfaq_plus_disable_all_delete'] = 0;

		if(!isset($settings['wp_dsfaq_plus_disable_category_delete']))
			$settings['wp_dsfaq_plus_disable_category_delete'] = 1;
		if(!isset($settings['wp_dsfaq_plus_disable_edit_delete']))
			$settings['wp_dsfaq_plus_disable_edit_delete'] = 0;
		if(!isset($settings['wp_dsfaq_plus_disable_frontedit_delete']))
			$settings['wp_dsfaq_plus_disable_frontedit_delete'] = 1;
		if(!isset($settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']))
			$settings['wp_dsfaq_plus_apply_safetyoptions_to_admin'] = 1;

		// 2011.08.26 (1.0.11): タイトルの非表示
		if(!isset($settings['wp_dsfaq_plus_general_display_title']))
			$settings['wp_dsfaq_plus_general_display_title'] = 0;
		
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;

        // 初めて本プラグインを入れた時の処置
        if($settings['wp_ds_faq_ver'] < 130){
            if(strpos($settings['wp_ds_faq_css'], ".dsfaq_tools") === false){
                $settings['wp_ds_faq_css']    = str_replace('</style>', ".dsfaq_tools{ display: block; text-align: right; font-size: smaller; }\n</style>", $settings['wp_ds_faq_css']);
                $settings['wp_ds_faq_db_ver'] = '0.2';
                $settings['wp_ds_faq_ver']    = '130';
//                update_option('wp_ds_faq_array', $settings);
            }
        }
        
        if($settings['wp_ds_faq_ver'] < 132){
            $table_name = $wpdb->prefix."dsfaq_name";
            $wpdb->query( $wpdb->prepare( " ALTER TABLE %i DEFAULT CHARSET=utf8, MODIFY COLUMN `name_faq` TEXT CHARACTER SET utf8 ", $table_name ) );
            
            $table_name = $wpdb->prefix."dsfaq_quest";
            $wpdb->query( $wpdb->prepare( " ALTER TABLE %i DEFAULT CHARSET=utf8, MODIFY COLUMN `quest` TEXT CHARACTER SET utf8, MODIFY COLUMN `answer` TEXT CHARACTER SET utf8 ", $table_name ) );
            
            $settings['wp_ds_faq_db_ver'] = '0.3';
            $settings['wp_ds_faq_ver']    = '132';
//            update_option('wp_ds_faq_array', $settings);
        }
        // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)       
        if($settings['wp_ds_faq_ver'] < 133){
            $settings['wp_ds_faq_ver']    = '133';
//            update_option('wp_ds_faq_array', $settings);
        }
        
        
        // 2011.08.25 (1.0.11) 必ず設定の初期化を行うように変更
        update_option('wp_ds_faq_array', $settings);
        
    }
    # END installer ##############################################------------------------------------------------------------#
    
    ##############################################################
    # add_to_wp_head()                                           #
    #   Добавляет стили и скрипты в заголовок wp                 #
    ##############################################################------------------------------------------------------------#
    function add_to_wp_head(){
        // use JavaScript SACK library for Ajax
        wp_print_scripts( array( 'sack' ));

        $settings = get_option('wp_ds_faq_array');
        // 2011.08.25 (1.0.11)  Refresh information of current user permission 
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;

        ?>
        <!-- WP DS FAQ -->
        <?php // 2011.09.13 (1.0.13) for CSS ?>
        <link rel='stylesheet' id='wp_ds_faq_plus_latest_information'  href='<?php echo $this->plugurl_front . "wp-ds-faq-plus.css"; ?>' type='text/css' media='all' /> 
        
        <?php if (isset($settings['wp_ds_faq_css'])){echo stripslashes($settings['wp_ds_faq_css']);}; ?>
        <script>
        //<![CDATA[
        function dsfaq_open_quest(id){
          document.getElementById("dsfaq_answer_" + id).innerHTML = '<img src="<?php echo $this->plugurl_front; ?>img/ajax-loader.gif" />';
              var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'open_quest' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 10]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_close_quest(id){
            document.getElementById("dsfaq_answer_" + id).innerHTML = '';
            if(document.getElementById("dsfaq_tools_" + id)){
                document.getElementById("dsfaq_tools_" + id).innerHTML = '';
            }
            
            return true;
        }
        <?php 
			// 2011.08.25 (1.0.11) Limitation by settings and current user permission
//            if(current_user_can('level_10')){
			if($this->settings_editor_permission){
		?>

        
        function dsfaq_front_edit_quest(id){
            document.getElementById("dsfaq_quest_" + id).innerHTML = '<img src="<?php echo $this->plugurl_front; ?>img/ajax-loader.gif" />';
            document.getElementById("dsfaq_answer_" + id).innerHTML = '<img src="<?php echo $this->plugurl_front; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'front_edit_quest' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 10]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_front_cancel_edit(id){
            document.getElementById("dsfaq_answer_" + id).innerHTML = '<img src="<?php echo $this->plugurl_front; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'front_cancel_edit' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 10]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_front_update_quest(id){
            var dsfaq_quest = document.getElementById("dsfaq_inp_quest_" + id).value;
            var dsfaq_answer = document.getElementById("dsfaq_txt_answer_" + id).value;
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'front_update_quest' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'dsfaq_quest', dsfaq_quest );
            mysack.setVar( 'dsfaq_answer', dsfaq_answer );
            mysack.onError = function() { alert('Ajax error. [Error id: 6]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_front_delete_quest(id){
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            var front = 1;
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'delete_quest' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'front', front );
            mysack.onError = function() { alert('Ajax error. [Error id: 4]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_front_bg_color(id, callback){
        	var count = 10;
        	var timeout = 100;
        	var hex = 205;
        	var divNode1 = document.getElementById(id);
        	var updataId = setInterval(function(){
                    if(count > 0){
                        hex = hex + 5;
                        divNode1.style.backgroundColor = '#ff' + Number(hex).toString(16) + Number(hex).toString(16);
                        --count;
                    }else{
                        clearInterval(updataId);
                        if(callback){ callback(); }
                    }
                }, timeout);
            return true;
        }
        <?php } ?>
        //]]>
        </script>
        <!-- END WP DS FAQ -->
        <?php
    }
    # END add_to_wp_head #########################################------------------------------------------------------------#
    
    ##############################################################
    # add_to_admin_head()                                        #
    #   Добавляет стили и скрипты в заголовок админ панели       #
    ##############################################################------------------------------------------------------------#
    function add_to_admin_head(){
        ?>
        <!-- WP DS FAQ -->
        <link rel="stylesheet" href="<?php echo $this->plugurl; ?>wp-ds-adminfaq.css" type="text/css" media="screen" />
        <!-- End WP DS FAQ -->
        <?php
    }
    # END add_to_admin_head ######################################------------------------------------------------------------#

    ##############################################################
    # deactivate()                                               #
    #   Функция вызываемая при деактивации плагина               #
    ##############################################################------------------------------------------------------------#
    function deactivate(){
        
    }
    # END deactivate #############################################------------------------------------------------------------#
    
    ##############################################################
    # enable_getext()                                            #
    #  Говорим WordPress-у что у нас многоязычие в плагине       #
    ##############################################################------------------------------------------------------------#
    function enable_getext() {
    }
    # END enable_getext ##########################################------------------------------------------------------------#
    
    ##############################################################
    # faq_hook()                                                 #
    #   Функция нужна для отлова фильтра dsfaq_filters           #
    #   apply_filters('the_content', $filtered, 'dsfaq_filters') #
    #   Отлавливаем есть ли у нас FAQ внутри FAQ-а и изменяем    #
    #   шорткод, чтобы он не приминился                          #
    #                                                            #
    #   $content - текст к которому применяется фильтр           #
    #   $dsfaq_filters - Проверочный флаг. Нам нужен только тот  #
    #      фильтр который вызвался нашим обработчиком шорткодов  #
    ##############################################################------------------------------------------------------------#
    function faq_hook($content, $dsfaq_filters = false){
        if($dsfaq_filters){
            $content = str_replace('[dsfaq ', '<span>[</span>dsfaq ', $content);
        }
        return $content;
    }
    # END faq_hook ###############################################------------------------------------------------------------#
    
    ##############################################################
    # faq_shortcode()                                            #
    #   При нахождении строки [dsfaq] нужно вытащить ID и        #
    #   отобразить соответсвующую страницу с вопросами и ответами#
    ##############################################################------------------------------------------------------------#
    function faq_shortcode($atts){
        if (isset($atts)){
            $settings = get_option('wp_ds_faq_array');
			// 2011.09.06 (1.0.13): bug fix
//            (int)$id  = $atts['id'];
			if(isset($atts['id'])) $id  = (int) $atts['id'];
			else $id = 0;
			 
            // 2011.09.06 (1.0.13): 新着情報のため（latest=5で最新５件、latestが0以下や予期せぬなら5件にする）、さらにorderbydescで降順・昇順選択ができるようにした。onなら降順、それ以外なら昇順とする。
            if(isset($atts['latest'])){
                // Security: $latest is only arrowed integer number
            	$latest = (int) $atts['latest'];
            	if(empty($latest) || $latest <= 0) $latest = 5;
            	if(isset($attrs['orderbydesc'])) $orderbydesc = $atts['orderbydesc'];
            	else $orderbydesc = "off";
            	if($orderbydesc == "on") $orderby = "DESC";
            	else $orderby = "ASC";
 	
 				// 2011.09.13 (1.0.13)
 				if(isset($atts['latest_format'])){
 					$latest_format = $atts['latest_format'];
 					if ($latest_format == "li") ;
 					else if ($latest_format == "table") ;
 					else if ($latest_format == "dl") ;
 					else $latest_format = "li";
 					// "li" is (<li><dl><dt>Date</dt><dd>Content</dd></dl></li>)
 				}else $latest_format = "li";
 	
            	return $this->get_latest_faq($latest, $orderby, $latest_format);            	
			}            

        // 2011.08.25 (1.0.11)  Refresh information of current user permission 
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;

        
            $book  = $this->get_faq_book(false, $id, true);
            
            // 2011.08.29 (1.0.12)
//            $mode = $book[0]['mode'];
            $mode = $book[0]['custom_mode'];
            // 2011.09.07 (1.0.13) visible: priority 1: [dsfaq latest=5 visible="on/off"] (only editor permission), 2: database setting
            if($this->settings_editor_permission && isset($atts['visible'])){
            	$status = $atts['visible'];
            	if($status == "off") $visible = 0;
            	else if($status == "on") $visible = 1;
            }
            if(!isset($visible)){
	            $visible = intval($book[0]['visible']);
    	        if($visible != 0) $visible = 1;
			}
            // 2011.07.19: 1.0.9 （DBのmodeからソートの抽出）
			if(!is_numeric($mode) || $mode < 10){ $sort = 0; $order = 0; }
			else{
			  $sort = (($mode - ($mode % 10)) / 10) % 10; // 十の位を取り出す 2011.07.19: 1.0.9 for sort
			  if($mode >= 100)
			    $order = (( $mode - ($sort * 10) - ($mode % 10) ) / 100) % 100; // 百の位を取り出す 2011.07.19: 1.0.9 for order
			  else $order = 0;
			  $mode = $mode % 10; //一の位を取り出す2011.07.19: 1.0.9 for sort
			} 

            // 2011.07.19: 1.0.9 for sort
//            	 $quest = $this->get_quest_from_faq($id, false, false, true);
  		    $quest = $this->get_quest_from_faq($id, false, false, true, $sort, $order);
 
/*        
            $results = $settings['wp_ds_faq_h1'];
            $results .= $book[0]['name_faq'];
            $results .= $settings['wp_ds_faq_h2'];
*/
            $results = "";
			// 2011.08.26 (1.0.11-1)
			if(isset($settings['wp_dsfaq_plus_general_display_title']) && $settings['wp_dsfaq_plus_general_display_title']){
			// 2011.09.06 (1.0.13)
//            	$results = $this->wp_ds_faq_default_array['wp_ds_faq_h1'];
            	$results = $settings['wp_ds_faq_h1'];
            	$results .= $book[0]['name_faq'];
//           	$results .= $this->wp_ds_faq_default_array['wp_ds_faq_h2'];
	           	$results .= $settings['wp_ds_faq_h2'];
			}
	  // 2011.07.01: 1.0.7: 何も情報がなければ作成中にしておく
	  // 2011.07.11: 1.0.8: <div class="dsfaq_plus_under_construction"> で括り、CSSでの制御を可能にした
	   if (empty($quest)){
		$results .= '<div class="dsfaq_plus_under_construction">' . __('Under Construction.', 'wp-ds-faq-plus')  . '</div>';
		return $results;
	   }else if($visible == 0){ // 2011.09.07 (1.0.13)
		$results .= '<div class="dsfaq_plus_not_permission">' . __('Not Permission.', 'wp-ds-faq-plus')  . '</div>';
		return $results;
	   }

//            if($book[0]['mode'] == "0"){
            if($mode == "0"){  // 2011.07.19: 1.0.9 for sort
                if(is_array($quest)){
                    $results .= '<ol class="dsfaq_ol_quest">';
                    foreach ($quest as $s) { $results .= '<li id="dsfaq_li_'.esc_attr($s['id']).'"><a href="#'.esc_attr($s['id']).'">'.wp_strip_all_tags($s['quest']).'</a></li>'; }

  			    	// 2011.08.25 (1.0.11) Linkage of WP-PostRatings Plugin (Please see settings).
		        	if(!empty($quest)){
						$results .= '<div style="text-align:right">';
						if(function_exists('the_ratings') && $settings['wp_dsfaq_plus_enable_ratings'])
							$results .= the_ratings('div',0,false);
				 		$results .= '</div>';
					}	
				
                    $results .= '</ol>';


                    foreach ($quest as $s) {
                        $results .= '<div class="dsfaq_qa_block" id="dsfaq_qa_block_'.esc_attr($s['id']).'">';
                        $results .= '<p><a name="'.esc_attr($s['id']).'"></a><span class="dsfaq_quest_title">'.__('Question:', 'wp-ds-faq-plus').'</span> <span class="dsfaq_quest" id="dsfaq_quest_'.esc_attr($s['id']).'">'.wp_strip_all_tags($s['quest']).'</span></p>';
                        $results .= '<p><span class="dsfaq_answer_title">'.__('Answer:', 'wp-ds-faq-plus').'</span></p>';
                        $results .= '<div id="dsfaq_answer_'.esc_attr($s['id']).'"><div class="dsfaq_answer">';
                        $results .= wp_kses_post($s['answer']);					
                        $results .= '</div></div>';

						// 2011.08.25 (1.0.11) Limitation by settings and current user permission
						//            if(current_user_can('level_10')){
						if($this->settings_editor_permission){
                            $results .= '<div class="dsfaq_tools" id="dsfaq_tools_'.esc_attr($s['id']).'">';
                            $results .= '[ <a href="#_" onclick="dsfaq_front_edit_quest('.esc_attr($s['id']).');">'.__('Edit', 'wp-ds-faq-plus').'</a> ]'; 
							// 2011.03.31: disabled because for to enable it is the high risk. By Kitani.
							// 2011.08.25 (1.0.11) Limitation by settings and current user permission
							if (! $settings['wp_dsfaq_plus_disable_all_delete'] && ! $settings['wp_dsfaq_plus_disable_frontedit_delete'])
                            		$results .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.esc_attr($s['id']).');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';
							else if($this->settings_admin_permission && ! $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']){
                            		$results .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.esc_attr($s['id']).');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';
                            }
                            $results .= '</div>';
                        }

                        $results .= '</div>';
                    }
                }
                $results = apply_filters('the_content', $results, 'dsfaq_filters');
            }
            
//            if($book[0]['mode'] == "1"){
            if($mode == "1"){ // 2011.07.19: 1.0.9 for sort
                if(is_array($quest)){
                    $results .= '<ol class="dsfaq_ol_quest">';
                    foreach ($quest as $s) { 
                        $results .= '<li id="dsfaq_quest_'.esc_attr($s['id']).'"><a href="#_" onclick="dsfaq_open_quest('.esc_attr($s['id']).');">'.wp_strip_all_tags($s['quest']).'</a></li>';
                        $results .= '<div id="dsfaq_answer_'.esc_attr($s['id']).'"></div>';
                        $results .= '<div class="dsfaq_tools" id="dsfaq_tools_'.esc_attr($s['id']).'"></div>';
                    }

  			       // 2011.08.25 (1.0.11) Linkage of WP-PostRatings Plugin (Please see settings).
		           if(!empty($quest)){
					  $results .= '<div style="text-align:right">';
					  if(function_exists('the_ratings') && $settings['wp_dsfaq_plus_enable_ratings'])
					 	  $results .= the_ratings('div',0,false);
				 	  $results .= '</div>';
 				   }	

                    $results .= '</ol>';

                }
            }
			// 2012.12.11 (1.0.15)
            if ($settings['wp_ds_faq_showcopyright'] == true){$results .= '<br><a class="dsfaq_copyright" href="http://kitaney.jp/~kitani/tools/wordpress/wp-ds-faq-plus_en.html">&copy; Kimiya Kitani</a>';};

            return $results;
        }
        
    }
    # END faq_shortcode ##########################################------------------------------------------------------------#
    
    ##############################################################
    # add_to_settings_menu()                                     #
    #  Добавляем страницу с настройками плагина в меню Параметры #
    ##############################################################------------------------------------------------------------#

    function add_to_settings_menu(){
		// 2011.08.25 (1.0.11) Limitation by user permissions
        $settings = get_option('wp_ds_faq_array');

		// Permissionの初期値が定義されていて、ユーザが設定を定義していない、あるいはデータを消した場合には初期値を代入（2011.08.25: 1.0.11）
		if(!isset($settings['wp_dsfaq_plus_editor_permission']) || empty($settings['wp_dsfaq_plus_editor_permission']))
			$settings['wp_dsfaq_plus_editor_permission'] = WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY;
		if(!isset($settings['wp_dsfaq_plus_admin_permission']) || empty($settings['wp_dsfaq_plus_admin_permission']))
			$settings['wp_dsfaq_plus_admin_permission'] = WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY;
			
	   // 2011.08.25 (1.0.11) 英数字とアンダーライン以外の場合には、デフォルト値を導入（というのはエスケープ文字や' ''がはいると動作しないので）
	   if(! preg_match("/^[a-zA-Z0-9_]+$/", $settings['wp_dsfaq_plus_editor_permission']))	
			$settings['wp_dsfaq_plus_editor_permission'] = WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY;
	   if(! preg_match("/^[a-zA-Z0-9_]+$/", $settings['wp_dsfaq_plus_admin_permission']))	
			$settings['wp_dsfaq_plus_admin_permission'] = WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY;
	      
			
       // 2011.04.01: Add Main Menu
       // 2011.08.24 (1.0.11): 編集者でもFAQの編集ができるようにする
       // 2011.08.25 (1.0.11): 設定名の多言語化
       	add_menu_page(__('WP DS FAQ Plus', 'wp-ds-faq-plus'), __('FAQ', 'wp-ds-faq-plus'), $settings['wp_dsfaq_plus_editor_permission'], 'wp-ds-faq-plus', array(&$this, 'options_page') );

       // 2011.04.24 (1.0.6): カスタムメニューのサブメニューに全カテゴリーを表示（将来的には多言語対応にしたい）
       // 2011.08.26 (1.0.11): 権限の引数を渡すの忘れていた
        $this->add_to_setting_category_submenu($settings['wp_dsfaq_plus_editor_permission']);

        // 2011.08.24 (1.0.11): 設定の「DS FAQ Plus」は管理者権限が必要とする（従来のまま）
        // そのためには、ここでは編集者以上に権限を与えて置いて、別途設定にアクセスしたときに制限するしかない
        add_options_page(__('WP DS FAQ Plus Admin Settings', 'wp-ds-faq-plus'), __('FAQ Settings','wp-ds-faq-plus'), $settings['wp_dsfaq_plus_admin_permission'], 'wp-ds-faq-plus-admin', array(&$this, 'options_page'));
//        add_options_page('WP DS FAQ Plus Admin Settings', 'DS FAQ Plus', WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY, __FILE__, $this->admin_settings_menu);
       
        // 以下は、管理メニューに最初にアクセスしたときに出るメニュー
        //add_submenu_page('options-general.php', 'WP DS FAQ Settings', 'DS FAQ Plus', WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY, __FILE__, array(&$this, 'options_page'));
        //add_submenu_page(__FILE__, 'WP DS FAQ Settings', 'DS FAQ Plus', WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY, __FILE__, array(&$this, 'options_page'));
    }

   // 2011.04.23 (1.0.6): カスタムメニューのサブメニューに全カテゴリーを表示（将来的には多言語対応にしたい）
    function add_to_setting_category_submenu($permission){
        global $wpdb;
        $table_name = $wpdb->prefix."dsfaq_name";

// 2011.08.22 (1.0.10): サブメニューは、名前順に並ぶように変更（ID順だと分かりづらい）
//        $sql = "SELECT * FROM `".$table_name."` ORDER BY `id` ASC"; 
        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY `name_faq` ASC", $table_name ), ARRAY_A );

        if($select){
            foreach ($select as $s) {
                // 2011.08.24 (1.0.11): 編集者でもFAQの編集ができるようにする
                $sid = (int) $s['id']; // 2011.08.29 (1.0.12) セキュリティ対策
//                if(is_int($sid) && $sid > 1)
                if(is_int($sid) && $sid > 0) // 2011.09.13 (1.0.13) fix（最初のカテゴリーが表示されない問題）
            	add_submenu_page('wp-ds-faq-plus', wp_strip_all_tags($s['name_faq']), wp_strip_all_tags($s['name_faq']), $permission, 'wp-ds-faq-plus&add_sub_id='.$sid, array(&$this, 'options_page'));
            }
        }
    }


    # END add_to_settings_menu ###################################------------------------------------------------------------#

// 設定用 (Settings Menu) 2011.08.24-25 (1.0.11)
    function admin_settings_page(){   	
        $settings = get_option('wp_ds_faq_array');

		// Permissionの初期値が定義されていて、ユーザが設定を定義していない、あるいはデータを消した場合には初期値を代入（2011.08.25: 1.0.11）
		if(!isset($settings['wp_dsfaq_plus_editor_permission']) || empty($settings['wp_dsfaq_plus_editor_permission']))
			$settings['wp_dsfaq_plus_editor_permission'] = WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY;
		if(!isset($settings['wp_dsfaq_plus_admin_permission']) || empty($settings['wp_dsfaq_plus_admin_permission']))
			$settings['wp_dsfaq_plus_admin_permission'] = WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY;

        // 初期化（管理権限がない場合には閲覧のみ）
/*
        $limit = '';
        if( ! current_user_can(WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY) )
        	$limit = ' disabled';
*/
		// 現ユーザの権限確認と更新
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;

		if(isset($_POST["wdfp-form"]) && $_POST["wdfp-form"]){
		    // CSRF Check
			if(check_admin_referer("wdfp-nonce-key", "wdfp-form")){
				if ( isset( $_POST['posted'] ) && 'Y' === sanitize_text_field( wp_unslash( $_POST['posted'] ) ) && $this->settings_admin_permission ){
				   // Save the settings.
				   if(!isset($_POST['wp_dsfaq_plus_general_display_title'])) $settings['wp_dsfaq_plus_general_display_title'] = 0;
				   else $settings['wp_dsfaq_plus_general_display_title'] = absint( wp_unslash( $_POST['wp_dsfaq_plus_general_display_title'] ) );

				   if(!isset($_POST['wp_dsfaq_plus_enable_ratings'])) $settings['wp_dsfaq_plus_enable_ratings'] = 0;
				   else $settings['wp_dsfaq_plus_enable_ratings'] 	= absint( wp_unslash( $_POST['wp_dsfaq_plus_enable_ratings'] ) );

				   if(!isset($_POST['wp_dsfaq_plus_editor_permission'])) $settings['wp_dsfaq_plus_editor_permission'] = "";
				   else{
				     $settings['wp_dsfaq_plus_editor_permission'] = str_replace("\\", "", sanitize_text_field( wp_unslash( $_POST['wp_dsfaq_plus_editor_permission'] ) ) );
				     $settings['wp_dsfaq_plus_editor_permission'] = str_replace("\"", "", $settings['wp_dsfaq_plus_editor_permission']);
				     $settings['wp_dsfaq_plus_editor_permission'] = str_replace("¥", "", $settings['wp_dsfaq_plus_editor_permission']);
				     $settings['wp_dsfaq_plus_editor_permission'] = str_replace("'", "", $settings['wp_dsfaq_plus_editor_permission']);
				     $settings['wp_dsfaq_plus_editor_permission'] = stripslashes($settings['wp_dsfaq_plus_editor_permission']);
				   }
				   if(!isset($_POST['wp_dsfaq_plus_admin_permission'])) $settings['wp_dsfaq_plus_admin_permission'] = "";
				   else{
				     $settings['wp_dsfaq_plus_admin_permission'] = str_replace("\\", "", sanitize_text_field( wp_unslash( $_POST['wp_dsfaq_plus_admin_permission'] ) ) );
				     $settings['wp_dsfaq_plus_admin_permission'] = str_replace("\"", "", $settings['wp_dsfaq_plus_admin_permission']);
				     $settings['wp_dsfaq_plus_admin_permission'] = str_replace("¥", "", $settings['wp_dsfaq_plus_admin_permission']);
				     $settings['wp_dsfaq_plus_admin_permission'] = str_replace("'", "", $settings['wp_dsfaq_plus_admin_permission']);
				     $settings['wp_dsfaq_plus_admin_permission'] = stripslashes($settings['wp_dsfaq_plus_admin_permission']);
				   }

				   // 2011.08.26 (1.0.11): データが存在しない場合には「0」を入れておくこと
				   if(!isset($_POST['wp_dsfaq_plus_disable_all_delete']))  $settings['wp_dsfaq_plus_disable_all_delete'] = 0;
				   else $settings['wp_dsfaq_plus_disable_all_delete'] = absint( wp_unslash( $_POST['wp_dsfaq_plus_disable_all_delete'] ) );

				   if(!isset($_POST['wp_dsfaq_plus_disable_category_delete'])) $settings['wp_dsfaq_plus_disable_category_delete'] = 0;
				   else $settings['wp_dsfaq_plus_disable_category_delete'] = absint( wp_unslash( $_POST['wp_dsfaq_plus_disable_category_delete'] ) );

				   if(!isset($_POST['wp_dsfaq_plus_disable_edit_delete']) ) $settings['wp_dsfaq_plus_disable_edit_delete'] = 0;
				   else $settings['wp_dsfaq_plus_disable_edit_delete'] = absint( wp_unslash( $_POST['wp_dsfaq_plus_disable_edit_delete'] ) );

				   if(!isset($_POST['wp_dsfaq_plus_disable_frontedit_delete'])) $settings['wp_dsfaq_plus_disable_frontedit_delete'] = 0;
				   else $settings['wp_dsfaq_plus_disable_frontedit_delete'] = absint( wp_unslash( $_POST['wp_dsfaq_plus_disable_frontedit_delete'] ) );

				   if(!isset($_POST['wp_dsfaq_plus_apply_safetyoptions_to_admin'])) $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin'] = 0;
				   else $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin'] = absint( wp_unslash( $_POST['wp_dsfaq_plus_apply_safetyoptions_to_admin'] ) );

				update_option('wp_ds_faq_array', $settings);
			}
		}
?>
<div class="dsfaq_plus_admin_setting_page_updated"><p><strong><?php _e('Updated', 'wp-ds-faq-plus'); ?></strong></p></div>
<?php
		}

		// 設定画面表示（Display of Setting page)
?>
<?php
		// ヘッダとカスケード（CSS）設定が保存や復元できなくなっていた件の修正 1.0.16: 2013.01.10
		// 1.0.11からの問題で下記のJavaScriptが抜けていた。
        // use JavaScript SACK library for Ajax
//       wp_print_scripts( array( 'sack' ));
       wp_enqueue_scripts( 'sack' ); // 2016.05.04

?>
        <script>
        //<![CDATA[
        function dsfaq_save_settings(){
            var dsfaq_h1    = document.getElementById("dsfaq_h1").value;
            var dsfaq_h2    = document.getElementById("dsfaq_h2").value;
            var dsfaq_css   = document.getElementById("dsfaq_css").value;
            var dsfaq_copyr = document.getElementById("dsfaq_copyr").checked;
            document.getElementById("dsfaq_progress").innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'save_settings' );
            mysack.setVar( 'dsfaq_h1', dsfaq_h1 );
            mysack.setVar( 'dsfaq_h2', dsfaq_h2 );
            mysack.setVar( 'dsfaq_css', dsfaq_css );
            mysack.setVar( 'dsfaq_copyr', dsfaq_copyr );
            mysack.onError = function() { alert('Ajax error. [Error id: 8]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_restore_settings(){
            document.getElementById("dsfaq_progress").innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'restore_settings' );
            mysack.onError = function() { alert('Ajax error. [Error id: 9]' )};
            mysack.runAJAX();
            return true;
        }
        //]]>
        </script>


<div class="wrap">
   <div id="wp_ds_faq_plus_admin_menu">
	<h2><?php _e('WP DS FAQ Plus Admin Settings', 'wp-ds-faq-plus'); ?></h2>
	<form method="post" action="">
	<?php // for CSRF (Cross-Site Request Forgery): https://propansystem.net/blog/2018/02/20/post-6279/
		wp_nonce_field("wdfp-nonce-key", "wdfp-form"); ?>
		<input type="hidden" name="posted" value="Y" />

     <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
		<legend><h3><?php _e('Note','wp-ds-faq-plus'); ?></h3></legend>
			<ul>
			  <?php if(! $this->settings_admin_permission){ ?>
				<li><?php _e('* You don\'t have the permissions for changing the settings.','wp-ds-faq-plus'); ?></li>
			  <?php }else{ ?>
				<li><?php _e('* You have all permissions for changing the settings.','wp-ds-faq-plus'); ?></li>
			  <?php } ?>
				<li><?php _e('* wp-ds-faq-plus.css: The layout and design for FAQ pages.','wp-ds-faq-plus'); ?></li>
				<li><?php _e('* wp-ds-adminfaq.css: The layout and design for admin pages.','wp-ds-faq-plus'); ?></li>

			</ul>

    </fieldset>
    <?php // 2011.09.07 (1.0.13) Explanation for shortcode ?>
    <br>
     <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
		<legend><h3><?php _e('How to use Shortcode','wp-ds-faq-plus'); ?></h3></legend><br />
            <p><?php _e('First of all, the format for WP DS FAQ plugin can be used.','wp-ds-faq-plus'); ?></p>
			<ul>
				<li>[dsfaq id="number"]<br>
				<ul>
					<li><?php _e('This is the simple. About id number, please see FAQ menu.','wp-ds-faq-plus'); ?></li>
				</ul></li>
				<li>[dsfaq id="number" visible="on/off"]<br>
				<ul>
					<li><?php _e('In case that [visible] setting in shortcode is [on] and [visible] setting in FAQ category is [Not publish], when the user with the permission of editor or administrator in the FAQ login to Wordpress, the FAQ data is displayed.','wp-ds-faq-plus'); ?></li>
					<li><?php _e('Maybe, in case that there is one or more private FAQ categories and you want to use [latest] option, this option will work effectively.','wp-ds-faq-plus'); ?></li>
				</ul></li>
				<li>[dsfaq latest="number" orderbydesc="on/off" latest_format="li/dl/table"]<br>
				<ul>
					<li><?php _e('default value: latest=5, orderbydesc=off, visible=on, latest_format=li','wp-ds-faq-plus'); ?></li>
					<li><?php _e('[latest] value means the number of latest items (last modified) in all FAQ data.','wp-ds-faq-plus'); ?></li>
					<li><?php _e('[orderbydesc] value means on=Descending off=Ascending (default).','wp-ds-faq-plus'); ?></li>
					<li><?php _e('[latest_format] value can be changed the display settings. Please see wp-ds-faq-plus.css file if you would like to change the layout and design.','wp-ds-faq-plus'); ?></li>
					<li><?php _e('* In case of setting up of [not publish] in a FAQ category, the data in the FAQ category does not display, but when you login with the permission of editor or administrator, the FAQ will display at gray color. Concretely, administrator want to see all latest information, but the private or local information does not want to display.','wp-ds-faq-plus'); ?></li>
					
				</ul></li>
			</ul>

    </fieldset>
    <br>
     <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
        <legend><h3><?php _e('General Settings', 'wp-ds-faq-plus');  ?></h3></legend>
		    <h4><?php _e('Display Title','wp-ds-faq-plus'); ?> <input type="checkbox" name="wp_dsfaq_plus_general_display_title" id="wp_dsfaq_plus_general_display_title" value="1"<?php if(isset($settings['wp_dsfaq_plus_general_display_title']) && $settings['wp_dsfaq_plus_general_display_title'] ) echo ' checked';?><?php if( ! $this->settings_admin_permission ) echo ' disabled'; ?> /> </h4>
			<ul>
				<li><?php _e('In case of using more than 2 FAQ shortcodes in same page, this option will be only effective.','wp-ds-faq-plus'); ?></li>
		  	</ul>
		  	
			<?php if($this->settings_admin_permission){ ?>
				<p class="dsfaq_drv">
					<input type="submit" name="Submit" value="<?php _e('Save Settings', 'wp-ds-faq-plus'); ?>" class="button" />
				</p>
			<?php      } ?>
	<br>
    </fieldset>
    <br>
    
     <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
		<legend><h3><?php _e('Permissions','wp-ds-faq-plus'); ?></h3></legend>
			<h4><?php _e('FAQ Administrative Permission', 'wp-ds-faq-plus'); ?> <input type="text" name="wp_dsfaq_plus_admin_permission" id="wp_dsfaq_plus_admin_permission" class="regular-text code"  value="<?php if(empty($settings['wp_dsfaq_plus_admin_permission'])) echo WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY; else echo esc_attr($settings['wp_dsfaq_plus_admin_permission']);?>" <?php if( ! $this->settings_admin_permission) echo ' disabled'; ?>/>
			<?php if(defined('WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY')) echo ' (' . __('Default value: ', 'wp-ds-faq-plus') . WP_DSFAQ_PLUS_ADMIN_CONTROL_CAPABILITY . ')'; ?>
			<?php if(!preg_match("/^[a-zA-Z0-9_]+$/", esc_attr($settings['wp_dsfaq_plus_admin_permission']))) echo '<br><span style="color:red;">' . __('Attention! Default value was forcibly applied because of including an Illegal character.', 'wp-ds-faq-plus') . '</span>'; ?>
			</h4>
			<h4><?php _e('FAQ Editing Permission', 'wp-ds-faq-plus'); ?> <input type="text" name="wp_dsfaq_plus_editor_permission" id="wp_dsfaq_plus_editor_permission" class="regular-text code" value="<?php if(empty($settings['wp_dsfaq_plus_editor_permission'])) echo WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY; else echo esc_attr($settings['wp_dsfaq_plus_editor_permission']);?>"  <?php if( ! $this->settings_admin_permission) echo ' disabled'; ?>/>
			<?php if(defined('WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY')) echo ' (' . __('Default value: ', 'wp-ds-faq-plus') . WP_DSFAQ_PLUS_ADMIN_EDIT_CAPABILITY . ')'; ?>
			<?php if(!preg_match("/^[a-zA-Z0-9_]+$/", esc_attr($settings['wp_dsfaq_plus_editor_permission'])))  echo '<br><span style="color:red;">' . __('Attention! Default value was forcibly applied because of including an Illegal character.', 'wp-ds-faq-plus') . '</span>';  ?>
			</h4>
			<ul>
				<li><?php _e('Alphameric characters and underline character are only allowed in the settings. If the special characters are used, the Default value of settings are forcibly used.', 'wp-ds-faq-plus');?></li>
				<li><?php _e('FAQ Editing permission (Default is for Editors) can post, change and delete the FAQ data in FAQ category, but cannot create, change, and delete FAQ category. And cannot access to the admin menu. The delete function is inflenced the settings of [Safety Precaution].', 'wp-ds-faq-plus');?></li>
				<li><?php _e('FAQ Administrative permission (Default is for Plugin Administrators) can do all operations, such as post, change, and delete. And can access to admin menu', 'wp-ds-faq-plus');?></li>
				<li><?php _e('In any cases, Administators (level_10) can access with full permission without the settings if Safety Precation.', 'wp-ds-faq-plus');?></li>
				<li><?php _e('If you set up [level_7] to the value of Administrative permission and set up [edit_posts] to the value of Editing permission, Administrators and Editors can have all permission in FAQ and Contributors can post, change and delete the FAQ data in FAQ category. Of course, by setting up [Safety Precaution], you can put restrictions.', 'wp-ds-faq-plus');?></li>
				<li><?php _e('About the detail information of permissions, please see ', 'wp-ds-faq-plus');?><a href="<?php _e('http://codex.wordpress.org/Roles_and_Capabilities','wp-ds-faq-plus');?>" target="_blank"><?php _e('Roles and Capabilities in Wordpress.org','wp-ds-faq-plus');?></a></li>
			</ul>

			<?php if($this->settings_admin_permission){ ?>
				<p class="dsfaq_drv">
					<input type="submit" name="Submit" value="<?php _e('Save Settings', 'wp-ds-faq-plus'); ?>" class="button" />
				</p>
			<?php      } ?>
	<br>
    </fieldset>
    <br>
    
     <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
        <legend><h3><?php _e('Safety Precaution','wp-ds-faq-plus'); ?></h3></legend>	
			<p><?php _e('In any cases without Administrators (level_10), these options are applied.', 'wp-ds-faq-plus');?></p>
			<h4><input type="checkbox" name="wp_dsfaq_plus_disable_all_delete" id="wp_dsfaq_plus_disable_all_delete" value="1"<?php if(isset($settings['wp_dsfaq_plus_disable_all_delete']) && $settings['wp_dsfaq_plus_disable_all_delete'] ) echo ' checked';?> <?php if( ! $this->settings_admin_permission) echo ' disabled'; ?> />
		    <?php _e('Disable all Delete function','wp-ds-faq-plus'); ?></h4>
			<h4><input type="checkbox" name="wp_dsfaq_plus_disable_category_delete" id="wp_dsfaq_plus_disable_category_delete" value="1"<?php if(isset($settings['wp_dsfaq_plus_disable_category_delete']) && $settings['wp_dsfaq_plus_disable_category_delete'] ) echo ' checked' ;?><?php if( ! $this->settings_admin_permission ) echo ' disabled'; ?> /> 
			    <?php _e('(Recommend) Disable Delete function for FAQ category','wp-ds-faq-plus'); ?></h4>
			<h4><input type="checkbox" name="wp_dsfaq_plus_disable_edit_delete" id="wp_dsfaq_plus_disable_edit_delete" value="1"<?php if(isset($settings['wp_dsfaq_plus_disable_edit_delete']) && $settings['wp_dsfaq_plus_disable_edit_delete'] ) echo ' checked';?><?php if( ! $this->settings_admin_permission ) echo ' disabled'; ?> />
			    <?php _e('Disable Delete function in Edit list','wp-ds-faq-plus'); ?> </h4>
			<h4><input type="checkbox" name="wp_dsfaq_plus_disable_frontedit_delete" id="wp_dsfaq_plus_disable_frontedit_delete" value="1"<?php if(isset($settings['wp_dsfaq_plus_disable_frontedit_delete']) && $settings['wp_dsfaq_plus_disable_frontedit_delete'] ) echo ' checked';?><?php if( ! $this->settings_admin_permission ) echo ' disabled'; ?> />
			    <?php _e('(Recommend) Disable Delete function in Front Editer.','wp-ds-faq-plus'); ?> </h4>

			<ul>
				<li><?php _e('If you want to apply these options to Administrators, please check the following setting.', 'wp-ds-faq-plus');?></li>
			</ul>

			<h4><input type="checkbox" name="wp_dsfaq_plus_apply_safetyoptions_to_admin" id="wp_dsfaq_plus_apply_safetyoptions_to_admin" value="1"<?php if(isset($settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']) && $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin'] ) echo ' checked';?><?php if( ! $this->settings_admin_permission ) echo ' disabled'; ?> />
			    <?php _e('(Recommend) Apply these options to Administrators, too','wp-ds-faq-plus'); ?> </h4>

			<ul>
				<li><?php _e('Normally, I recommend to disable the delete function because of prevention from unexpected data lost. Especially, there is heavy risk about the delete function for the category.', 'wp-ds-faq-plus');?></li>
			</ul>
			
			<?php if($this->settings_admin_permission){ ?>
				<p class="dsfaq_drv">
					<input type="submit" name="Submit" value="<?php _e('Save Settings', 'wp-ds-faq-plus'); ?>" class="button" />
				</p>
			<?php      } ?>
	<br>
    </fieldset>
    <br>
			
     <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
        <legend><h3><?php _e('Linkage from other plugins', 'wp-ds-faq-plus');  ?></h3></legend>
			<h4><input type="checkbox" name="wp_dsfaq_plus_enable_ratings" id="wp_dsfaq_plus_enable_ratings" value="1"<?php if(isset($settings['wp_dsfaq_plus_enable_ratings']) && $settings['wp_dsfaq_plus_enable_ratings'] ) echo ' checked';?><?php if( !function_exists('the_ratings') || ! $this->settings_admin_permission ) echo ' disabled'; ?> /><?php if(!function_exists('the_ratings') ) echo ' (' . __('Please install or activate WP-PostRatings plugin.', 'wp-ds-faq-plus') . ')'; ?> 
			    <?php _e('Enable Ratings Display.','wp-ds-faq-plus'); ?> <?php if(function_exists('the_ratings')) _e('(WP-PostRatings plugin is already activated.)','wp-ds-faq-plus');?></h4>
			<ul>
				<li><?php _e('First, please install and activate WP-PostRatings plugin.','wp-ds-faq-plus'); ?></li>
		  		<li><?php _e('By enabling this option, the rating button in WP-PostRatings plugin can be displayed in each FAQ messages.','wp-ds-faq-plus');?></li>
		  	</ul>
		  	
			<?php if($this->settings_admin_permission){ ?>
				<p class="dsfaq_drv">
					<input type="submit" name="Submit" value="<?php _e('Save Settings', 'wp-ds-faq-plus'); ?>" class="button" />
				</p>
			<?php      } ?>
	<br>
    </fieldset>

	</form>
	
	<br/>

    <?php // 2011.08.25 (1.0.11) Movement of header and CSS setting menu ?>
    <fieldset style="border:1px solid #777777; width: 695px; padding-left: 6px;">
        <legend><h3><?php _e('Header and CSS Settings','wp-ds-faq-plus'); ?></h3></legend>
        <p><input id="dsfaq_h1" type="text" value="<?php if (isset($settings['wp_ds_faq_h1'])){echo $settings['wp_ds_faq_h1'];}; ?>" <?php if(! $this->settings_admin_permission) echo ' disabled'; ?>/> <?php _e('Text before the FAQ book name.', 'wp-ds-faq-plus') ?></p>
        <p><input id="dsfaq_h2" type="text" value="<?php if (isset($settings['wp_ds_faq_h2'])){echo $settings['wp_ds_faq_h2'];}; ?>" <?php if(! $this->settings_admin_permission) echo ' disabled'; ?>/> <?php _e('Text after the FAQ book name.', 'wp-ds-faq-plus') ?></p>
        <p>CSS</p>
        <textarea id="dsfaq_css" rows="10" cols="45" <?php if(! $this->settings_admin_permission) echo ' disabled'; ?>><?php if (isset($settings['wp_ds_faq_css'])){echo stripslashes($settings['wp_ds_faq_css']);}; ?></textarea>
        <p><input id="dsfaq_copyr" type="checkbox" name="copyright"<?php if ($settings['wp_ds_faq_showcopyright'] == true){echo " checked";}; ?> <?php if(! $this->settings_admin_permission) echo ' disabled'; ?>/> <?php _e('Show a link to the plugin in the end of the page.', 'wp-ds-faq-plus') ?></p>
        <p class="dsfaq_drv"><img src="<?php echo $this->plugurl; ?>img/1x1.gif" width="1" height="16"><span id="dsfaq_progress"></span> &nbsp; <a href="#_" onclick="dsfaq_restore_settings();" class="button" <?php if(! $this->settings_admin_permission) echo ' disabled'; ?>><?php _e('Restore settings', 'wp-ds-faq-plus') ?></a> &nbsp; <a href="#_" onclick="dsfaq_save_settings();" class="button" <?php if(! $this->settings_admin_permission) echo ' disabled'; ?>><?php _e('Save Settings', 'wp-ds-faq-plus') ?></a></p>
        <br>
    </fieldset>
  </div>
</div>
<?php
    }

    ##############################################################
    # options_page()                                             #
    #  Страница настроек плагина                                 #
    ##############################################################------------------------------------------------------------#
    function options_page(){
        global $wpdb;

		// 2011.08.24 (1.0.11): Admin Settings
    	if(isset($_GET['admin_page']) || (isset($_GET['page']) && $_GET['page'] === 'wp-ds-faq-plus-admin')){
    		$this->admin_settings_page();
    		return;
		}

		// 2011.08.25 (1.0.11): Get settings
        $settings = get_option('wp_ds_faq_array');
			// 現ユーザの権限確認
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;
			     
        // use JavaScript SACK library for Ajax
        wp_print_scripts( array( 'sack' ));
        
?>
        <script>
        //<![CDATA[
        function dsfaq_add_input(){
            var inputText = document.getElementById("name_faq").value;
            document.getElementById("s1").innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'add_faq' );
            mysack.setVar( 'input_faq', inputText );
            mysack.onError = function() { alert('Ajax error. [Error id: 1]' )};
            mysack.runAJAX();
            return true;
        }
        function delete_faqbook(id){
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'delete_faqbook' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 2]' )};
            mysack.runAJAX();
            return true;
        }
        function add_input_quest(id,numid){
            document.getElementById(id).style.backgroundColor = '#fdfdef';
            document.getElementById(id).innerHTML =  '<p><?php _e('Question:', 'wp-ds-faq-plus') ?></p>';
            document.getElementById(id).innerHTML += '<input id="dsfaq_quest" type="text" value="" />';
            document.getElementById(id).innerHTML += '<p><?php _e('Answer:', 'wp-ds-faq-plus') ?></p>';
            document.getElementById(id).innerHTML += '<textarea id="dsfaq_answer" rows="10" cols="45" name="text"></textarea><br>';
            document.getElementById(id).innerHTML += '<p class="dsfaq_drv"><a href="#_" onclick="this.innerHTML=\'<img src=<?php echo $this->plugurl; ?>img/ajax-loader.gif>\'; save_quest(' + numid + ');"><span class="button"><?php _e('Save', 'wp-ds-faq-plus') ?></span></a> &nbsp; <a href="#_" onclick="cancel_quest(\'' + id + '\', \'' + numid + '\');" class="button"><?php _e('Cancel', 'wp-ds-faq-plus') ?></a></p>';
            return true;
        }
        function cancel_quest(id,numid){
            document.getElementById(id).style.backgroundColor = '#FFFFFF';
            document.getElementById(id).innerHTML =  '<a href="#_" onclick="add_input_quest(\'' + id + '\', \'' + numid + '\');" class="button"><?php _e('Add&nbsp;question', 'wp-ds-faq-plus') ?></a>';
            return true;
        }
        function save_quest(id){
            var dsfaq_quest = document.getElementById("dsfaq_quest").value;
            var dsfaq_answer = document.getElementById("dsfaq_answer").value;
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'save_quest' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'dsfaq_quest', dsfaq_quest );
            mysack.setVar( 'dsfaq_answer', dsfaq_answer );
            mysack.onError = function() { alert('Ajax error. [Error id: 3]' )};
            mysack.runAJAX();
            return true;
        }
        function delete_quest(id){
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'delete_quest' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 4]' )};
            mysack.runAJAX();
            return true;
        }
        function edit_quest(id){
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'edit_quest' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 5]' )};
            mysack.runAJAX();
            return true;
        }
        function cancel_edit(id,obj){
            idElem = document.getElementById(obj);
            idElem.parentNode.removeChild(idElem);
            document.getElementById("dsfaq_edit_link_" + id).innerHTML = '<a href="#_" onclick="this.innerHTML=\'<img src=<?php echo $this->plugurl; ?>img/ajax-loader.gif>\'; edit_quest(' + id + ');"><span class="button"><?php _e('Edit', 'wp-ds-faq-plus') ?></span></a>';
            document.getElementById("dsfaq_idquest_" + id).style.backgroundColor = '#FFFFFF';
            return true;
        }
        function update_quest(id, id_book){
            var dsfaq_quest = document.getElementById("dsfaq_quest").value;
            var dsfaq_answer = document.getElementById("dsfaq_answer").value;
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'update_quest' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'id_book', id_book );
            mysack.setVar( 'dsfaq_quest', dsfaq_quest );
            mysack.setVar( 'dsfaq_answer', dsfaq_answer );
            mysack.onError = function() { alert('Ajax error. [Error id: 6]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_q_change(to, id_book, id){
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'q_change' );
            mysack.setVar( 'to', to );
            mysack.setVar( 'id_book', id_book );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 7]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_nahStep(id){
            nahStep = function(x,id){
                          var m = parseInt(document.getElementById(id).style.marginLeft),nahStepTimeOut;
                          if(nahStepTimeOut){
                              clearTimeout(nahStepTimeOut);
                          }
                          l = (1 / (Math.pow (x, 1.25) / 20 + 5) - 0.08) * Math.sin (x/2);
                          document.getElementById(id).style.marginLeft = (m + l * 25) + 'px';
                          x++;
                          if(x < 82){
                              nahStepTimeOut = setTimeout(function() {nahStep(x, id)}, 10);
                          }else{
                              document.getElementById(id).style.marginLeft = m + 'px';
                          }
                      }
            nahStep(0,id);
            return true;
        }
        function dsfaq_bg_color(id1, id2){
            var count = 10;
            var timeout = 100;
            var hex = 205;
            var divNode1 = document.getElementById(id1);
            var divNode2 = document.getElementById(id2);
            var updataId = setInterval(function(){
                if(count > 0){
                    hex = hex + 5;
                    divNode1.style.backgroundColor = '#' + Number(hex).toString(16) + 'ff' + Number(hex).toString(16);
                    divNode2.style.backgroundColor = '#' + Number(hex).toString(16) + 'ff' + Number(hex).toString(16);
                    --count;
                }else{
                    clearInterval(updataId);
                }
            }, timeout);
            return true;
        }
        function dsfaq_save_settings(){
            var dsfaq_h1    = document.getElementById("dsfaq_h1").value;
            var dsfaq_h2    = document.getElementById("dsfaq_h2").value;
            var dsfaq_css   = document.getElementById("dsfaq_css").value;
            var dsfaq_copyr = document.getElementById("dsfaq_copyr").checked;
            document.getElementById("dsfaq_progress").innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'save_settings' );
            mysack.setVar( 'dsfaq_h1', dsfaq_h1 );
            mysack.setVar( 'dsfaq_h2', dsfaq_h2 );
            mysack.setVar( 'dsfaq_css', dsfaq_css );
            mysack.setVar( 'dsfaq_copyr', dsfaq_copyr );
            mysack.onError = function() { alert('Ajax error. [Error id: 8]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_restore_settings(){
            document.getElementById("dsfaq_progress").innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'restore_settings' );
            mysack.onError = function() { alert('Ajax error. [Error id: 9]' )};
            mysack.runAJAX();
            return true;
        }
         function dsfaq_edit_name_book(id){
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'edit_name_book' );
            mysack.setVar( 'id', id );
            mysack.onError = function() { alert('Ajax error. [Error id: 10]' )};
            mysack.runAJAX();
            return true;
        }
        function dsfaq_save_name_book(id){
            var dsfaq_name_book = document.getElementById("dsfaq_input_bookname_" + id).value;
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'save_name_book' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'name_book', dsfaq_name_book );
            mysack.onError = function() { alert('Ajax error. [Error id: 11]' )};
            mysack.runAJAX();
            return true;
        }


        function dsfaq_change_faqdisplay(id, mode){
            document.getElementById("dsfaq_display_mode_" + id).innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'change_faqdisplay' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'mode', mode );
            mysack.onError = function() { alert('Ajax error. [Error id: 12]' )};
            mysack.runAJAX();
            return true;
        }
		// 2011.07.19: 1.0.9 (Select sort type)
        function dsfaq_change_faqdisplaysort(id, sortby){
            document.getElementById("dsfaq_display_sort_" + id).innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'change_faqdisplaysort' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'sort', sortby );
            mysack.onError = function() { alert('Ajax error. [Error id: 13]' )};
            mysack.runAJAX();
            return true;        
        }
		// 2011.07.19: 1.0.9 (Select order (sort2) type)
        function dsfaq_change_faqdisplayorder(id, order){
            document.getElementById("dsfaq_display_order_" + id).innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'change_faqdisplayorder' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'order', order ); // 2011.07.19: 1.0.9: for Sort2
            mysack.onError = function() { alert('Ajax error. [Error id: 14]' )};
            mysack.runAJAX();
            return true;        
        }
		// 2011.09.07: 1.0.13 (Visible)
        function dsfaq_faqdisplay_visible(id, visible){
            document.getElementById("dsfaq_faqdisplay_visible_" + id).innerHTML = '<img src="<?php echo $this->plugurl; ?>img/ajax-loader.gif" />';
            var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' ); ?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( 'dsfaq_nonce', '<?php echo wp_create_nonce( 'dsfaq_ajax_nonce' ); ?>' );
            mysack.setVar( 'action', 'dsfaq_faqdisplay_visible' );
            mysack.setVar( 'id', id );
            mysack.setVar( 'visible', visible );
            mysack.onError = function() { alert('Ajax error. [Error id: 15]' )};
            mysack.runAJAX();
            return true;        
        }
        //]]>
        </script>
 <?php
    // 2011.04.24 (1.0.6): add_sub_idが引数として渡された場合、必要なIDのみの結果を返す
    // 2011.08.29 (1.0.12) セキュリティ対策（数値かどうかのチェックに切り替え）
    if(isset($_GET['add_sub_id']) && is_int( (int) $_GET['add_sub_id'])){
      $sid = (int) $_GET['add_sub_id'];
echo '        <div id="faqbook">';
			echo $this->get_faq_book(false, $sid); 
echo "        </div>";
    }else{
 ?>

<div class="wrap">
    <h2><?php _e('WP DS FAQ Plus Settings:', 'wp-ds-faq-plus') ?></h2>
    <br>
    <p><?php _e('Every FAQ book has its own <b>ID</b>.', 'wp-ds-faq-plus') ?></p>
    <p><?php _e('To have the book viewed on a page you need to write a key word and specify book ID (for example: <b>[dsfaq id=1]</b>).', 'wp-ds-faq-plus') ?></p>
    <?php // 2011.08.26 (1.0.11): Introduction of admin menu. ?>
    <?php echo  '<p><a href="options-general.php?page=wp-ds-faq-plus-admin" target="_blank">' . __('Admin Settings','wp-ds-faq-plus') . '</a> ' . __('was established.','wp-ds-faq-plus') ; ?>
    <?php echo  ' ' . __('For displaying the delete button, please check the settings in it.','wp-ds-faq-plus') . '</p>'; ?>
    <br>
    <p><?php _e('You can create a new FAQ book:', 'wp-ds-faq-plus') ?></p>
    <?php // 2011.08.25 (1.0.11): Limitation from settings ?>
    <input id="name_faq" type="text" value="" <?php if( ! $this->settings_admin_permission ) echo ' disabled'; ?> />
    <?php if($this->settings_admin_permission){ ?>
    <?php // Fixed bug 2011.08.30 (1.0.12-1) ?>
    <a href="#_" onclick="dsfaq_add_input();" class="button"><?php _e('Add FAQ', 'wp-ds-faq-plus') ?></a>
    <?php } ?>
    
    <span id="s1"></span>
    <br><br>
    <div id="faqbook">
        <?php echo $this->get_faq_book(); ?>
    </div>
    <br><br>
</div>
<?php } ?>
<?php        
    }
    # END options_page ###########################################------------------------------------------------------------#
/*
	// 日付変換（convert timezone）
	// 2011.09.06-07 (1.0.13): Timezone of save data is UTC, so if you want to display other timezone, please set up the date setting.
	// In case of manual setting in timezone like "UTC+9", date_i18n function in wordpress does not work, so I made this function.
	// dateformat: Default setting is year, month, day. "Time" is year, month, day, and time(H:M:S). Other is custom format ("Y-m-d"). Please input date format.
	// About date format and time format, please check the general setting in the admin setting.

*/
	function convert_timezone_date($date = false, $dateformat = false){
		// About date format, Japanese style in only Japanese language.
		if ($date === false || $date === '') {
			return "";
		}

		$timezone_string = get_option( 'timezone_string' );
		$timezone_offset = get_option( 'gmt_offset' );
		$date_format     = get_option( 'date_format' );
		$time_format     = get_option( 'time_format' );

		if ($dateformat === "TIME") {
			$date_format = $date_format . " " . $time_format;
		} else if ($dateformat !== false && $dateformat !== '') {
			$date_format = $dateformat;
		}

		if ($timezone_offset === '' || ! is_numeric($timezone_offset)) {
			$timezone_offset = 0;
		}

		try {
			/*
			 * Keep backward compatibility:
			 * this plugin stores local time in DB and should display that local time as-is.
			 * So do not convert the saved value from UTC again here.
			 */
			if ( ! empty($timezone_string) ) {
				$timezone = new DateTimeZone($timezone_string);
			} else {
				$offset = (float) $timezone_offset;
				$sign   = ($offset < 0) ? '-' : '+';
				$abs    = abs($offset);
				$hours  = floor($abs);
				$mins   = (int) round(($abs - $hours) * 60);

				$timezone = new DateTimeZone(
					sprintf('%s%02d:%02d', $sign, $hours, $mins)
				);
			}

			$dt  = new DateTime($date, $timezone);
			$d_c = $dt->format($date_format);

			// Keep old correction when timezone_string is empty.
			if ( empty($timezone_string) && ! empty($timezone_offset) && preg_match('/UTC/', $d_c) ) {
				if ( (float) $timezone_offset > 0 ) {
					$d_c = str_replace('UTC', 'UTC+' . $timezone_offset, $d_c);
				} else {
					$d_c = str_replace('UTC', 'UTC' . $timezone_offset, $d_c);
				}
			}

			return $d_c;
		} catch (Exception $e) {
			return "";
		}
	}

	// 全体から最新の○件を返すメソッド 
	// Get latest data 2011.09.06 (1.0.13)
	// 2011.09.13 (1.0.13) added $latest_format
    function get_latest_faq($latest = 5, $orderby = "ASC", $latest_format="li"){
        global $wpdb;
        $table_name = $wpdb->prefix."dsfaq_quest";

		// 2011.09.07 (1.0.13) permission 
        $settings = get_option('wp_ds_faq_array');

		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;

        
        $latest = (int) $latest;
        if($latest <= 0) $latest = 5;  
        $orderby = strtoupper($orderby); 
			$select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY `date` DESC", $table_name ), ARRAY_A );

//		$sql = "SELECT * FROM `".$table_name."` WHERE EXISTS(SELECT ".$wpdb->prefix."dsfaq_name.id,".$wpdb->prefix."dsfaq_name.visible FROM ".$wpdb->prefix."dsafaq_name WHERE ".$wpdb->prefix."dsfaq_name.visible = 1 AND ".$table_name.".id_book = ".$wpdb->prefix."dsfaq_name.id".")  ORDER BY `date` DESC LIMIT ".$latest;


			// 予め、$category[dsfaq_name[id]] = dsfaq_name[name_faq]; を作っておく
        $table_name = $wpdb->prefix."dsfaq_name";
        $c_name = $wpdb->get_results( $wpdb->prepare( "SELECT id,name_faq,visible FROM %i", $table_name ), ARRAY_A );

		$category = array();
		$visible_status = "";
		if($c_name){
			foreach($c_name as $s){
				$category[$s['id']] = wp_strip_all_tags($s['name_faq']);
				$visible = (int) $s['visible'];
				if($visible != 0) $visible = 1;
				$visible_status[$s['id']] = $visible;
			}
		}
		$results = "";		
   		if($latest_format == "li"){
   		    $l_first = '<ul>'; $l_end = '</ul>';
   			$l_head = '<li>';  $l_head_end = '</li>';  // LOOP header
   			$l_head_i = '<dl><dt>'; $l_head_i_end = '</dt>';
            $l_content = '<dd>'; $l_content_end = '</dd></dl>';
        }else if($latest_format == "dl"){
   		    $l_first = ''; $l_end = '';
   			$l_head = '<dl>';  $l_head_end = '</dl>';  // LOOP header
   			$l_head_i = '<dt>'; $l_head_i_end = '</dt>'; 
            $l_content = '<dd>'; $l_content_end = '</dd>';
        }else if($latest_format == "table"){
   		    $l_first = '<table>'; $l_end = '</table>';
   			$l_head = '<tr>';  $l_head_end = '</tr>'; // LOOP header
   			$l_head_i = '<th>'; $l_head_i_end = '</th>';
            $l_content = '<td>'; $l_content_end = '</td>';        
        }else{
   		    $l_first = '<ul>'; $l_end = '</ul>';
   			$l_head = '<li>';  $l_head_end = '</li>';  // LOOP header
   			$l_head_i = '<dl><dt>'; $l_head_i_end = '</dt>';
            $l_content = '<dd>'; $l_content_end = '</dd></dl>';
        }
 		
 		// 2011.09.1-14 (1.0.13): 出力の場合分け（非公開分はlatestにカウントせず表示）
 		$i = 0;
		if($select){
			$results = "\n" . '<div id="wp_ds_faq_plus_latest_information">' . "\n";
			if(!empty($l_first)) $results .= $l_first . "\n";
			if($latest_format == "table"){ // 2011.09.22 (1.0.14) Added item's title in the table.
			   $results .= $l_head;
			   $results .= $l_head_i . __('Date','wp-ds-faq-plus') . $l_head_i_end;
			   $results .= $l_head_i . __('Category','wp-ds-faq-plus') . $l_head_i_end;
			   $results .= $l_head_i . __('Question','wp-ds-faq-plus') . $l_head_i_end;
			   $results .= $l_head_end;
			}
           	foreach ($select as $s) {
           		$i++;
           		if($visible_status[$s['id_book']] == 0){
           			$i--;
           			if($this->settings_editor_permission){
//            			$results .= '<li><span style="color:gray;">'.$this->convert_timezone_date($s['date']).': '.$s['quest'].' (<strong>'.wp_strip_all_tags($category[$s['id_book']]).'</strong>)</span></li>';
						$results .= str_replace('>', ' class="private">', $l_head); // 非公開分はグレーに
//						$results .= $l_head;
//						$results .= '<span style="color:gray;">';
						$results .= $l_head_i . $this->convert_timezone_date($s['date']) .  $l_head_i_end;
						if($latest_format == "table")
							$results .= '<td class="category">'.wp_strip_all_tags($category[$s['id_book']]).'</td>' . $l_content;
						else
							$results .= $l_content . '<strong>'.wp_strip_all_tags($category[$s['id_book']]).'</strong> / ';
						$results .= wp_strip_all_tags($s['quest']) . $l_content_end;
//           				$results .= '</span>';
           				$results .= $l_head_end . "\n";

            		}
           		}else{
//         			$results .= '<li>'.$this->convert_timezone_date($s['date']).': '.$s['quest'].' (<strong>'.$category[$s['id_book']].'</strong>)</li>';
					$results .= $l_head;
					$results .= $l_head_i . $this->convert_timezone_date($s['date']) .  $l_head_i_end;
					if($latest_format == "table")
						$results .= '<td class="category">'.wp_strip_all_tags($category[$s['id_book']]).'</td>' . $l_content;
					else
						$results .= $l_content . '<strong>'.wp_strip_all_tags($category[$s['id_book']]).'</strong> / ';
					$results .= wp_strip_all_tags($s['quest']) . $l_content_end;
           			$results .= $l_head_end . "\n";
           		}
           		if($i >= $latest) break;
           	}           	
			if(!empty($l_end)) $results .= $l_end . "\n"; 
			
			$results .= '</div>' . "\n";
		}

		return $results;
	}
    
    ##############################################################
    # get_faq_book()                                             #
    #  Получаем книгу вопросов и ответов либо в виде html-я либо #
    #  в виде массива                                            #
    #                                                            #
    #  $flag - текст вопроса                                     #
    #  $id   - id вопроса                                        #
    #  $raw  - переключатель html / массив                       #
    ##############################################################------------------------------------------------------------#
    function get_faq_book($flag = false, $id = false, $raw = false){
        global $wpdb;
        $table_name = $wpdb->prefix."dsfaq_name";
        // 2011.09.06 (1.0.13): Security fix
		if($flag != false) $flag = k_escape($flag);
		if($id != false) $id = (int) $id;

		// 2011.08.25 (1.0.11) get options
        $settings = get_option('wp_ds_faq_array');
        // 2011.08.25 (1.0.11)  Refresh information of current user permission 
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
        if(isset($flag) and $flag != false){ $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `name_faq` = %s", $table_name, $flag ), ARRAY_A ); }
//        else                               { $sql = "SELECT * FROM `".$table_name."` ORDER BY `name_faq` ASC"; }
        else if($id)                        { $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_name, $id ), ARRAY_A ); }
			// 2011.08.26 (1.0.11): 名前順にソート
        else                               { $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY `name_faq` ASC", $table_name ), ARRAY_A ); }
//        else                               { $sql = "SELECT * FROM `".$table_name."` ORDER BY `id` ASC"; }
//        else                               { $sql = "SELECT * FROM `".$table_name."` ORDER BY `id` ASC"; }
        
        if($select){
            if($raw){return $select;}
            $results = '';
            foreach ($select as $s) {           
                $results .= '<div id="dsfaq_id_'.esc_attr($s['id']).'" class="dsfaq_curentbook"><div class="dsfaq_name_faq_book">';
                $results .= '<table border="0" width="690"><tr><td id="dsfaq_namebook_'.esc_attr($s['id']).'">';
                $results .= '<span class="dsfaq_title">'.wp_strip_all_tags($s['name_faq']).'</span>';
                $results .= '</td><td align="center" width="140" id="dsfaq_toolnamebook_'.esc_attr($s['id']).'">';
                
         		// 2011.08.25 (1.0.11)  Limitation by current user permission 
                if($this->settings_admin_permission){
                	$results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; dsfaq_edit_name_book(\''.esc_attr($s['id']).'\');"><span class="button">'.__('Change&nbsp;title', 'wp-ds-faq-plus').'</span></a>';
                }

                $results .= '</td><td width="120" align="center">';
                
       		// 2011.08.25 (1.0.11)  Limitation by current user permission 
                if($this->settings_admin_permission){
					  if(! $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin'])
		                $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; delete_faqbook(\''.esc_attr($s["id"]).'\');"><span class="button">'.__('Delete&nbsp;FAQ', 'wp-ds-faq-plus').'</span></a>';
		              else  if(! $settings['wp_dsfaq_plus_disable_all_delete'] && ! $settings['wp_dsfaq_plus_disable_category_delete'])
	                	$results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; delete_faqbook(\''.esc_attr($s["id"]).'\');"><span class="button">'.__('Delete&nbsp;FAQ', 'wp-ds-faq-plus').'</span></a>';
	                  else
	                  	$results .= '<input type="button" name="dummy_button" value="'.__('Delete&nbsp;FAQ', 'wp-ds-faq-plus').'" class="button" disabled/>';
	            }

                $results .= '</td></tr></table>';
                $results .= '</div>';
                $results .= '<div class="dsfaq_divshortcode">';
                $results .= '<fieldset style="border-top:1px solid #cccccc; width: 685px; margin-left: 5px; padding-left: 5px;">';
                $results .= '<legend class="dsfaq_shortcode">'.__('Options for this FAQ:', 'wp-ds-faq-plus').'</legend>';
                $results .= '<br><table border="0" width="690"><tr>';

				// 2011.07.19: 1.0.9 for sort
//				$mode = $s['mode'];
				$mode = $s['custom_mode']; // 2011.08.29 (1.0.12)
				if(!is_numeric($mode) || $mode < 10){ $sort = 0; $order = 0; }
				else{
				  $sort = (($mode - ($mode % 10)) / 10) % 10; 
				  if($mode >= 100)
				    $order = (( $mode - ($sort * 10) - ($mode % 10) ) / 100 ) % 100;
				  else $order = 0;
				  $mode = $mode % 10;
				} 
				// 2011.09.07 (1.0.13) for Visible
				$visible = intval($s['visible']);
				if($visible != 0) $visible = 1;
				
				//                $results .= '<td width="50%">';
//               $results .= '<td width="50%" rowspan="3">';
               $results .= '<td width="50%" rowspan="4">'; // 2011.09.07 (1.0.13)
                $results .= '<span class="dsfaq_shortcode">'.__('Short&nbsp;code:', 'wp-ds-faq-plus').'&nbsp;<b>[dsfaq&nbsp;id="'.$s['id'].'"]</b></span>';
                $results .= '</td><td width="1"><img src="'.$this->plugurl.'img/1x1.gif" width="1" height="18"></td><td width="10%" align="right">';
                $results .= '<span class="dsfaq_shortcode">'.__('Display:', 'wp-ds-faq-plus').' </span> ';
                $results .= '</td><td width="300">';
                $results .= '<span class="dsfaq_shortcode" id="dsfaq_display_mode_'.$s['id'].'">';
/*
                $results .= '<input type="radio" name="dsfaq_mode_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplay(\''.esc_attr($s['id']).'\', \'0\');" '.(($s['mode'] == 0)?"checked":"").'> '.(($s['mode'] == 0)?"<b>":"").__('deployed', 'wp-ds-faq-plus').(($s['mode'] == 0)?"</b>":"");
                $results .= ' &nbsp; ';
                $results .= '<input type="radio" name="dsfaq_mode_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplay(\''.esc_attr($s['id']).'\', \'1\');" '.(($s['mode'] == 1)?"checked":"").'> '.(($s['mode'] == 1)?"<b>":"").__('minimized', 'wp-ds-faq-plus').(($s['mode'] == 1)?"</b>":"");
*/

				// 2011.07.19: 1.0.9 for sort
				
                $results .= '<input type="radio" name="dsfaq_mode_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplay(\''.esc_attr($s['id']).'\', \'0\');" '.(($mode == 0)?"checked":"").'> '.(($mode == 0)?"<b>":"").__('deployed', 'wp-ds-faq-plus').(($mode == 0)?"</b>":"");
                $results .= '<input type="radio" name="dsfaq_mode_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplay(\''.esc_attr($s['id']).'\', \'1\');" '.(($mode == 1)?"checked":"");
                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '. (($mode == 1)?"<b>":"").__('minimized', 'wp-ds-faq-plus').(($mode == 1)?"</b>":"");

                $results .= '</span>';
                $results .= '</td>';

			    // 2011.07.19: 1.0.9: Display Sort (added) 
                $results .= '</tr><tr><td width="1"><img src="'.$this->plugurl.'img/1x1.gif" width="1" height="18"></td><td align="right">';
                $results .= '<span class="dsfaq_shortcode">'.__('Sort Key:', 'wp-ds-faq-plus').' </span> ';
                $results .= '</td><td width="300">';

                $results .= '<span class="dsfaq_shortcode" id="dsfaq_display_sort_'.esc_attr($s['id']).'">';
                $results .= '<input type="radio" name="dsfaq_sort_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplaysort(\''.esc_attr($s['id']).'\', \'0\');" '.(($sort == 0)?"checked":"");
				// 2011.08.25 (1.0.11) for Limitation by current user permission
                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results  .= '> '.(($sort == 0)?"<b>":"").__('Custom', 'wp-ds-faq-plus').(($sort == 0)?"</b>":"");
                $results .= ' &nbsp; ';
                $results .= '<input type="radio" name="dsfaq_sort_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplaysort(\''.esc_attr($s['id']).'\', \'1\');" '.(($sort == 1)?"checked":"");
				// 2011.08.25 (1.0.11) for Limitation by current user permission
                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '.(($sort == 1)?"<b>":"").__('Last modified', 'wp-ds-faq-plus').(($sort == 1)?"</b>":"");

                $results .= ' &nbsp; ';
                $results .= '<input type="radio" name="dsfaq_sort_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplaysort(\''.esc_attr($s['id']).'\', \'2\');" '.(($sort == 2)?"checked":"");
				// 2011.08.25 (1.0.11) for Limitation by current user permission
                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '.(($sort == 2)?"<b>":"").__('Answer Name', 'wp-ds-faq-plus').(($sort == 2)?"</b>":"");
                $results .= '</span>';
                $results .= '</td>';

				// 2011.07.19: 1.0.9: Sort 2:  ASC or DESC
                $results .= '</tr><tr><td width="1"><img src="'.$this->plugurl.'img/1x1.gif" width="1" height="18"></td><td align="right">';
                $results .= '<span class="dsfaq_shortcode">'.__('Order by:', 'wp-ds-faq-plus').' </span> ';
                $results .= '</td><td width="300">';

                $results .= '<span class="dsfaq_shortcode" id="dsfaq_display_order_'.esc_attr($s['id']).'">';
                $results .= '<input type="radio" name="dsfaq_order_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplayorder(\''.esc_attr($s['id']).'\', \'0\');" '.(($order == 0)?"checked":"");
				// 2011.08.25 (1.0.11) for Limitation by current user permission
                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '.(($order == 0)?"<b>":"").__('Ascending', 'wp-ds-faq-plus').(($order == 0)?"</b>":"");
                $results .= ' &nbsp; ';
                $results .= '<input type="radio" name="dsfaq_order_'.esc_attr($s['id']).'" onclick="dsfaq_change_faqdisplayorder(\''.esc_attr($s['id']).'\', \'1\');" '.(($order == 1)?"checked":"");
				// 2011.08.25 (1.0.11) for Limitation by current user permission
                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '.(($order == 1)?"<b>":"").__('Descending', 'wp-ds-faq-plus').(($order == 1)?"</b>":"");
                $results .= '</span>';
                $results .= '</td>';

				// 2011.09.07 (1.0.13): Visible or not
                $results .= '</tr><tr><td width="1"><img src="'.$this->plugurl.'img/1x1.gif" width="1" height="18"></td><td align="right">';
                $results .= '<span class="dsfaq_shortcode">'.__('Visible:', 'wp-ds-faq-plus').' </span> ';
                $results .= '</td><td width="300">';

                $results .= '<span class="dsfaq_shortcode" id="dsfaq_faqdisplay_visible_'.esc_attr($s['id']).'">';
                $results .= '<input type="radio" name="dsfaq_faqdisplay_visible_'.esc_attr($s['id']).'" onclick="dsfaq_faqdisplay_visible(\''.esc_attr($s['id']).'\', \'1\');" '.(($visible == 1)?"checked":"");

                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '.(($visible == 1)?"<b>":"").__('Publish', 'wp-ds-faq-plus').(($visible == 1)?"</b>":"");
                $results .= ' &nbsp; ';

                $results .= '<input type="radio" name="dsfaq_faqdisplay_visible_'.esc_attr($s['id']).'" onclick="dsfaq_faqdisplay_visible(\''.esc_attr($s['id']).'\', \'0\');" '.(($visible == 0)?"checked":"");

                if(! $this->settings_admin_permission)
                   $results .= ' disabled';
                $results .= '> '.(($visible == 0)?"<b>":"").__('Not publish', 'wp-ds-faq-plus').(($visible == 0)?"</b>":"");
                $results .= '</span>';
                $results .= '</td>';
                
                
                $results .= '</tr></table><br>';
                $results .= '</fieldset>';
                $results .= '</div>';
                
                // 2011.07.19: 1.0.9 for sort
//                $results .= $this->get_quest_from_faq($s['id'], false, false, false);
                $results .= '<div id="dsfaq_quest_list_'.esc_attr($s['id']).'">';
                $results .= $this->get_quest_from_faq($s['id'], false, false, false, $sort, $order);
                $results .= '</div>';
                
                $results .= '<div id="dsfaq_add_q_'.$s['id'].'" class="dsfaq_name_faq_quest_add">';
                $results .= '<a href="#_" onclick="add_input_quest(\'dsfaq_add_q_'.esc_attr($s['id']).'\',\''.esc_attr($s['id']).'\');" class="button">'.__('Add&nbsp;question', 'wp-ds-faq-plus').'</a>';
                $results .= '</div>';
                $results .= '</div>';
            }
        }else{
            $results = false;
        }
        return $results;
    }
    # END get_faq_book ###########################################------------------------------------------------------------#

    ##############################################################
    # get_quest_from_faq()                                       #
    #  Получаем конкретный вопрос либо в виде html-я либо        #
    #  в виде массива                                            #
    #                                                            #
    #  $id_book  - id книги вопросов и ответов                   #
    #  $id_quest - id вопроса                                    #
    #  $quest    - текст вопроса                                 #
    #  $raw  - переключатель html / массив                       #
    ##############################################################------------------------------------------------------------#
//	2011.07.19: 1.0.9 for sort
//    function get_quest_from_faq($id_book, $id_quest = false, $quest = false, $raw = false){
    function get_quest_from_faq($id_book, $id_quest = false, $quest = false, $raw = false, $sort = 0, $order = 0){
        global $wpdb;
        if(!isset($id_book) or $id_book == ""){
            $results = false;
            return $results;
        }

		// 2011.08.25 (1.0.11) get options
        $settings = get_option('wp_ds_faq_array');
        // 2011.08.25 (1.0.11)  Refresh information of current user permission 
		if( current_user_can('level_10') ) $this->settings_editor_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_editor_permission']) ) $this->settings_editor_permission = true;
		else	$this->settings_editor_permission = false;
		if( current_user_can('level_10') ) $this->settings_admin_permission = true;
		else if( current_user_can($settings['wp_dsfaq_plus_admin_permission']) ) $this->settings_admin_permission = true;
		else	$this->settings_admin_permission = false;


        $table_name = $wpdb->prefix."dsfaq_quest";
       if(isset($id_quest) and $id_quest != false){ $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id_book` = %d AND `id` = %d", $table_name, $id_book, $id_quest ), ARRAY_A ); }
        elseif(isset($quest) and $quest != false)  { $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id_book` = %d AND `quest` = %s", $table_name, $id_book, $quest ), ARRAY_A ); }
/*
//       else                                       { $sql = "SELECT * FROM `".$table_name."` WHERE `id_book` = .$id_book. ORDER BY `sort` ASC"; }
// `id` , `id_book` , `date` ,    `quest` ,           `answer` ,          `sort`  2011.03.18 By Kitani. 1.0.1
//       else                                       { $sql = "SELECT * FROM `".$table_name."` WHERE `id_book` = .$id_book. ORDER BY `date` DESC"; }
			// 2011.07.19: 1.0.9 for Sort
*/
       else{
        if(!is_numeric($sort) || $sort < 0) $sort_by = "sort";
        else if($sort == 1) $sort_by = "date";
        else if($sort == 2) $sort_by = "quest";
        else $sort_by = "sort"; // default

        if(!is_numeric($order) || $order < 0) $order_by = "ASC";
        else if($order == 1) $order_by = "DESC";
        else $order_by = "ASC"; // default
        
//        $sql = "SELECT * FROM `".$table_name."` WHERE `id_book` = .$id_book. ORDER BY `".$sort_by."` DESC";
        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id_book` = %d ORDER BY `{$sort_by}` {$order_by}", $table_name, $id_book ), ARRAY_A );
       }

        if($select){
            if($raw){return $select;}
            $results = '';
//            $results = $sql;
            foreach ($select as $s) {
                $results .= '<div id="dsfaq_idquest_'.esc_attr($s['id']).'" class="dsfaq_name_faq_quest" style="margin-left: 0;">';
//                $results .= '<table border="0" width="690"><tr><td width="12">';
                $results .= '<table border="0" width="690"><tr><td width="12"></td>'; // 2011.03.19

		$results .= '<td width="12">';
                $results .= '<a href="#_" onclick="dsfaq_q_change(\'up\', \''.esc_attr($s['id_book']).'\', \''.esc_attr($s['id']).'\');"><img src="'.$this->plugurl.'img/up.gif" width="8" height="8"></a>';
                $results .= '<br><img src="'.$this->plugurl.'img/1x1.gif" width="1" height="6"><br>';
                $results .= '<a href="#_" onclick="dsfaq_q_change(\'down\', \''.esc_attr($s['id_book']).'\', \''.esc_attr($s['id']).'\');"><img src="'.$this->plugurl.'img/down.gif" width="8" height="8"></a>';
                $results .= '</td>';

		// 2011.03.18 By Kitani. タイトルの先頭に（）があれば、タイトルの後ろの列に移動する（カテゴリー表示）
		// （カテゴリ表示内の-は改行）
		$s_q = wp_strip_all_tags($s['quest']);
		$s_q = str_replace('(', '（', $s_q); // 何故か半角()でのpreg_matchはうまく動作しない
		$s_q = str_replace(')', '）', $s_q);
		$s_q = str_replace('　', '  ', $s_q); // 全角スペースもltrimされるようにする（全角スペースは２つの半角スペースに置換）
		$s_q = trim ($s_q); 
		// (何か文字)何かで始まっていたら 

		$s_q_matches = "";  $s_q_type = ""; $s_q_value = $s_q;
//		if( preg_match('/^¥(.+¥).+/', $s_q) ){
		if( preg_match('/^（.+）.+/', $s_q) ){
			$s_q_value = "";
			$s_q_matches = explode('）', $s_q);
			$s_max = count($s_q_matches);
			$s_q_type = str_replace('（','',$s_q_matches[0]);
			
			$s_q_value = $s_q_matches[1];
			if ($s_max > 2){
			  array_shift($s_q_matches); // delete [0]
			  array_shift($s_q_matches); // delete[1]
			  foreach($s_q_matches as $s_q_v)
				$s_q_value .= $s_q_v . '）';
			}
 		  }
		// - は改行
		 if(preg_match('/-/', $s_q_type))
			$s_q_type = str_replace('-', '<br/>', $s_q_type);

                $results .= '<td>';
//                $results .= $s['quest'];
		// 2010.03.18
                $results .= $s_q_value . '</td><td width="80" align="center"><small>' . $s_q_type.'</small>';

//                $results .= '</td><td width="100" align="center" id="dsfaq_edit_link_'.$s['id'].'">';
                $results .= '</td><td width="60" align="center" id="dsfaq_edit_link_'.esc_attr($s['id']).'">';

                $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; edit_quest('.esc_attr($s['id']).');"><span class="button">'.__('Edit', 'wp-ds-faq-plus').'</span></a>';

		// 削除は一番右端にもっていこう
//               $results .= '</td><td width="120" align="center">';
 //               $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; delete_quest('.$s['id'].');"><span class="button">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</span></a>';

		// 2011.03.18: WordpressはUTCというタイムゾーンになっている。よって表示するときに直さねばならない
		// 2011.09.07 (1.0.13): WordpressはUTCをセットして、date_i18nでローカナイズしていることを発見。これを使う
/*				
		if (version_compare(PHP_VERSION, '5.2.0') >= 0 ){
		     $current_dt = new DateTime();  // 現在日付を取得（目的は現在のタイムゾーン設定を取得）
		     $dt = new  DateTime($s['date'], new DateTimeZone($current_dt->format('e')));
		     $dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
		     $d_c = $dt->format('Y-m-d H:i:s');
        }else  // 5.2.0以下なら変換しない（面倒なので）
		    $d_c = $s['date'];
*/
		$d_c = $this->convert_timezone_date($s['date'], "Y-m-d H:i:s T");
//		$d_c = date_i18n("Y-m-d H:i:s T", strtotime($s['date']."+9:00"));
		
		$results .= '<td width="70" align="center">'. $d_c. '</td>';

		// 消すボタンを移動 2011.03.18
               $results .= '</td><td width="100" align="center">';

			// 2011.08.25 (1.0.11) Limitation by settings
		    if( ! $settings['wp_dsfaq_plus_disable_all_delete'] && ! $settings['wp_dsfaq_plus_disable_edit_delete']){
		        $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; delete_quest('.$s['id'].');"><span class="button">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</span></a>';
		    }else if($this->settings_admin_permission && ! $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']){
		        $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$this->plugurl.'img/ajax-loader.gif>\'; delete_quest('.$s['id'].');"><span class="button">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</span></a>';
		    }else 
		    	$results .= '<input type="button" name="dummy_button" class="button" value="'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'" disabled/>';
		        
                $results .= '</td></tr></table>';
                $results .= '</div>'  ;

               if(isset($quest) and $quest != false){
                    $results .= '<div id="dsfaq_add_q_'.$id_book.'" class="dsfaq_name_faq_quest_add"><a href="#_" onclick="add_input_quest(\'dsfaq_add_q_'.esc_attr($s['id_book']).'\',\''.esc_attr($s['id_book']).'\');" class="button">'.__('Add&nbsp;question', 'wp-ds-faq-plus').'</a></div>';
                }
           }
        }else{
            $results = false;
        }
        return $results;
    }
    # END get_quest_from_faq #####################################------------------------------------------------------------#    
}


if ( ! function_exists( 'k_escape' ) ) {
function k_escape($str, $flag=false, $opt=false){
  $str = stripcslashes($str);
  $str = stripslashes($str);

  if ($flag){
    $str = esc_sql($str);
  }
  if ($opt){
    $str = str_replace("\\'","\'",$str);
  }

   return $str;
}
}

if ( ! function_exists( 'dsfaq_plus_ajax_error' ) ) {
function dsfaq_plus_ajax_error($str=""){
  if($str != "") {
    die( "alert('" . esc_js( __( $str, 'wp-ds-faq-plus' ) ) . "')" );
  } else {
    die( "alert('" . esc_js( __('Something did not work.','wp-ds-faq-plus') ) . "')" );
  }
}
}

if ( ! function_exists( 'dsfaq_plus_verify_ajax_nonce' ) ) {
function dsfaq_plus_verify_ajax_nonce( $allow_public_read = false ) {
    $nonce = isset( $_POST['dsfaq_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dsfaq_nonce'] ) ) : '';
    if ( $nonce && wp_verify_nonce( $nonce, 'dsfaq_ajax_nonce' ) ) {
        return true;
    }

    if ( $allow_public_read ) {
        return true;
    }

    dsfaq_plus_ajax_error( 'Security check failed.' );
}
}


if ( ! function_exists( 'dsfaq_plus_require_ajax_nonce' ) ) {
function dsfaq_plus_require_ajax_nonce() {
    if ( ! check_ajax_referer( 'dsfaq_ajax_nonce', 'dsfaq_nonce', false ) ) {
        dsfaq_plus_ajax_error( 'Security check failed.' );
    }
}
}

if ( ! function_exists( 'dsfaq_plus_handle_ajax_action' ) ) {
function dsfaq_plus_handle_ajax_action(){
header('Content-type: text/javascript; charset=' . get_option('blog_charset'), true);
header('Cache-control: no-cache, must-revalidate', true);

if ( ! isset( $_POST['action'] ) ) {
  dsfaq_plus_ajax_error("There is not action.");
}

$action = sanitize_key( wp_unslash( $_POST['action'] ) );
$public_read_actions = array( 'open_quest' );
dsfaq_plus_verify_ajax_nonce( in_array( $action, $public_read_actions, true ) );

global $wpdb, $dsfaq;
$escape      = "\\'\\\/\x08\x0C\n\r\x09";
$table_name  = $wpdb->prefix . "dsfaq_name";
$table_quest = $wpdb->prefix . "dsfaq_quest";
$settings = get_option('wp_ds_faq_array');

$settings_editor_permission = false;
$settings_admin_permission = false;
if ( isset( $settings['wp_dsfaq_plus_editor_permission'] ) && current_user_can( $settings['wp_dsfaq_plus_editor_permission'] ) ) $settings_editor_permission = true;
if ( current_user_can('level_10') ) $settings_editor_permission = true;
if ( isset( $settings['wp_dsfaq_plus_admin_permission'] ) && current_user_can( $settings['wp_dsfaq_plus_admin_permission'] ) ) $settings_admin_permission = true;
if ( current_user_can('level_10') ) $settings_admin_permission = true;

$read_only_actions = array( 'open_quest' );
$editor_actions = array( 'front_edit_quest', 'front_cancel_edit', 'front_update_quest', 'delete_quest' );
$admin_actions = array( 'add_faq','delete_faqbook','save_quest','edit_quest','update_quest','q_change','save_settings','edit_name_book','save_name_book','change_faqdisplay','change_faqdisplaysort','change_faqdisplayorder','dsfaq_faqdisplay_visible','restore_settings' );

if ( in_array( $action, $editor_actions, true ) && ! $settings_editor_permission ) {
    dsfaq_plus_ajax_error( 'You do not have permission.' );
}
if ( in_array( $action, $admin_actions, true ) && ! $settings_editor_permission ) {
    dsfaq_plus_ajax_error( 'You do not have permission.' );
}


switch( $action ) {

    case 'add_faq':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['input_faq']) || $_POST['input_faq'] == ""){ dsfaq_plus_ajax_error("No input a FAQ name"); }
        $input_faq = wp_strip_all_tags( wp_unslash( $_POST['input_faq'] ) );
	   
        // Надо будет избавиться от двух одинаковых обращений к БД. Хватит и одного.
        if(!$dsfaq->get_faq_book($input_faq, false, false)){
			// 2011.08.29 (1.0.12)
           $results = $wpdb->insert( $table_name, array( 'name_faq' => $input_faq, 'mode' => 0, 'custom_mode' => 0 ), array( '%s', '%d', '%d' ) );
            if($results){
                $results = $dsfaq->get_faq_book(k_escape($input_faq,true,true), false, false);

                $results = addcslashes($results, "\\'"); 
                die( "document.getElementById('faqbook').innerHTML += '$results';\n
                document.getElementById(\"s1\").innerHTML = '';" );
            }
        }else{
            die( "alert('".__('The FAQ with such name already exists!', 'wp-ds-faq-plus')."');\n
            document.getElementById(\"s1\").innerHTML = '';" );
        }
        break;

    case 'delete_faqbook':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("There is not id number"); }
//        $id = $_POST['id'];
          $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
      
        $quest_from_faq = $dsfaq->get_quest_from_faq($id, false, false, true);
        
        if($quest_from_faq != false){
            $count = count($quest_from_faq);
            $i = 0; $wr = '';
            foreach ($quest_from_faq as $s) {
                $i = $i + 1;
                if($i == $count){$or = "";}else{$or = ' OR ';}
                $wr .= "`id` = '".k_escape($s['id'],true,false)."'".$or;
            }
            $results = $wpdb->delete( $table_quest, array( 'id_book' => $id ), array( '%d' ) );
        }

//        $sql = "DELETE FROM `".$table_name."` WHERE `id` = ".$id;
        $results = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
        if($results){ die( "idElem = document.getElementById('dsfaq_id_".$id."');\n idElem.parentNode.removeChild(idElem);"); }
        break;
    
    case 'save_quest':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("There is not id number"); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)


        if(!isset($_POST['dsfaq_quest']) || $_POST['dsfaq_quest'] == "" ){ dsfaq_plus_ajax_error("POST: dsfaq_quest error. "); }
        $dsfaq_quest = wp_strip_all_tags( wp_unslash( $_POST['dsfaq_quest'] ) ); 

        if(!isset($_POST['dsfaq_answer']) || $_POST['dsfaq_answer'] == "" ){ dsfaq_plus_ajax_error("POST: dsfaq_answer error. "); }
        $dsfaq_answer = wp_kses_post( wp_unslash( $_POST['dsfaq_answer'] ) ); 
        
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id_book` = %d ORDER BY `sort` DESC LIMIT 1", $table_quest, $id ), ARRAY_A );
        if($results){
            foreach ($results as $s) {
                (int)$sortnum = $s['sort']+1;
            }
        }else{
            (int)$sortnum = 1;
        }
        
        
        $results = $wpdb->insert( $table_quest, array( 'id_book' => $id, 'date' => wp_date( 'Y-m-d H:i:s' ), 'quest' => $dsfaq_quest, 'answer' => $dsfaq_answer, 'sort' => $sortnum ), array( '%d', '%s', '%s', '%s', '%d' ) );
        
        if($results){
            $results = $dsfaq->get_quest_from_faq($id, false, k_escape($dsfaq_quest,true,true), false);

            $results = addcslashes($results, "\\'");
            die( "idElem = document.getElementById('dsfaq_add_q_".$id."');\n
            idElem.parentNode.removeChild(idElem);\n
            document.getElementById('dsfaq_id_".$id."').innerHTML += '".$results."';" );
        }        
        break;

    case 'delete_quest':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in delete_quest. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        if(isset($_POST['front'])){
//            (int)$front = $_POST['front'];
            $front = (int) wp_unslash( $_POST['front'] ); // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        }

//        $sql = "DELETE FROM `".$table_quest."` WHERE `id` = ".$id;
        $results = $wpdb->delete( $table_quest, array( 'id' => $id ), array( '%d' ) );
        
//        if(isset($front) and $front == 1){
        if(isset($front) and is_int($front) and $front == 1){ // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
            $die = "if(document.getElementById('dsfaq_qa_block_".$id."')){
                        dsfaq_front_bg_color('dsfaq_qa_block_".$id."', function (){
                            idElem = document.getElementById('dsfaq_qa_block_".$id."');
                            idElem.parentNode.removeChild(idElem);
                            idElem = document.getElementById('dsfaq_li_".$id."');
                            idElem.parentNode.removeChild(idElem); })
                    }else{
                        dsfaq_front_bg_color('dsfaq_answer_".$id."', function (){ 
                            idElem = document.getElementById('dsfaq_quest_".$id."');
                            idElem.parentNode.removeChild(idElem);
                            idElem = document.getElementById('dsfaq_tools_".$id."');
                            idElem.parentNode.removeChild(idElem);
                            idElem = document.getElementById('dsfaq_answer_".$id."');
                            idElem.parentNode.removeChild(idElem); })
                    }";
        }else{
            $die = "idElem = document.getElementById('dsfaq_idquest_".$id."');
                    idElem.parentNode.removeChild(idElem);";
        }

        if($results){ die($die); }
        break;
        
    case 'edit_quest':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in edit_quest. "); }
//        (int)$id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
       
        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_quest, $id ), ARRAY_A );
        
        $results = '';
        foreach ($select as $s) {
            $results .= '<div id="dsfaq_idquest_edit_'.$id.'" class="dsfaq_idquest_edit">';
            $results .= '<br>';
            $results .= '<p>'.__('Question:', 'wp-ds-faq-plus').'</p>';
            $results .= '<input id="dsfaq_quest" type="text" value="'.str_replace('"', '&quot;', wp_strip_all_tags($s['quest'])).'" />';
            $results .= '<p>'.__('Answer:', 'wp-ds-faq-plus').'</p>';
            $results .= '<textarea id="dsfaq_answer" rows="10" cols="45" name="text">'.wp_kses_post($s['answer']).'</textarea><br>';
            $results .= '<p class="dsfaq_drv">';
            $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; update_quest('.k_escape($s['id'],false,true).', '.esc_attr($s['id_book']).');"><span class="button">'.__('Save', 'wp-ds-faq-plus').'</span></a>';
            $results .= ' &nbsp; ';
            $results .= '<a href="#_" onclick="cancel_edit(\''.$id.'\', \'dsfaq_idquest_edit_'.$id.'\');" class="button">'.__('Cancel', 'wp-ds-faq-plus').'</a>';
            $results .= '</p>';
            $results .= '</div>';
        }
        
        $results = addcslashes($results, $escape);
        die( "document.getElementById('dsfaq_edit_link_".$id."').innerHTML = '';\n
        document.getElementById('dsfaq_idquest_".$id."').style.backgroundColor = '#fdfdef';\n
        document.getElementById('dsfaq_idquest_".$id."').innerHTML += '".$results."';" );
        break;
        
    case 'front_edit_quest':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == "" ){ dsfaq_plus_ajax_error("POST: id error in front_edit_quest. "); }
//        (int)$id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
      
        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_quest, $id ), ARRAY_A );

        foreach ($select as $s) {          
            $front_input_quest = '<input style="width: 100%;" id="dsfaq_inp_quest_'.$id.'" type="text" value="'.str_replace('"', '&quot;', wp_strip_all_tags($s['quest'])).'" />';
            $front_textarea_answer = '<textarea style="width: 100%;" id="dsfaq_txt_answer_'.$id.'" rows="10" cols="45">'.wp_kses_post($s['answer']).'</textarea>';
            $front_tools  = '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_update_quest('.k_escape($s['id'],false,true).');">'.__('Save', 'wp-ds-faq-plus').'</a> ]';
            $front_tools .= '[ <a href="#_" onclick="dsfaq_front_cancel_edit(\''.$id.'\');">'.__('Cancel', 'wp-ds-faq-plus').'</a> ]';
        }
        
        $front_input_quest     = addcslashes($front_input_quest, $escape);
        $front_textarea_answer = addcslashes($front_textarea_answer, $escape);
        $front_tools           = addcslashes($front_tools, $escape);
      
        die( "document.getElementById('dsfaq_quest_".$id."').innerHTML = '".$front_input_quest."';\n
        document.getElementById('dsfaq_answer_".$id."').innerHTML = '".$front_textarea_answer."';\n
        document.getElementById('dsfaq_tools_".$id."').innerHTML = '".$front_tools."';" );
        break;
        
    case 'front_cancel_edit':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in front_cancel_edit. "); }
//        (int)$id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
      
        $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_quest, $id ), ARRAY_A );
        
        $results = '';
        foreach ($select as $s) {
            $front_input_quest = wp_strip_all_tags($s['quest']);
            $front_textarea_answer = '<div class="dsfaq_answer">'.wp_kses_post($s['answer']);
			// 2011.08.25 (1.0.11) Limitation by settings and current user permission
//            if(current_user_can('level_10')){
			if($settings_editor_permission){
                $front_tools  = '[ <a href="#_" onclick="dsfaq_front_edit_quest('.k_escape($s['id'],false,true).');">'.__('Edit', 'wp-ds-faq-plus').'</a> ]';
//	2011.08.22: 1.0.10: ログイン時のFAQ表示ページにおいて、「編集」→「キャンセル」を押した後に「削除」になる問題を修正（管理画面以外での削除を禁止するという1.0.3のポリシーに準拠）

				// 2011.08.25 (1.0.11) Limitation by settings and current user permission
				if(! $settings['wp_dsfaq_plus_disable_all_delete'] && ! $settings['wp_dsfaq_plus_disable_frontedit_delete']){	
             	   $front_tools .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.k_escape($s['id'],false,true).');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';             	   
          		}else if($settings_admin_permission && !$settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']){
             	   $front_tools .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.k_escape($s['id'],false,true).');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';             	   
				}          		
          	}
        }
        $front_input_quest     = addcslashes($front_input_quest, $escape);
        $front_textarea_answer = addcslashes($front_textarea_answer, $escape);
        $front_tools           = addcslashes($front_tools, $escape);
        die( "if(document.getElementById('dsfaq_qa_block_".$id."')){
                  document.getElementById('dsfaq_quest_".$id."').innerHTML = '".$front_input_quest."';\n
                  document.getElementById('dsfaq_answer_".$id."').innerHTML = '".$front_textarea_answer."</div>';\n
              }else{
                  document.getElementById('dsfaq_quest_".$id."').innerHTML = '<a href=\"#_\" onclick=\"dsfaq_open_quest(".$id.");\">".$front_input_quest."</a>';\n
                  document.getElementById('dsfaq_answer_".$id."').innerHTML = '".$front_textarea_answer."<br><span class=\"dsfaq_tools\">[&nbsp;<a href=\"#_\" onclick=\"dsfaq_close_quest(".$id.");\">".__('Close', 'wp-ds-faq-plus')."</a>&nbsp;]</span></div>';\n
              }
        document.getElementById('dsfaq_tools_".$id."').innerHTML = '".$front_tools."';" );
        break;

    case 'update_quest':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in update_quest. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
        
        if(!isset($_POST['id_book']) || $_POST['id_book'] == ""){ dsfaq_plus_ajax_error("POST: id_book error in update_quest. "); }
//        $id_book = $_POST['id_book'];
        $id_book = (int) wp_unslash( $_POST['id_book'] ); if(!is_int($id_book)){ break; }  // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
        
        if(!isset($_POST['dsfaq_quest']) || $_POST['dsfaq_quest'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_quest error in update_quest. "); }
        $dsfaq_quest = wp_strip_all_tags( wp_unslash( $_POST['dsfaq_quest'] ) );

        if(!isset($_POST['dsfaq_answer']) || $_POST['dsfaq_answer'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_answer error in update_quest. "); }
        $dsfaq_answer = wp_kses_post( wp_unslash( $_POST['dsfaq_answer'] ) );
        
        $results = $wpdb->update( $table_quest, array( 'quest' => $dsfaq_quest, 'answer' => $dsfaq_answer ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );

        if($results){
            $res = $dsfaq->get_quest_from_faq(k_escape($id_book,true,true), $id, false, true);

            $results = '';
            
           foreach ($res as $s) {
                $results .= '<table border="0" width="690"><tr><td width="12">';
                $results .= '<a href="#_" onclick="dsfaq_q_change(\'up\', \''.k_escape($s['id_book'],false,true).'\', \''.k_escape($s['id'],false,true).'\');"><img src="'.$dsfaq->plugurl.'img/up.gif" width="8" height="8"></a>';
                $results .= '<br><img src="'.$dsfaq->plugurl.'img/1x1.gif" width="1" height="6"><br>';
                $results .= '<a href="#_" onclick="dsfaq_q_change(\'down\', \''.k_escape($s['id_book'],false,true).'\', \''.k_escape($s['id'],false,true).'\');"><img src="'.$dsfaq->plugurl.'img/down.gif" width="8" height="8"></a>';
                $results .= '</td>';
                $results .= '<td>';
                $results .= wp_strip_all_tags($s['quest']);
                $results .= '</td><td width="120" align="center" id="dsfaq_edit_link_'.k_escape($s['id'],false,true).'">';
                $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; edit_quest('.k_escape($s['id'],false,true).');"><span class="button">'.__('Edit', 'wp-ds-faq-plus').'</span></a>';
                $results .= '</td><td width="120" align="center">';
                $results .= '<a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; delete_quest('.k_escape($s['id'],false,true).');"><span class="button">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</span></a>';
                $results .= '</td></tr></table>';
            }

            $results = addcslashes($results, "\\'");
            die( "idElem = document.getElementById('dsfaq_idquest_edit_".$id."');\n
            idElem.parentNode.removeChild(idElem);\n
            document.getElementById('dsfaq_idquest_".$id."').style.backgroundColor = '#FFFFFF';\n
            document.getElementById('dsfaq_idquest_".$id."').innerHTML = '".$results."';" );
        }        
        
        break;
        
    case 'front_update_quest':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in front_update_quest. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
      
        if(!isset($_POST['dsfaq_quest']) || $_POST['dsfaq_quest'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_quest error in front_update_quest. "); }
        $dsfaq_quest = wp_strip_all_tags( wp_unslash( $_POST['dsfaq_quest'] ) );

        if(!isset($_POST['dsfaq_answer']) || $_POST['dsfaq_answer'] == "") dsfaq_plus_ajax_error("POST: dsfaq_answer error in front_update_quest. ");
        $dsfaq_answer = wp_kses_post( wp_unslash( $_POST['dsfaq_answer'] ) );
        
        $results = $wpdb->update( $table_quest, array( 'quest' => $dsfaq_quest, 'answer' => $dsfaq_answer ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
        
        if($results){
            $select = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_quest, $id ), ARRAY_A );
            
            $results = '';
            foreach ($select as $s) {
                $front_input_quest = wp_strip_all_tags($s['quest']);
                $front_textarea_answer = '<div class="dsfaq_answer">'.wp_kses_post($s['answer']);
			// 2011.08.25 (1.0.11) Limitation by settings and current user permission
//            if(current_user_can('level_10')){
				if($settings_editor_permission){
                    $front_tools  = '[ <a href="#_" onclick="dsfaq_front_edit_quest('.k_escape($s['id'],false,true).');">'.__('Edit', 'wp-ds-faq-plus').'</a> ]';
				// 2011.08.25 (1.0.11) Limitation by settings and current user permission
					if(! $settings['wp_dsfaq_plus_disable_all_delete'] && ! $settings['wp_dsfaq_plus_disable_frontedit_delete']){
                   		$front_tools .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.k_escape($s['id'],false,true).');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';
               		}else if($settings_admin_permission && ! $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']){
                   		$front_tools .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.k_escape($s['id'],false,true).');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';
               		}
               	}
            }
        
            $front_input_quest     = addcslashes($front_input_quest, $escape);
            $front_textarea_answer = addcslashes($front_textarea_answer, $escape);
            $front_tools           = addcslashes($front_tools, $escape);
            die( "if(document.getElementById('dsfaq_qa_block_".$id."')){
                  document.getElementById('dsfaq_quest_".$id."').innerHTML = '".$front_input_quest."';\n
                  document.getElementById('dsfaq_answer_".$id."').innerHTML = '".$front_textarea_answer."</div>';\n
                  document.getElementById('dsfaq_li_".$id."').innerHTML = '<a href=\"#".$id."\">".$front_input_quest."</a>';
           }else{
                  document.getElementById('dsfaq_quest_".$id."').innerHTML = '<a href=\"#_\" onclick=\"dsfaq_open_quest(".$id.");\">".$front_input_quest."</a>';\n
                  document.getElementById('dsfaq_answer_".$id."').innerHTML = '".$front_textarea_answer."<br><span class=\"dsfaq_tools\">[&nbsp;<a href=\"#_\" onclick=\"dsfaq_close_quest(".$id.");\">".__('Close', 'wp-ds-faq-plus')."</a>&nbsp;]</span></div>';\n
              }
            document.getElementById('dsfaq_tools_".$id."').innerHTML = '".$front_tools."';" );
        }        
        
        break;
    
    case 'q_change':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['to']) || $_POST['to'] == ""){ dsfaq_plus_ajax_error("POST: to error in q_change. "); }
        $to = sanitize_key( wp_unslash( $_POST['to'] ) );
        if(!isset($_POST['id_book']) || $_POST['id_book'] == ""){ dsfaq_plus_ajax_error("POST: id_book error in q_change. "); }
//      $id_book = $_POST['id_book'];
        $id_book = (int) wp_unslash( $_POST['id_book'] ); if(!is_int($id_book)){ break; }  // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in q_change. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
        
        $sort = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `sort` FROM %i WHERE `id` = %d", $table_quest, $id ) );
        
        if($to == "up"){  $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id_book` = %d AND `sort` < %d ORDER BY `sort` DESC LIMIT 1", $table_quest, $id_book, $sort ), ARRAY_A );}
        if($to == "down"){$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `id_book` = %d AND `sort` > %d ORDER BY `sort` ASC LIMIT 1", $table_quest, $id_book, $sort ), ARRAY_A );}
        
        if($results){
            $q_id_curent   = $id;
            $q_sort_curent = $sort;
            $q_id_change   = (int) $results['0']['id'];
            $q_sort_change = (int) $results['0']['sort'];
            
            $results = $wpdb->update( $table_quest, array( 'sort' => $q_sort_change ), array( 'id' => $q_id_curent ), array( '%d' ), array( '%d' ) );
            $results = $wpdb->update( $table_quest, array( 'sort' => $q_sort_curent ), array( 'id' => $q_id_change ), array( '%d' ), array( '%d' ) );
            
            if($to == 'up'){
                die( "da = document.getElementById('dsfaq_idquest_".$q_id_curent."');\n
                db = document.getElementById('dsfaq_idquest_".$q_id_change."');\n
                da.parentNode.insertBefore(da, db);\n
                dsfaq_bg_color ('dsfaq_idquest_".$q_id_curent."', 'dsfaq_idquest_".$q_id_change."');" );
            }
            else if($to == 'down'){
                die( "da = document.getElementById('dsfaq_idquest_".$q_id_curent."');\n
                db = document.getElementById('dsfaq_idquest_".$q_id_change."');\n
                db.parentNode.insertBefore(db, da);\n
                dsfaq_bg_color ('dsfaq_idquest_".$q_id_curent."', 'dsfaq_idquest_".$q_id_change."');" );
            }
            
        }else{
            die("dsfaq_nahStep('dsfaq_idquest_".$id."');");
        }
        break;
        
    case 'save_settings':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['dsfaq_h1']) || $_POST['dsfaq_h1'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_h1 error in save_settings. "); }
        $dsfaq_h1 = sanitize_text_field( wp_unslash( $_POST['dsfaq_h1'] ) );
        
        if(!isset($_POST['dsfaq_h2']) || $_POST['dsfaq_h2'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_h2 error in save_settings. "); }
        $dsfaq_h2 = sanitize_text_field( wp_unslash( $_POST['dsfaq_h2'] ) ); 
        
        if(!isset($_POST['dsfaq_css']) || $_POST['dsfaq_css'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_css error in save_settings. "); }
        $dsfaq_css = wp_kses_post( wp_unslash( $_POST['dsfaq_css'] ) );
        
        if(!isset($_POST['dsfaq_copyr']) || $_POST['dsfaq_copyr'] == ""){ dsfaq_plus_ajax_error("POST: dsfaq_copyr error in save_settings. "); }
        $dsfaq_copyr = sanitize_text_field( wp_unslash( $_POST['dsfaq_copyr'] ) );
        
        if($dsfaq_copyr == 'true'){
            $wp_ds_faq_array['wp_ds_faq_showcopyright'] = true;
        }else{
            $wp_ds_faq_array['wp_ds_faq_showcopyright'] = false;
        }
        $wp_ds_faq_array['wp_ds_faq_h1']  = $dsfaq_h1;
        $wp_ds_faq_array['wp_ds_faq_h2']  = $dsfaq_h2;

        $wp_ds_faq_array['wp_ds_faq_css'] = $dsfaq_css;
        
        update_option('wp_ds_faq_array', $wp_ds_faq_array);

        die("document.getElementById('dsfaq_progress').innerHTML = '<span style=\'color:green;\'>".__('Settings&nbsp;have&nbsp;been&nbsp;saved:', 'wp-ds-faq-plus')."&nbsp;".wp_date("Y-m-d H:i:s")."</span>';");
        
        break;
    
    case 'edit_name_book':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in edit_name_book. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)
        
        $select = $dsfaq->get_faq_book(false, $id, true);
        
        $name_faq = wp_strip_all_tags($select[0]['name_faq']);
        $name_faq = addcslashes($name_faq, $escape);
        $name_faq = str_replace('"', '&quot;', $name_faq);
	            
        die("document.getElementById('dsfaq_namebook_".$id."').innerHTML = '<input style=\"width:415px;\" id=\"dsfaq_input_bookname_".$id."\" value=\"".$name_faq."\"/>';\n
        document.getElementById('dsfaq_toolnamebook_".$id."').innerHTML = '<a href=\"#_\" onclick=\"this.innerHTML=\'<img src=".$dsfaq->plugurl."img/ajax-loader.gif>\'; dsfaq_save_name_book(".$id.");\"><span class=\"button\">".__('Save', 'wp-ds-faq-plus')."</span></a>';");
        
        break;
        
    case 'save_name_book':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in save_name_book. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        if(!isset($_POST['name_book']) || $_POST['name_book'] == ""){ dsfaq_plus_ajax_error("POST: name_book error in save_name_book. "); }
        $name_book = wp_strip_all_tags( wp_unslash( $_POST['name_book'] ) );
        
        $results = $wpdb->update( $table_name, array( 'name_faq' => $name_book ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
        
        die("document.getElementById('dsfaq_namebook_".$id."').innerHTML = '<span class=\"dsfaq_title\">".k_escape($name_book,false,true)."</span>';\n
        document.getElementById('dsfaq_toolnamebook_".$id."').innerHTML = '<a href=\"#_\" onclick=\"this.innerHTML=\'<img src=".$dsfaq->plugurl."img/ajax-loader.gif>\'; dsfaq_edit_name_book(".$id.");\"><span class=\"button\">".__('Change&nbsp;title', 'wp-ds-faq-plus')."</span></a>';");
        
        break;
        
    case 'change_faqdisplay':
        dsfaq_plus_require_ajax_nonce();
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in change_faqdisplay. "); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.12: 2011.08.29)

        if(!isset($_POST['mode']) || $_POST['mode'] === ""){ dsfaq_plus_ajax_error("POST: mode error in change_faqdisplay. " ); }
//        $mode = $_POST['mode'];
        $mode = (int) wp_unslash( $_POST['mode'] ); if(!is_int($mode)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        // 2011.07.19: 1.0.9 for security
        if(!is_numeric($mode) || $mode < 0) $mode = 0;      

		// 2011.07.19: 1.0.9: modeをDBから取得
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_name, $id ) );
        if(isset($result->custom_mode)) // 一つしか人しないはず。一つ目からmodeを取り出す
            $set_mode = $result->custom_mode;
        else $set_mode = 0;
        if(!is_numeric($set_mode) || $set_mode < 0) $set_mode = 0;
        unset($result); // 取得したDBデータの破棄
             
        // 2011.07.19: 1.0.9: DBのmodeからsort, orderを分離   
	    $sort = (($set_mode - ($set_mode % 10)) / 10) % 10; 
	    if($set_mode >= 100) // 2011.08.29 (1.0.12) fix
	       $order = (( $set_mode - ($sort * 10) - ($set_mode % 10) ) / 100 ) % 100;
	    else $order = 0;
              
        $mode_with_sort = $mode + $sort*10 + $order*100;

		// 2011.08.29 (1.0.12)        
        $results = $wpdb->update( $table_name, array( 'mode' => $mode, 'custom_mode' => $mode_with_sort ), array( 'id' => $id ), array( '%d', '%d' ), array( '%d' ) );

        k_escape($id, false, true);
        k_escape($mode, false, true);

        if($results){
            $results = '';
            $results .= '<input type="radio" name="dsfaq_mode_'.$id.'" onclick="dsfaq_change_faqdisplay(\''.$id.'\', \'0\');" '.(($mode == 0)?"checked":"").'> '.(($mode == 0)?"<b>":"").__('deployed', 'wp-ds-faq-plus').(($mode == 0)?"</b>":"");
            $results .= ' &nbsp; ';
            $results .= '<input type="radio" name="dsfaq_mode_'.$id.'" onclick="dsfaq_change_faqdisplay(\''.$id.'\', \'1\');" '.(($mode == 1)?"checked":"").'> '.(($mode == 1)?"<b>":"").__('minimized', 'wp-ds-faq-plus').(($mode == 1)?"</b>":"");
        }
        
        $results = addcslashes($results, $escape);
        die("document.getElementById('dsfaq_display_mode_".$id."').innerHTML = '".$results."';");
        break;
        
    case 'change_faqdisplaysort':
        dsfaq_plus_require_ajax_nonce();
// 2011.07.19: 1.0.9 for Sort

        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("There is not id number"); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        if(!isset($_POST['sort']) || $_POST['sort'] === ""){ dsfaq_plus_ajax_error("Not setting of sort"); }
        $sort = (int) wp_unslash( $_POST['sort'] );

        // 2011.07.19: 1.0.9 for security
        if(!is_numeric($sort) || $sort < 0) $sort = 0;

        // 2011.07.19: 1.0.9: modeをDBから取得
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_name, $id ) );
        if(isset($result->custom_mode)) // 一つしか人しないはず。一つ目からmodeを取り出す
            $set_mode = $result->custom_mode;
        else $set_mode = 0;
        if(!is_numeric($set_mode) || $set_mode < 0) $set_mode = 0;
        unset($result); // 取得したDBデータの破棄

        // 2011.07.19: 1.0.9: DBのmodeからmode, orderを分離
        $mode = $set_mode % 10;
        $set_sort = (($set_mode - ($set_mode % 10)) / 10) % 10;
        if($set_mode >= 100) // 2011.08.29 (1.0.12) fix
           $order = (( $set_mode - ($set_sort * 10) - $mode ) / 100 ) % 100;
        else $order = 0;

        $mode_with_sort = $mode + $sort*10 + $order*100;

        // 2011.08.29 (1.0.12)
        $results = $wpdb->update( $table_name, array( 'mode' => $mode, 'custom_mode' => $mode_with_sort ), array( 'id' => $id ), array( '%d', '%d' ), array( '%d' ) );

        k_escape($id, false, true);
        k_escape($sort, false, true);

        if($results !== false){
            $results = '';
            $results .= '<input type="radio" name="dsfaq_sort_'.$id.'" onclick="dsfaq_change_faqdisplaysort(\''.$id.'\', \'0\');" '.(($sort == 0)?"checked":"").'> '.(($sort == 0)?"<b>":"").__('Custom', 'wp-ds-faq-plus').(($sort == 0)?"</b>":"");
            $results .= ' &nbsp; ';
            $results .= '<input type="radio" name="dsfaq_sort_'.$id.'" onclick="dsfaq_change_faqdisplaysort(\''.$id.'\', \'1\');" '.(($sort == 1)?"checked":"").'> '.(($sort == 1)?"<b>":"").__('Last modified', 'wp-ds-faq-plus').(($sort == 1)?"</b>":"");
            $results .= ' &nbsp; ';
            $results .= '<input type="radio" name="dsfaq_sort_'.$id.'" onclick="dsfaq_change_faqdisplaysort(\''.$id.'\', \'2\');" '.(($sort == 2)?"checked":"").'> '.(($sort == 2)?"<b>":"").__('Answer Name', 'wp-ds-faq-plus').(($sort == 2)?"</b>":"");
        }

        $quest_results = $dsfaq->get_quest_from_faq($id, false, false, false, $sort, $order);
        $results = addcslashes($results, $escape);
        $quest_results = addcslashes($quest_results, $escape);
        die("document.getElementById('dsfaq_display_sort_".$id."').innerHTML = '".$results."';document.getElementById('dsfaq_quest_list_".$id."').innerHTML = '".$quest_results."';");
        break;

    case 'change_faqdisplayorder':
        dsfaq_plus_require_ajax_nonce();
// 2011.07.19: 1.0.9 for Sort2

        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("There is not id number"); }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        if(!isset($_POST['order']) || $_POST['order'] === ""){ dsfaq_plus_ajax_error("Not setting of order"); }
        $order = (int) wp_unslash( $_POST['order'] );

        // 2011.07.19: 1.0.9 for security
        if(!is_numeric($order) || $order < 0) $order = 0;      

		// 2011.07.19: 1.0.9: modeをDBから取得
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE `id` = %d", $table_name, $id ) );
        if(isset($result->custom_mode)) // 一つしか人しないはず。一つ目からmodeを取り出す
            $set_mode = $result->custom_mode;		
        else $set_mode = 0;
        if(!is_numeric($set_mode) || $set_mode < 0) $set_mode = 0;
        unset($result); // 取得したDBデータの破棄
     
        // 2011.07.19: 1.0.9: DBのmodeからmode, orderを分離   
	    $mode = $set_mode % 10;
	    $sort = (($set_mode - $mode) / 10) % 10; 
      
        $mode_with_sort = $mode + $sort*10 + $order*100;
		
		// 2011.0.29 (1.0.12) 
        $results = $wpdb->update( $table_name, array( 'mode' => $mode, 'custom_mode' => $mode_with_sort ), array( 'id' => $id ), array( '%d', '%d' ), array( '%d' ) );

        k_escape($id, false, true);
        k_escape($sort, false, true);
        
        if($results !== false){
            $results = '';
            $results .= '<input type="radio" name="dsfaq_order_'.$id.'" onclick="dsfaq_change_faqdisplayorder(\''.$id.'\', \'0\');" '.(($order == 0)?"checked":"").'> '.(($order == 0)?"<b>":"").__('Ascending', 'wp-ds-faq-plus').(($order == 0)?"</b>":"");
            $results .= ' &nbsp; ';
            $results .= '<input type="radio" name="dsfaq_order_'.$id.'" onclick="dsfaq_change_faqdisplayorder(\''.$id.'\', \'1\');" '.(($order == 1)?"checked":"").'> '.(($order == 1)?"<b>":"").__('Descending', 'wp-ds-faq-plus').(($order == 1)?"</b>":"");
        }

        $quest_results = $dsfaq->get_quest_from_faq($id, false, false, false, $sort, $order);
        $results = addcslashes($results, $escape);
        $quest_results = addcslashes($quest_results, $escape);
        die("document.getElementById('dsfaq_display_order_".$id."').innerHTML = '".$results."';document.getElementById('dsfaq_quest_list_".$id."').innerHTML = '".$quest_results."';");
        break;

    case 'dsfaq_faqdisplay_visible':
        dsfaq_plus_require_ajax_nonce();
// 2011.09.07: 1.0.13 for visible

        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("There is not id number"); }
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   

        if(!isset($_POST['visible']) || $_POST['visible'] === ""){ dsfaq_plus_ajax_error("Not setting of visible"); }
        $visible = (int) wp_unslash( $_POST['visible'] ); 
        if($visible != 0) $visible = 1;

        $results = $wpdb->update( $table_name, array( 'visible' => $visible ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

        if($results){
            $results = '';
            $results .= '<input type="radio" name="dsfaq_faqdisplay_visible_'.$id.'" onclick="dsfaq_faqdisplay_visible(\''.$id.'\', \'1\');" '.(($visible == 1)?"checked":"").'> '.(($visible == 1)?"<b>":"").__('Publish', 'wp-ds-faq-plus').(($visible == 1)?"</b>":"");
            $results .= ' &nbsp; ';
            $results .= '<input type="radio" name="dsfaq_faqdisplay_visible_'.$id.'" onclick="dsfaq_faqdisplay_visible(\''.$id.'\', \'0\');" '.(($visible == 0)?"checked":"").'> '.(($visible == 0)?"<b>":"").__('Not publish', 'wp-ds-faq-plus').(($visible == 0)?"</b>":"");
        }
                
        $results = addcslashes($results, $escape);
        die("document.getElementById('dsfaq_faqdisplay_visible_".$id."').innerHTML = '".$results."';");
        break;


    case 'open_quest':
        if(!isset($_POST['id']) || $_POST['id'] == ""){ dsfaq_plus_ajax_error("POST: id error in open_quest. ");; }
//        $id = $_POST['id'];
        $id = (int) wp_unslash( $_POST['id'] ); if(!is_int($id)){ break; }   // fixed by WP DS FAQ 1.3.3 (1.0.10: 2011.08.22)

        $select = $wpdb->get_results( $wpdb->prepare( "SELECT `answer` FROM %i WHERE `id` = %d", $table_quest, $id ), ARRAY_A );
        $answer = wp_kses_post($select[0]['answer']);
        
        $results = '';
        $results .= '<div class="dsfaq_answer">';
        $results .= apply_filters('the_content', $answer, 'dsfaq_filters');
        $results .= '<br><span class="dsfaq_tools">[&nbsp;<a href="#_" onclick="dsfaq_close_quest('.$id.');">'.__('Close', 'wp-ds-faq-plus').'</a>&nbsp;]</span>';
        $results .= '</div>';
        
			// 2011.08.25 (1.0.11) Limitation by settings and current user permission
//            if(current_user_can('level_10')){
		if($settings_editor_permission){
         	  $tools .= '[ <a href="#_" onclick="dsfaq_front_edit_quest('.$id.');">'.__('Edit', 'wp-ds-faq-plus').'</a> ]';

			  // 2011.08.25 (1.0.11) Limitation by settings and current user permission
			  if(! $settings['wp_dsfaq_plus_disable_all_delete'] && ! $settings['wp_dsfaq_plus_disable_frontedit_delete']){
	           		 $tools .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.$id.');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';
	          }else if($settings_admin_permission && ! $settings['wp_dsfaq_plus_apply_safetyoptions_to_admin']){
	           		 $tools .= '[ <a href="#_" onclick="this.innerHTML=\'<img src='.$dsfaq->plugurl.'img/ajax-loader.gif>\'; dsfaq_front_delete_quest('.$id.');">'.__('Delete&nbsp;question', 'wp-ds-faq-plus').'</a> ]';
	          }
        }
        
        $results = addcslashes($results, $escape);
        $tools   = addcslashes($tools, $escape);
        $results = str_replace('\"','', $results);
        $results = str_replace('\'','', $results);
		
        die("document.getElementById('dsfaq_answer_".$id."').innerHTML = '".$results."';\n
        document.getElementById('dsfaq_tools_".$id."').innerHTML = '".$tools."';");
        
        break;
        
    case 'restore_settings':
        dsfaq_plus_require_ajax_nonce();
        update_option('wp_ds_faq_array', $dsfaq->wp_ds_faq_default_array);
        
        die("document.getElementById('dsfaq_h1').value = '".addcslashes($dsfaq->wp_ds_faq_default_array['wp_ds_faq_h1'], $escape)."';\n
        document.getElementById('dsfaq_h2').value = '".addcslashes($dsfaq->wp_ds_faq_default_array['wp_ds_faq_h2'], $escape)."';\n
        document.getElementById('dsfaq_css').value = '".addcslashes($dsfaq->wp_ds_faq_default_array['wp_ds_faq_css'], $escape)."';\n
        document.getElementById('dsfaq_copyr').checked = 'true';\n
        document.getElementById('dsfaq_progress').innerHTML = '<span style=\'color:green;\'>".__('Settings&nbsp;have&nbsp;been&nbsp;restore:', 'wp-ds-faq-plus')."&nbsp;".wp_date("Y-m-d H:i:s")."</span>';");
        break;

    default:
        dsfaq_plus_ajax_error();
        break;
}

die( "document.getElementById(\"s1\").innerHTML = '<span style=\"color:red;\">[Error id: 1000]</span>';" );
}
}

$dsfaq = new dsfaq();
