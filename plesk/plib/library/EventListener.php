<?php
/**
 * Registers a newly created Plesk domain through Paknic when it is still available.
 */
class Modules_Paknic_EventListener implements EventListener
{
    public function filterActions()
    {
        return ['domain_create'];
    }

    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {
        if ($action !== 'domain_create') {
            return;
        }

        $domainName = isset($newValues['Domain Name']) ? $newValues['Domain Name'] : '';
        $email = '';
        try {
            $domain = pm_Domain::getByDomainId($objectId);
            if ($domainName === '') {
                $domainName = $domain->getName();
            }
            $email = (string)$domain->getClient()->getProperty('email');
        } catch (Exception $e) {
            // The domain name from the event is enough; the registrant email falls back to the settings.
        }

        if ($domainName === '') {
            return;
        }
        pm_Log::info('[Paknic] ' . Modules_Paknic_Registrar::registerIfAvailable($domainName, $email));
    }
}

return new Modules_Paknic_EventListener();
