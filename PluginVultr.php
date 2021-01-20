<?php

require_once 'Vultr.class.php';

class PluginVultr extends ServerPlugin
{
    public $features = [
        'packageName' => false,
        'testConnection' => true,
        'showNameservers' => false,
        'directlink' => false
    ];

    public $api;

    public function setup($args)
    {
        $this->api = new Vultr($args['server']['variables']['plugin_vultr_API_Key']);
    }

    public function getVariables()
    {
        $variables = [
            lang("Name") => [
                "type" => "hidden",
                "description" => "Used by CE to show plugin - must match how you call the action function names",
                "value" => "Vultr"
            ],
            lang("Description") => [
                "type" => "hidden",
                "description" => lang("Description viewable by admin in server settings"),
                "value" => lang("SolusVM control panel integration")
            ],
            lang("API Key") => [
                "type" => "text",
                "description" => lang("API Key"),
                "value" => "",
                "encryptable" => true
            ],
            lang("VM Password Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the root password."),
                "value" => ""
            ],
            lang("VM Hostname Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the VM hostname."),
                "value" => ""
            ],
            lang("VM Operating System Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the VM Operating System."),
                "value" => ""
            ],
            lang("VM Location Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the Location/Region"),
                "value" => ""
            ],
            lang("Actions") => [
                "type" => "hidden",
                "description" => lang("Current actions that are active for this plugin per server"),
                "value" => "Create,Delete,Suspend,UnSuspend"
            ],
            lang('Registered Actions For Customer') => [
                "type" => "hidden",
                "description" => lang("Current actions that are active for this plugin per server for customers"),
                "value" => ""
            ],
            lang("reseller") => [
                "type" => "hidden",
                "description" => lang("Whether this server plugin can set reseller accounts"),
                "value" => "0",
            ],
            lang("package_addons") => [
                "type" => "hidden",
                "description" => lang("Supported signup addons variables"),
                "value" => "",
            ],
            lang('package_vars') => [
                'type' => 'hidden',
                'description' => lang('Whether package settings are set'),
                'value' => '0',
            ],
            lang('package_vars_values') => [
                'type'        => 'hidden',
                'description' => lang('VM account parameters'),
                'value'       => array(
                    'plan' => array(
                        'type'        => 'dropdown',
                        'multiple'    => false,
                        'getValues'   => 'getPlans',
                        'label'       => lang('Plan'),
                        'description' => lang(''),
                        'value'       => '',
                    ),
                ),
            ],
        ];

        return $variables;
    }

    public function validateCredentials($args)
    {
    }

    public function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->delete($args);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_vultr_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been deleted.';
    }

    public function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->create($args);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_vultr_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been created.';
    }

    public function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->suspend($args);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_vultr_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been suspended.';
    }

    public function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->unsuspend($args);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_vultr_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been unsuspended.';
    }

    public function unsuspend($args)
    {
        $this->setup($args);
        $this->api->start($args['package']['ServerAcctProperties']);
    }

    public function suspend($args)
    {
        $this->setup($args);
        $this->api->halt($args['package']['ServerAcctProperties']);
    }

    public function delete($args)
    {
        $this->setup($args);
        $this->api->destroy($args['package']['ServerAcctProperties']);

        $userPackage = new UserPackage($args['package']['id']);
        $userPackage->setCustomField('Server Acct Properties', '');
    }

    public function getAvailableActions($userPackage)
    {
        $args = $this->buildParams($userPackage);
        $this->setup($args);

        $actions = [];
        if ($args['package']['ServerAcctProperties'] == '') {
            $actions[] = 'Create';
        } else {
            $foundServer = false;
            $servers = $this->api->server_list();
            foreach ($servers as $key => $server) {
                if ($key == $args['package']['ServerAcctProperties']) {
                    $foundServer = true;
                    if (strtolower($server['power_status']) == 'running') {
                        $actions[] = 'Suspend';
                    } else {
                        $actions[] = 'UnSuspend';
                    }
                    $actions[] = 'Delete';
                }
            }
            if ($foundServer == false) {
                $actions[] = 'Create';
            }
        }

        return $actions;
    }

    public function create($args)
    {
        $this->setup($args);
        $userPackage = new UserPackage($args['package']['id']);

        $options = [
            'DCID' => $userPackage->getCustomField(
                $args['server']['variables']['plugin_vultr_VM_Location_Custom_Field'],
                CUSTOM_FIELDS_FOR_PACKAGE
            ),
            'VPSPLANID' => $args['package']['variables']['plan'],
            'OSID' => $userPackage->getCustomField(
                $args['server']['variables']['plugin_vultr_VM_Operating_System_Custom_Field'],
                CUSTOM_FIELDS_FOR_PACKAGE
            ),
            'hostname' => $userPackage->getCustomField(
                $args['server']['variables']['plugin_vultr_VM_Hostname_Custom_Field'],
                CUSTOM_FIELDS_FOR_PACKAGE
            )
        ];

        $serverId = $this->api->create($options);
        $userPackage->setCustomField('Server Acct Properties', $serverId);

        $foundIp = false;
        while ($foundIp == false) {
            $servers = $this->api->server_list();
            foreach ($servers as $key => $server) {
                if ($key == $serverId) {
                    if ($server['main_ip'] != '0.0.0.0') {
                        $userPackage->setCustomField('IP Address', $server['main_ip']);
                        $userPackage->setCustomField('Shared', 0);
                        $userPackage->setCustomField(
                            $args['server']['variables']['plugin_vultr_VM_Password_Custom_Field'],
                            $server['default_password'],
                            CUSTOM_FIELDS_FOR_PACKAGE
                        );
                        $foundIp = true;
                        break;
                    } else {
                        CE_Lib::log(4, "Sleeping for four seconds...");
                        sleep(4);
                    }
                }
            }
        }
    }

    public function testConnection($args)
    {
        CE_Lib::log(4, 'Testing connection to Vultr');
        $this->setup($args);
        $response = $this->api->account_info();
        if (!is_array($response)) {
            throw new CE_Exception($response);
        }
    }


    public function getPlans($serverId)
    {
        $server = new Server($serverId);
        $pluginVariables = $server->getAllServerPluginVariables($this->user, 'vultr');
        $this->setup($pluginVariables);

        $plans = [];
        $plans[0] = lang('-- Select VPS Plan --');
        foreach ($this->api->plans_list() as $plan) {
            $plans[$plan['VPSPLANID']] = str_replace(',', ', ', $plan['name']);
        }
        return $plans;
    }
}
