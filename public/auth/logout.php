<?php
/**
 * Logout.
 */

require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/helpers.php';

auth_logout();
flash_set('info', 'You have been logged out.');
redirect('/');
