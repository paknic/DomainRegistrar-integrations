<?php

declare(strict_types=1);

// Global mock classes for third-party hosting/billing platforms (Blesta, ClientExec, HostBill, Plesk)

// Blesta mocks
if (!class_exists('RegistrarModule')) {
    class BlestaInput {
        public array $errors = [];
        public function setErrors(array $errors): void {
            $this->errors = $errors;
        }
    }

    class RegistrarModule {
        public $Input;
        public $meta;

        public function __construct() {
            $this->Input = new BlestaInput();
        }

        public function getModuleRow($id = null) {
            $row = new \stdClass();
            $row->meta = $this->meta;
            return $row;
        }
    }
}

// ClientExec mocks
if (!class_exists('RegistrarPlugin')) {
    class RegistrarPlugin {
        public $settings;
    }
}

// HostBill mocks
if (!class_exists('DomainModule')) {
    class DomainModule {
        public array $options = [];
        public array $details = [];
        public int $period = 1;
        public array $domain_contacts = [];

        public array $infos = [];
        public array $errors = [];

        public function __set(string $name, $value): void {
            $this->$name = $value;
        }

        public function &__get(string $name) {
            return $this->$name;
        }

        public function __isset(string $name): bool {
            return isset($this->$name);
        }

        public function addInfo(string $msg): void {
            $this->infos[] = $msg;
        }

        public function addError(string $msg): void {
            $this->errors[] = $msg;
        }
    }
}

// Plesk mocks
if (!interface_exists('EventListener')) {
    interface EventListener {
        public function filterActions();
        public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues);
    }
}

if (!class_exists('pm_Settings')) {
    class pm_Settings {
        public static array $settings = [];
        public static function get($name, $default = '') {
            return self::$settings[$name] ?? $default;
        }
        public static function getDecrypted($name) {
            return self::$settings[$name] ?? '';
        }
        public static function set($name, $val): void {
            self::$settings[$name] = $val;
        }
    }
}

if (!class_exists('pm_Log')) {
    class pm_Log {
        public static array $messages = [];
        public static function info($message): void {
            self::$messages[] = $message;
        }
    }
}

if (!class_exists('pm_Client')) {
    class pm_Client {
        public function getProperty($prop) {
            return 'pleskclient@example.com';
        }
    }
    class pm_Domain {
        public static function getByDomainId($id) {
            return new self();
        }
        public function getName(): string {
            return 'avail-plesk.com';
        }
        public function getClient(): pm_Client {
            return new pm_Client();
        }
    }
}
