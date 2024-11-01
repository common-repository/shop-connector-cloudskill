<?php

/**
 * Klassenobjekt für alle API Funktionen
 * wie z.B. Übermittlung Bestellung / API Daten
 * @author pascal
 */

namespace Cloudskill;

class Api {

    /**
     * Präfix für Gespeicherte Optionen
     * @var string
     */
    var $prefix = "cs_g1t89rf4_";

    public function __construct() {
        
    }

    /**
     * Sendet Key-Daten Daten an WooCommerce-API um wiederum api-key und
     * api-secret für WooCommerce REST-API zu erhalten und speichert diese
     * ab. Sendung auch an Cloudskill.
     * @return string 
     */
    function woo_callback_action() {
        $key_id = sanitize_text_field(json_decode(file_get_contents('php://input'))->key_id);
        if ($key_id) {
            ob_start();
            $consumer_key = sanitize_text_field(json_decode(file_get_contents('php://input'))->consumer_key);
            $consumer_secret = sanitize_text_field(json_decode(file_get_contents('php://input'))->consumer_secret);
            if ($consumer_key && $consumer_secret) {
                $erg = $this->send_woo_api_data($consumer_key, $consumer_secret);
                if ($erg->status) {
                    $this->set_woo_key($consumer_key);
                    $this->set_woo_secret($consumer_secret);
                } else
                    return $erg->error;
            }
        }
    }

    /**
     * schickt API Zugangsdaten zum Cloudskill und verarbeitet die Antwort
     * @param \stdClass $data
     * @return \stdClass Attribute "status" => (bool) true wenn erfolgreich sonst false, wenn false gibt es "error" => Fehlermeldung als String
     */
    function send_woo_api_data($consumer_key, $consumer_secret) {
        $api_data = [
            'consumer_key' => $consumer_key,
            'consumer_secret' => $consumer_secret,
            'shop_url' => get_site_url()
        ];
        $erg = $this->send("/shop/connect/woocommerce", $this->get_token(), $api_data);
        $ret = new \stdClass();
        if ($erg->status) {
            /* Api Connect an sich war erfolgreich, hier die Api Verarbeitung an sich noch prüfen */
            if ($erg->status == "success") {
                $ret->status = true;
            } else {
                $ret->status = false;
                $ret->error = $erg->error_text;
            }
        } else {
            $ret->status = false;
            $ret->error = "Verbindungsfehler. Bitte erneut versuchen";
        }

        return $ret;
    }

    /**
     * Wandelt eingehende Order in json-format String und sendet diese an Cloudskill.
     * Speichert id in session damit Prozess mit ID nur einmal staffinden kann  -> WC()-session.
     * Funktion ist speziell für den Nutzen im Kaufprozess gedacht, zum manuellen Versand die "send_order()" Funktion nutzen
     * @param int $order_id ID von eigehender Bestellung
     */
    function trigger_order($order_id) {
        $order_processed = WC()->session->get('order_processed_' . $order_id);
        if ($order_processed) {
            return;
        }

        $process = $this->send_order($order_id);

        if ($process->status)
            WC()->session->set('order_processed_' . $order_id, true);
    }

    /**
     * lädt über WooCommerce Objekt eine Bestellung, bereitet sie als Objekt fürs
     * Cloudskill, verschickt sie und wertet das Ergebnis aus
     * @param int $order_id ID von eigehender Bestellung
     * @return \stdClass Attribute "status" => (bool) true wenn erfolgreich sonst false, wenn true gibt es "number" => Belegnummer aus Cloudskill, wenn false gibt es "error" => Fehlermeldung als String
     */
    function send_order($order_id) {
        $order_data = $this->get_order_from_woo($order_id);
        $cs_arr = $this->woo_order_to_cs($order_data);
        $erg = $this->send("/shop/order", $this->get_token(), $cs_arr);
        /* Ergebnis des Aufrufs auswerten */
        $ret = new \stdClass();
        if ($erg) {
            /* Api Connect an sich war erfolgreich, hier die Api Verarbeitung an sich noch prüfen */
            if ($erg->status == "success") {
                $ret->status = true;
                $ret->number = $erg->data->beleg_nr;
                $this->update_orders_cs_number($order_id, $ret->number);
            } else {
                $ret->status = false;
                $ret->error = $erg->error_text;
            }
        } else {
            $ret->status = false;
            $ret->error = "Verbindungsfehler. Bitte erneut versuchen";
        }

        return $ret;
    }

    /**
     * Sendet Daten an Cloudskill-API und speichert bei Erfolg die Belegnummer
     * die als Antwort kommt und setzt den Status diser Order auf TRUE
     * für die Cloudskill-Shop Bestellungs-Seite
     * @param int $id
     * @param array $data
     * @return boolean
     */
    function send_order_from_cs_shop($id) {
        $answer = $this->send_order($id);
        return $answer->status;
    }

