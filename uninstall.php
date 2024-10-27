<?php
/* Uninstall process - will remove the options from the database.
- new in version 3.00
 */
// exit if uninstall constant is not defined
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}
delete_option('AZLNK_options');
return;
