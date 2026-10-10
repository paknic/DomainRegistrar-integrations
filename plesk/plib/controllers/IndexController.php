<?php
/**
 * Extension settings page (administrator only).
 */
class IndexController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();
        if (!pm_Session::getClient()->isAdmin()) {
            throw new pm_Exception('Access denied');
        }
        $this->view->pageTitle = 'Paknic Domain Registration';
    }

    public function indexAction()
    {
        $form = new pm_Form_Simple();

        $form->addElement('text', 'api_key', [
            'label' => 'API Key ID',
            'value' => pm_Settings::get('api_key', ''),
            'description' => 'pk_live_... for live, pk_test_... for the sandbox (nothing registered or charged).',
            'required' => true,
        ]);
        $form->addElement('password', 'api_secret', [
            'label' => 'API Secret',
            'description' => 'Leave empty to keep the saved secret.',
        ]);
        $form->addElement('text', 'api_url', [
            'label' => 'API Base URL',
            'value' => pm_Settings::get('api_url', 'https://api.paknic.com'),
            'description' => 'Host only, e.g. https://api.paknic.com',
        ]);
        $form->addElement('checkbox', 'auto_register', [
            'label' => 'Register new domains automatically',
            'value' => pm_Settings::get('auto_register', '0'),
            'description' => 'When a domain is created in Plesk and is still available, register it through Paknic (charged to your wallet).',
        ]);
        $form->addElement('text', 'only_tlds', [
            'label' => 'Only these TLDs',
            'value' => pm_Settings::get('only_tlds', ''),
            'description' => 'Comma-separated, e.g. .com, .net. Empty: all TLDs.',
        ]);
        $form->addElement('text', 'years', [
            'label' => 'Registration years',
            'value' => pm_Settings::get('years', '1'),
            'validators' => [['Int', true], ['Between', true, ['min' => 1, 'max' => 10]]],
        ]);
        $form->addElement('checkbox', 'auto_renew', [
            'label' => 'Auto-renew registered domains',
            'value' => pm_Settings::get('auto_renew', '1'),
        ]);
        $form->addElement('checkbox', 'whois_privacy', [
            'label' => 'Buy WHOIS privacy (paid add-on)',
            'value' => pm_Settings::get('whois_privacy', '0'),
        ]);
        $form->addElement('checkbox', 'dns_management', [
            'label' => 'Buy DNS management (paid add-on)',
            'value' => pm_Settings::get('dns_management', '0'),
        ]);
        $form->addElement('text', 'nameservers', [
            'label' => 'Nameservers',
            'value' => pm_Settings::get('nameservers', ''),
            'description' => 'Comma-separated, at least two, e.g. ns1.example.net, ns2.example.net',
        ]);

        $labels = [
            'first_name' => 'First name', 'last_name' => 'Last name', 'organization' => 'Organization',
            'address1' => 'Address', 'address2' => 'Address line 2', 'city' => 'City', 'state' => 'State / province',
            'postal_code' => 'Postal code', 'country_code' => 'Country code (2 letters)',
            'email' => 'Email (used when the customer has none)', 'phone' => 'Phone (+CC.NUMBER)',
        ];
        foreach ($labels as $field => $label) {
            $form->addElement('text', 'registrant_' . $field, [
                'label' => 'Registrant: ' . $label,
                'value' => pm_Settings::get('registrant_' . $field, ''),
            ]);
        }

        $form->addControlButtons(['cancelLink' => pm_Context::getModulesListUrl()]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            foreach (['api_key', 'api_url', 'only_tlds', 'years', 'nameservers'] as $name) {
                pm_Settings::set($name, trim((string)$form->getValue($name)));
            }
            foreach (['auto_register', 'auto_renew', 'whois_privacy', 'dns_management'] as $name) {
                pm_Settings::set($name, $form->getValue($name) ? '1' : '0');
            }
            foreach (array_keys($labels) as $field) {
                pm_Settings::set('registrant_' . $field, trim((string)$form->getValue('registrant_' . $field)));
            }
            $secret = trim((string)$form->getValue('api_secret'));
            if ($secret !== '') {
                pm_Settings::setEncrypted('api_secret', $secret);
            }

            try {
                $balance = Modules_Paknic_Registrar::client()->account()->getBalance();
                $this->_status->addMessage('info', 'Settings saved. Connected: wallet balance $' . number_format((float)$balance['balance'], 2) . '.');
            } catch (Exception $e) {
                $this->_status->addMessage('error', 'Settings saved, but the API connection failed: ' . $e->getMessage());
            }
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
        }

        $this->view->form = $form;
    }
}