    /**
     * schickt eine Anfrage an die API von Cloudskill
     * erwartet ein JSON Objekt (kein String) und gibt ein Objekt zurück
     * selbst wenn der Zugriff erfolgreich war, muss immer das Ergebnis der Schnittstelle noch geprüft werden
     * @param \stdClass $json_obj
     * @return \stdClass Attribute "status" = true wenn erfolgreicher Aufruf sonst false, "response" mit dem JSON Objekt (kein String) des Ergebnis der Schnittstelle
     */
    private function send($additive, $token, $body = NULL) {
        if ($body)
            $b_json = json_encode($body);
        $args = array(
            'body' => $b_json,
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json;',
                'Authorization' => 'Bearer ' . $token
            ),
            'cookies' => array()
        );

        if($body)
            $response = wp_remote_post('https://api.cloudskill.de' . $additive, $args);
        else
            $response = wp_remote_get('https://api.cloudskill.de' . $additive, $args);

        if ($response['response']['code'] !== 200) {
            return false;
        } else {
            $res = json_decode($response['body']);
            if ($res)
                return $res;
            else
                return false;
        }
    }

    /**
     * greift per WooCommerce API auf die Bestellung zu
     * nutzt dafür die eigenen Zugangsdaten
     * @param int $order_id
     * @return \stdClass Attribute "status" = true wenn erfolgreicher Aufruf sonst false, "response" mit dem JSON Objekt (kein String) des Ergebnis der Schnittstelle
     */
    private function get_order_from_woo($order_id) {
        $woo_key = $this->get_woo_key();
        $woo_secret = $this->get_woo_secret();

        $args = array(
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'cookies' => array()
        );

        $url = get_site_url() . '/wp-json/wc/v3/orders/' . $order_id . '?consumer_key=' . $woo_key .
                '&consumer_secret=' . $woo_secret;

        $response = wp_remote_post($url, $args);

        if ($response['response']['code'] !== 200) {
            return false;
        } else {
            $res = json_decode($response['body']);
            if ($res)
                return $res;
            else
                return false;
        }
    }

    /**
     * Erstellt aus dem Objekt der WooCommerce Bestellung ein JSON Objekt für die Cloudskill API
     * @param \stdClass $woo_order_obj ergebnis aus get_order_from_woo()
     * @return \stdClass passendes Objekt für send()
     */
    private function woo_order_to_cs($woo_order_obj) {
        $cs_arr = [
            "number" => $woo_order_obj->number,
            "email" => $woo_order_obj->billing->email,
            "phone" => $woo_order_obj->billing->phone,
            "account_id" => $woo_order_obj->customer_id,
            "billing" => [],
            "delivery" => [],
            "comment" => $woo_order_obj->customer_note,
            "payment_method" => $woo_order_obj->payment_method,
            "full_amount" => $woo_order_obj->total,
            "shipping" => [
                "description" => $woo_order_obj->shipping_lines[0]->method_title,
                "cost" => $woo_order_obj->shipping_lines[0]->total
            ],
            "positions" => []
        ];

        /* Füllt data billing Array mit Werten */
        $cs_arr["billing"] = $this->woo_order_to_cs_address($woo_order_obj->billing);
        /* Füllt data delivery Array mit Werten */
        $cs_arr["delivery"] = $this->woo_order_to_cs_address($woo_order_obj->shipping);
        /* Füllt data positions Array mit Produkt-Arrays aus */
        $cs_arr["positions"] = $this->woo_order_to_cs_positions($woo_order_obj->line_items);

        /* Prüfung ob billing und delivery gleichen Inhalt haben.
          Falls Ja, wird delivery aus data gelsöcht */
        $intersect = array_intersect($cs_arr["billing"], $cs_arr["delivery"]);
        if (count($intersect) == 8) {
            unset($cs_arr["delivery"]);
        }

        return $cs_arr;
    }

    /**
     * Liefert ein Array für die API Daten maßgeschneidert für "billing" und "delivery" zurück
     * @param \stdClass $address Adressknoten einer WooCommerce Bestellung
     * @return array() Array für CS Api
     */
    private function woo_order_to_cs_address($address) {
        return [
            "first_name" => $address->first_name,
            "last_name" => $address->last_name,
            "company" => $address->company,
            "street" => $address->address_1,
            "house_number" => $address->address_2,
            "postal_code" => $address->postcode,
            "city" => $address->city,
            "country_code" => $address->country
        ];
    }

    /**
     * Liefert ein verschachteltes Arraycs_get_positions mit allen produkten,
     * maßgeschneidert f�r "positions" der CS API
     * @param \stdClass $line_items line_item Knoten einer WooCommerce Bestellung
     * @return array() Array f�r CS Api
     */
    private function woo_order_to_cs_positions($line_items) {
        $products = [];
        foreach ($line_items as $product) {
            $arr = [
                "item_number" => $product->sku,
                "item_description" => $product->name,
                "quantity" => $product->quantity,
                "item_amount" => $product->price
            ];
            $products[] = $arr;
        }
        return $products;
    }

    /**
     * Enthält nach erfolgreicher Request von Cloudskill alle Job-Vorlagen und gibt diese zurück
     * @return array
     */
    function get_job_vorlagen_from_cloudskill() {
        $response = $this->send("/job/template/list", $this->get_token());
        if ($response) {
            if ($response->status !== 'success')
                return $response->error_text;
            else
                return $response->data;
        } else
            return 'Fehler bei Anfrage';
    }

    /**
     * Wandelt Daten für die Joberstellung in ein passendes Format und schickt diese an Cloudskill um als Antwort eine Job ID zu erhalten
     * @param type $user_id
     */
    function send_job_to_cloudskill($user_id) {
        $user_data = get_userdata($user_id);
        $cs_arr = [
            'vorlage_id' => (int) $this->get_job_vorlage_id()->id,
            'account_id' => $user_id,
            'account_email' => $user_data->user_email,
            'log_text' => 'Name: ' . $user_data->first_name . ' ' . $user_data->last_name . ' | Email: ' . $user_data->user_email . ' | Account ID: ' . $user_id
        ];

        $response = $this->send("/job/createFromTemplate", $this->get_token(), $cs_arr);
        if ($response) {
            if ($response->status !== 'success')
                return $response->error_text;
            else
                return $response->data;
        } else
            return 'Fehler bei Anfrage';
    }

    /**
     * Gibt der Cloudskill-API passende identifikations Daten, diese schickt anhand dieser 
     * alle Aufträge des aktuellen Benutzers
     * @return boolean
     */
    function get_user_orders_from_cs() {
        $user_data = get_userdata(get_current_user_id());
        $cs_arr = [
            'account_id' => get_current_user_id(),
            'account_email' => $user_data->user_email
        ];

        $response = $this->send("/shop/order/list", $this->get_token(), $cs_arr);

        if ($response) {
            if ($response->status !== 'success')
                return $response->error_text;
            else
                return $response->data;
        } else
            return 'Fehler bei Anfrage';
    }

    /**
     * lädt den API WooCommerce Key
     * @return string
     */
    function get_woo_key() {
        return base64_decode(get_option($this->prefix . 'woo_key', ""));
    }

    /**
     * lädt das API WooCommerce Secret
     * @return string
     */
    function get_woo_secret() {
        return base64_decode(get_option($this->prefix . 'woo_secret', ""));
    }

    /**
     * lädt das Cloudskill Token
     * @return string
     */
    function get_token() {
        return base64_decode(get_option($this->prefix . 'token', ""));
    }

    /**
     * speichert den API WooCommerce Key
     * @param string $wert
     */
    function set_woo_key($wert) {
        if (!update_option($this->prefix . 'woo_key', base64_encode($wert)))
            add_option($this->prefix . 'woo_key', base64_encode($wert));
    }

    /**
     * speichert das API WooCommerce Secret
     * @param string $wert
     */
    function set_woo_secret($wert) {
        if (!update_option($this->prefix . 'woo_secret', base64_encode($wert)))
            add_option($this->prefix . 'woo_secret', base64_encode($wert));
    }

    /**
     * speichert das Cloudskill Token
     * @param string $wert
     */
    function set_token($wert) {
        if (!update_option($this->prefix . 'token', base64_encode($wert)))
            add_option($this->prefix . 'token', base64_encode($wert));
    }

    /**
     * Gibt das in Wordpress-option gespeicherte Array mit den Bestellungen
     * und deren Bestellnummer von Cloudskill zurück
     * @return array
     */
    function get_orders_cs_number() {
        return get_option($this->prefix . 'shop_orders_numbers', "");
    }

    /**
     * Gibt den User Authentification Token zurück
     * @return string
     */
    function get_user_auth_token() {
        return get_option($this->prefix . 'user_auth_token', "");
    }

    /**
     * Erstellt ein Array wechles die Bestellnummer der dazugehörigen
     * Bestellung beinhaltet und speichert diese dann in einer
     * Wordpress option.
     * @param int $id
     * @param int $number
     */
    function update_orders_cs_number($id, $number) {
        $orders_numbers = $this->get_orders_cs_number();
        if (count($orders_numbers) >= 20) {
            reset($orders_numbers);
            unset($orders_numbers[key($orders_numbers)]);
        }
        $orders_numbers[$id] = $number;
        update_option($this->prefix . 'shop_orders_numbers', $orders_numbers);
    }

    /**
     * Speichert die ausgewählte Job-Vorlage
     * @param stdobject $wert
     */
    function set_job_vorlage_id($wert) {
        if (!update_option($this->prefix . 'job_vorlage_id', $wert))
            add_option($this->prefix . 'job_vorlage_id', $wert);
    }

    /**
     * Speichert den User Authentification Token
     * @param string $wert
     */
    function set_user_auth_token($wert) {
        if (!update_option($this->prefix . 'user_auth_token', $wert))
            add_option($this->prefix . 'user_auth_token', $wert);
    }

    /**
     * Gibt die ausgewählte Job-Vorlage zurück
     * @return stdobject
     */
    function get_job_vorlage_id() {
        return get_option($this->prefix . 'job_vorlage_id', "");
    }

}
