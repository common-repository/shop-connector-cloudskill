<?php
if (!defined('ABSPATH')) //Leise das Script beenden, um unerwünschten Zugriff zu vermeiden
    exit;

/**
 * Kreiert die Seite anhand von einer GET-Variable namens "tab" die Aussagt welche
 * Seite generiert werden soll.
 */
function cloudskill_create_order_page() {
    $api = new Cloudskill\Api();
    $tab_url = sanitize_text_field(filter_input(INPUT_GET, 'tab'));
    $tab = (!$tab_url) ? 'orders' : $tab_url;
    cloudskill_shop_nav_tab_bes($tab);

    switch ($tab) {
        case 'orders':
            cloudskill_shop_create_orders_tab($api);
            break;
        case 'customer_accounts':
            cloudskill_shop_create_customeracc_tab($api);
            break;
        case 'settings':
            cloudskill_shop_create_setting_tab($api);
            break;
        default:
            break;
    }
}

/**
 * Generiert die Tab-Seite "Bestellungen".
 */
function cloudskill_shop_create_orders_tab($api) {
    $query = new WC_Order_Query(array(
        'limit' => 20,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
    $woo_orders = $query->get_orders();

    cloudskill_order_to_cs_button_event($api, $woo_orders);
    ?>    
    <div class="cs_shop_orders cs_shop_ani" name="cs_shop_orders">
        <form method="POST" action="?page=cs-shop-orders&tab=orders">
            <table class="cs_shop_table_fancy">
                <tr>
                    <th width="35%">Name</th>
                    <th width="20%">Bestellnummer</th>
                    <th width="20%">Betrag</th>
                    <th width="25%"></th>
                </tr>
                <?php
                foreach ($woo_orders as $order) {
                    ?>
                    <tr>
                        <td><?php echo esc_html($order->get_billing_first_name() . " " . $order->get_billing_last_name()) ?></td>
                        <td><?php echo esc_html($order->get_id()) ?></td>
                        <td><?php echo esc_html($order->get_total() . " €") ?></td>
                        <td style="text-align: right">
                            <?php
                            $found = cloudskill_found_order_in_orders_numbers($api, $order->get_id());
                            if ($found) {
                                ?>
                                <div class="cs_shop_number_check">
                                    <span class="dashicons dashicons-cloud-saved"></span>
                                    <p style="margin-left: 5px;"><?php echo esc_html($found) ?></p>
                                </div>
                                <?php
                            } else {
                                ?><input class="cs_shop_table_sendto_cskill" name="cs_shop_send_order" value="An Cloudskill senden" type="button" onclick="window.location.href = '?page=cs-shop-orders&tab=orders&process_order=<?php echo esc_js($order->get_id()) ?>'"><?php
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </form>
    </div>
    <?php
}

/**
 * Prüft ob ein "Cloudskill senden"-Button geklickt wurde und sendet dann
 * die benötigten Daten zur Cloudskill-Api. Jenachdem ob Erfolg oder nicht
 * kommt eine Benachrichtigung
 * @param Cloudskill\api $api
 * @param array $orders
 */
function cloudskill_order_to_cs_button_event($api, $orders) {
    if (filter_has_var(INPUT_GET, "process_order")) {
        $order_id = sanitize_text_field(filter_input(INPUT_GET, 'process_order'));
        $order_id_int = intval($order_id);
        if ($order_id_int) {
            if (!cloudskill_found_order_in_orders_numbers($api, $order_id)) {
                foreach ($orders as $order) {
                    $id = $order->get_id();
                    if ($id == $order_id) {
                        $answer = $api->send_order_from_cs_shop($id);
                        $message = $answer ? " wurde erfolgreich von Cloudskill angenommen!" : "konnte von Cloudskill nicht angenommen werden!";
                        cloudskill_shop_create_notice($answer ? 'success' : 'error', "Bestellung $id $message");
                    }
                }
            } else
                cloudskill_shop_create_notice('error', 'Bestellung existiert bereits in Cloudskill!');
        }
    }
}

/**
 * Sucht, ob sich die ID bereits in der order_number Tabelle befindet und wenn ja
 * gibt er die number zurück, ansonsten FALSE
 * @param API $api 
 * @param int $order_id
 * @return int || boolean
 */
function cloudskill_found_order_in_orders_numbers($api, $order_id) {
    $order_numbers = $api->get_orders_cs_number();
    $result = (isset($order_numbers[$order_id])) ? $order_numbers[$order_id] : FALSE;
    return $result;
}

/**
 * Generiert die Tab-Seite Kundenaccounts
 */
function cloudskill_shop_create_customeracc_tab($api) {
    if (filter_has_var(INPUT_POST, 'safe_vorlage')) {
        if (filter_has_var(INPUT_POST, 'check_job_vorlage')) {
            $vorlage_value_id = sanitize_text_field(filter_input(INPUT_POST, 'cs_shop_job_vorlagen'));
            $api->set_job_vorlage_id((object) ['active' => true, 'id' => $vorlage_value_id]);
            cloudskill_shop_create_notice('success', 'Vorlage erfolgreich gespeichert!');
        } else {
            $api->set_job_vorlage_id((object) ['active' => false, 'id' => ""]);
            cloudskill_shop_create_notice('success', 'Vorlage erfolgreich deaktiviert!');
        }
    }
    $vorlage_id = $api->get_job_vorlage_id();
    $vorlagen_res = $api->get_job_vorlagen_from_cloudskill();

    if (filter_has_var(INPUT_POST, 'btn_authtoken')) {
        if (WP_Application_Passwords::application_name_exists_for_user(get_current_user_id(), 'generated Token CS')) {
            $c_token = $api->get_user_auth_token();
            $erg = WP_Application_Passwords::delete_application_password(get_current_user_id(), $c_token['uuid']);
        }
        $app_ps = WP_Application_Passwords::create_new_application_password(get_current_user_id(), array('name' => 'generated Token CS', 'app_id' => wp_generate_uuid4()));
        if (is_wp_error($erg) && is_wp_error($app_ps)) {
            cloudskill_shop_create_notice('error', 'Neuer Authentifikations Token konnte nicht generiert werden!' . $app_ps->get_error_message() . $erg->get_error_message());
        } else {
            cloudskill_shop_create_notice('success', 'Neuer Authentifikations Token wurde generiert!');
            $api->set_user_auth_token(array('uuid' => $app_ps[1]['uuid'], 'token' => $app_ps[0]));
        }
    }

    $current_token = $api->get_user_auth_token();
    ?> 
    <div style="margin-bottom: 20px">
        <form method="POST" action="?page=cs-shop-orders&tab=customer_accounts">
            <div class="cs_shop_job_vorlage cs_shop_ani" name="cs_shop_jobvorlage">
                <h2> Job Vorlage </h2>
                <p> Hier kannst du eine Job Vorlage aktivieren </p>
                <table class="cs_shop_form_table cs_shop_table" name="cs_shop_table_jobvorlage">
                    <tr>
                        <th style="text-align: left; width: 15%">
                            <p class="cs_shop_table_header"> Vorlage aktivieren </p>
                        </th>
                        <td>
                            <input type="checkbox" name="check_job_vorlage" id="check_job_vorlage" onclick="click_checked()" <?php if ($vorlage_id->active) echo 'checked' ?>/>
                        </td>
                    </tr>
                    <tr id="job_vorlagen" style="display: none;">
                        <th style="text-align: left; width: 15%">
                            <p class="cs_shop_table_header"> Vorlage </p>
                        </th>
                        <td>
                            <select name="cs_shop_job_vorlagen" id="cs_shop_job_vorlagen">
                                <?php
                                foreach ($vorlagen_res->vorlagen as $key => $value) {
                                    ?><option <?php if ($value->id == $vorlage_id->id) echo 'selected="selected"' ?> value="<?php echo esc_attr($value->id) ?>"><?php echo esc_attr($value->name) ?></option><?php
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="safe_vorlage">
                        <th>
                        </th>
                        <td>
                            <input class="cs_shop_btn_group cs_shop_btn_table_button" type="submit" value="Speichern" name="safe_vorlage"/>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="cs_shop_auth_token cs_shop_ani" name="cs_shop_authtoken">
                <h2>User Authentifizierungs Token</h2>
                <p>Hier kannst du ein User Authentifikation Token erstellen und diesen an Cloudskill schicken.</p>
                <table class="cs_shop_form_table cs_shop_table" name="cs_shop_table_userauth">
                    <tr>
                        <th style="text-align: left; width: 15%">
                            <p class="cs_shop_table_header"> Token </p>
                        </th>
                        <td>
                            <input style="width: 300px" type="text" name="cs_shop_authtoken_txt" value="<?php if ($current_token) echo esc_attr($current_token['token']) ?>" placeholder="Token..." readonly/>
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <input class="cs_shop_btn_group cs_shop_btn_table_button" type="submit" name="btn_authtoken" value="Neuen Token generieren" />
                        </td>
                    </tr>
                </table>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Hook die ausgeführt wird sobald der User nach der Registrierung auf den Bestätigungs-Link klickt
 */
add_action('user_registration_check_token_complete', 'cloudskill_user_registration_reg_after_email_conf', 10, 2);

/**
 * Schick eine Joberstellung an Cloudskill
 * @param int $user_id
 * @param bool $user_reg_successful
 */
function cloudskill_user_registration_reg_after_email_conf($user_id, $user_reg_successful) {
    if (!$user_reg_successful) {
        return;
    }
    $api = new Cloudskill\Api();
    $api->send_job_to_cloudskill($user_id);
}

/**
 * Generiert die Tab-Seite "Einstellungen".
 */
function cloudskill_shop_create_setting_tab($api) {
    if (filter_has_var(INPUT_POST, 'cs_shop_btn_save_token')) {
        $token = sanitize_key(filter_input(INPUT_POST, 'cs_shop_txt_token'));
        $api->set_token($token);
        cloudskill_shop_create_notice('success', 'Token wurde erfolgreich gespeichert!');
    }

    if (filter_has_var(INPUT_GET, 'success')) {
        $status = sanitize_text_field(filter_input(INPUT_GET, 'success'));
        $status_int = intval($status);
        if ($status_int !== false) {
            $message = $status_int === 1 ? 'API-Schlüssel wurden erfolgreich generiert!' : 'Authentifizierung fehlgeschlagen!';
            cloudskill_shop_create_notice($status_int === 1 ? 'success' : 'error', $message);
        }
    }
    ?>
    <div style="margin-bottom: 20px">
        <form method="POST" action="?page=cs-shop-orders&tab=settings">
            <div class="cs_shop_settings_token cs_shop_ani" name="cs_shop_token">
                <h2> Token </h2>
                <p> Hier kannst du den Bestellungs-Token erstellen/verwalten </p>
                <table class="cs_shop_form_table cs_shop_table" name="cs_shop_table_token">
                    <tr>
                        <th style="text-align: left; width: 10%;">
                            <p class="cs_shop_table_header"> Token </p>
                        </th>
                        <td>
                            <input style="width: 500px" name="cs_shop_txt_token" type="text" value="<?php echo esc_attr($api->get_token()) ?>" placeholder="Token eingeben"/>
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <input style="margin-left: 100px;margin-top: -5px" class="cs_shop_btn_group" name="cs_shop_btn_save_token" type="submit" value="Token speichern"/>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="cs_shop_settings_api_keys cs_shop_ani" name="cs_shop_api_keys">
                <h2> API Schlüssel </h2>
                <p> Hier kannst du einen neuen API-Schlüssel generieren </p>
                <table class="cs_shop_form_table cs_shop_table" name="cs_shop_table_api_keys">
                    <tr>
                        <th>
                            <input class="cs_shop_btn_group cs_shop_btn_table_button" type="submit" name="cs_shop_btn_generate_api_keys" value="API Schlüssel generieren"/>
                        </th>
                    </tr>
                </table>
            </div>
        </form>
    </div>
    <?php
    if (filter_has_var(INPUT_POST, 'cs_shop_btn_generate_api_keys')) {
        $store_url = get_site_url();
        $endpoint = '/wc-auth/v1/authorize';
        $params = [
            'app_name' => 'Cloudskill',
            'scope' => 'read_write',
            'user_id' => 1,
            'return_url' => $store_url . '/wp-admin/admin.php?page=cs-shop-orders&tab=settings',
            'callback_url' => $store_url . '/?callback=call'
        ];
        $query_string = http_build_query($params);
        header("Location:" . $store_url . $endpoint . '?' . $query_string);
    }
}

/**
 * Erstellt eine Art Wordpress-Notiz mit dem passenden Typen die 
 * bei verschiedenen Aktionen eine Message ausgibt.
 * @param string $type = Welcher Notiz-Typ angezeigt werden soll
 * @param string $message = Nachricht von Notiz 
 */
function cloudskill_shop_create_notice($type, $message) {
    $head = "";
    switch ($type) {
        case 'success':
            $head = "Erfolg";
            break;
        case 'error':
            $head = "Fehler";
            break;
        default:
            break;
    }
    ?>
    <div class="e-notice e-notice--<?php echo esc_attr($type) ?>">
        <div class="e-notice__content">
            <h3><?php echo esc_html($head) ?></h3>
            <p><?php echo esc_html($message) ?></p>
        </div>
    </div>
    <?php
}

/**
 * Erstellt den Header + Navigation.
 * @param string $active = Aktiver Tab
 */
function cloudskill_shop_nav_tab_bes($active) {
    $tabs = [
        'orders' => 'Bestellungen',
        'customer_accounts' => 'Kundenaccounts',
        'settings' => 'Einstellungen'
    ];
    ?>

    <div style="margin-top: 20px">
        <a href="admin.php?page=cloudskill">Zurück</a>
    </div>
    <div class="cs_shop_header">
        <p class="cs_shop_bes_header cs_shop_ani"> Cloudskill Shop </p>
    </div>
    <div>
        <nav class="cs_shop_nav_wrapper cs_shop_ani">
            <?php foreach ($tabs as $tab => $label) { ?>
                <a class="cs_shop_nav_tab <?php echo (esc_attr($active === $tab) ? 'cs_shop_nav_tab_active' : '') ?> cs_shop_ani"
                   href="?page=cs-shop-orders&tab=<?php echo esc_attr($tab) ?>">
                    <?php echo esc_html($label) ?> </a>
            <?php } ?>
        </nav>
    </div>
    <?php
}
