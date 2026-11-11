<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/inventory_import.php';
require_personal_scope();
require_once __DIR__ . '/../../include/property_picker_api.php';

property_picker_json_response();
