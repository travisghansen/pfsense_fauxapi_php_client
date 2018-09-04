<?php

namespace PfSenseFauxApi;

class Client
{
    /**
     * URL of the pfSense server without trailing slash
     *
     * @var stirng
     */
    private $uri = "";

    /**
     * API Key to use
     *
     * @var string
     */
    private $apiKey = "";

    /**
     * API Secret to use
     *
     * @var string
     */
    private $apiSecret = "";

    /**
     * Add debug data to the responses
     *
     * @var bool
     */
    private $debug = false;

    /**
     * Whether insecure ssl communication is allowed
     *
     * @var bool
     */
    private $insecure = false;

    /**
     * Create a new instance of the client
     *
     * Client constructor.
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->uri = $options['uri'];
        $this->apiKey = $options['apiKey'];
        $this->apiSecret = $options['apiSecret'];

        if (key_exists('debug', $options)) {
            $this->debug = (bool) $options['debug'];
        }

        if (key_exists('insecure', $options)) {
            $this->insecure = (bool) $options['insecure'];
        }
    }

    /**
     * @return bool
     */
    public function getInsecure()
    {
        return $this->insecure;
    }

    /**
     * @param $value
     */
    public function setInsecure($value)
    {
        $this->insecure = (bool) $value;
    }

    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param $value
     */
    public function setDebug($value)
    {
        $this->debug = (bool) $value;
    }

    /**
     * Generate a random string usable for nonce
     *
     * @param int $length
     * @return string
     */
    private function generate_random_string($length = 8)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * Generate the value to be used for the auth header
     *
     * apikey:timestamp:nonce:HASH(apisecret:timestamp:nonce)
     *
     * @return string
     */
    private function generate_auth()
    {
        $nonce = $this->generate_random_string(8);
        $timestamp = gmdate("Ymd\ZHis");
        $hash = hash('sha256', $this->apiSecret.$timestamp.$nonce);

        return $this->apiKey.':'.$timestamp.':'.$nonce.':'.$hash;
    }

    /**
     * Build URL to be used for request
     *
     * @param $action
     * @param array $args
     * @return string
     */
    private function build_uri($action, $args = [])
    {
        $args['action'] = $action;
        if ($this->debug) {
            $args['__debug'] = true;
        }

        return $this->uri.'/fauxapi/v1/?'.http_build_query($args);
    }

    /**
     * Perform API request
     *
     * @param $method
     * @param $action
     * @param null $data
     * @param array $args
     * @return bool|mixed|string
     */
    private function api_request($method, $action, $data = null, $args = [])
    {
        $auth = $this->generate_auth();

        $opts = array(
            'http' => array(
                'method' => $method,
                'ignore_errors' => true,
                'header' => "fauxapi-auth: ${auth}\r\nContent-Type: application/json\r\n",
                'content' => (empty($data))? null : json_encode($data),
            ),
        );

        if ($this->insecure) {
            $opts['ssl']['verify_peer'] = false;
        }

        $context = stream_context_create($opts);
        $fp = fopen($this->build_uri($action, $args), 'r', false, $context);
        $response = stream_get_contents($fp);
        fclose($fp);

        $response = json_decode($response, true);

        return $response;
    }

    /**
     * Causes the pfSense host to immediately update any urltable alias entries from their (remote) source URLs.
     * Optionally update just one table by specifying the table name, else all tables are updated.
     *
     * HTTP: GET
     * params:
     * table (optional, default = null)
     *
     * @param array $params
     * @return bool|mixed|string
     */
    public function alias_update_urltables($params = [])
    {
        return $this->api_request('GET', __FUNCTION__, null, $params);
    }

    /**
     * Causes the system to take a configuration backup and add it to the regular set of pfSense system backups at
     * /cf/conf/backup/
     *
     * params:
     *
     * @return bool|mixed|string
     */
    public function config_backup()
    {
        return $this->api_request('GET', __FUNCTION__);
    }

    /**
     * Returns a list of the currently available pfSense system configuration backups.
     *
     * HTTP: GET
     * params: none
     *
     * @return bool|mixed|string
     */
    public function config_backup_list()
    {
        return $this->api_request('GET', __FUNCTION__);
    }

    /**
     * Returns the system configuration as a JSON formatted string. Additionally, using the optional config_file
     * parameter it is possible to retrieve backup configurations by providing the full path to it under the
     * /cf/conf/backup path.
     *
     * HTTP: GET
     * params:
     * config_file (optional, default=/cf/config/config.xml)
     *
     * @param array $params
     * @return bool|mixed|string
     */
    public function config_get($params = [])
    {
        return $this->api_request('GET', __FUNCTION__, null, $params);
    }

    /**
     * Allows the API user to patch the system configuration with the existing system config
     *
     * A config_patch call allows the API user to supply the partial configuration to be updated which is quite
     * different to the config_set function that requires the full configuration to be posted.
     *
     * HTTP: POST
     * params:
     * do_backup (optional, default = true)
     * do_reload (optional, default = true)
     *
     * @param array $data
     * @param array $params
     * @return bool|mixed|string
     */
    public function config_patch($data = [], $params = [])
    {
        return $this->api_request('POST', __FUNCTION__, $data, $params);
    }


    /**
     * Causes the pfSense system to perform a reload action of the config.xml file, by default this happens when the
     * config_set action occurs hence there is normally no need to explicitly call this after a config_set action.
     *
     * HTTP: GET
     * params: none
     *
     * @return bool|mixed|string
     */
    public function config_reload()
    {
        return $this->api_request('GET', __FUNCTION__);
    }

    /**
     * Restores the pfSense system to the named backup configuration.
     *
     * HTTP: GET
     * params:
     * config_file (required, full path to the backup file to restore)
     *
     * @param array $params
     * @return bool|mixed|string
     */
    public function config_restore($params = [])
    {
        return $this->api_request('GET', __FUNCTION__, null, $params);
    }

    /**
     * Sets a full system configuration and (by default) takes a system config backup and (by default) causes the system
     * config to be reloaded once successfully written and tested.
     *
     * NB1: be sure to pass the FULL system configuration here, not just the piece you wish to adjust! Consider the
     * config_patch or config_item_set functions if you wish to adjust the configuration in more granular ways.
     *
     * NB2: if you are pulling down the result of a config_get call, be sure to parse that response data to obtain the
     * config data only under the key .data.config
     *
     * HTTP: POST
     * params:
     * do_backup (optional, default = true)
     * do_reload (optional, default = true)
     *
     * @param array $data
     * @param array $params
     * @return bool|mixed|string
     */
    public function config_set($data = [], $params = [])
    {
        return $this->api_request('POST', __FUNCTION__, $data, $params);
    }

    /**
     * Call directly a pfSense PHP function with API user supplied parameters. Note that is action is a VERY raw
     * interface into the inner workings of pfSense and it is not recommended for API users that do not have a solid
     * understanding of PHP and pfSense. Additionally, not all pfSense functions are appropriate to be called through
     * the FauxAPI and only very limited testing has been performed against the possible outcomes and responses. It is
     * possible to harm your pfSense system if you do not 100% understand what is going on.
     *
     * Functions to be called via this interface MUST be defined in the file /etc/pfsense_function_calls.txt only a
     * handful very basic and read-only pfSense functions are enabled by default.
     *
     * HTTP: POST
     * params:
     *
     * @param $data
     * @return bool|mixed|string
     */
    public function function_call($data)
    {
        return $this->api_request('POST', __FUNCTION__, $data);
    }

    /**
     * Returns gateway status data.
     *
     * HTTP: GET
     * params:
     *
     * @return bool|mixed|string
     */
    public function gateway_status()
    {
        return $this->api_request('GET', __FUNCTION__);
    }

    /**
     * Returns interface statistics data and information - the real interface name must be provided not an alias of the
     * interface such as "WAN" or "LAN"
     *
     * HTTP: GET
     * params:
     * interface (required)
     *
     * @param $params
     * @return bool|mixed|string
     */
    public function interface_stats($params)
    {
        return $this->api_request('GET', __FUNCTION__, null, $params);
    }

    /**
     * Returns the numbered list of loaded pf rules from a pfctl -sr -vv command on the pfSense host. An empty
     * rule_number parameter causes all rules to be returned.
     *
     * HTTP: GET
     * params:
     * rule_number (optional, default = null)
     *
     * @param array $params
     * @return bool|mixed|string
     */
    public function rule_get($params = [])
    {
        return $this->api_request('GET', __FUNCTION__, null, $params);
    }

    /**
     * Performs a pfSense "send_event" command to cause various pfSense system actions as is also available through the
     * pfSense console interface. The following standard pfSense send_event combinations are permitted:-
     *  filter: reload, sync
     *  interface: all, newip, reconfigure
     *  service: reload, restart, sync
     *
     * HTTP: POST
     * params: none
     *
     * @param $data
     * @return bool|mixed|string
     */
    public function send_event($data)
    {
        return $this->api_request('POST', __FUNCTION__, $data);
    }

    /**
     * Just as it says, reboots the system.
     *
     * HTTP: GET
     * params: none
     *
     * @return bool|mixed|string
     */
    public function system_reboot()
    {
        return $this->api_request('GET', __FUNCTION__);
    }

    /**
     * Returns various useful system stats.
     *
     * HTTP: GET
     * params: none
     *
     * @return bool|mixed|string
     */
    public function system_stats()
    {
        return $this->api_request('GET', __FUNCTION__);
    }
}
