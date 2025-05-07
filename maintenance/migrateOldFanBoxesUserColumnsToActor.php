<?php
/**
 * @file
 * @ingroup Maintenance
 */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Run automatically with update.php
 *
 * @since January 2020
 */
class MigrateOldFanBoxesUserColumnsToActor extends MediaWiki\Maintenance\LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrates data from old _user_name/_user_id columns in fantag and user_fantag tables to the new actor columns.' );
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage() {
		return 'fantag and user_fantag have already been migrated to use the actor columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );
		$dbw->query(
			"UPDATE {$dbw->tableName( 'fantag' )} SET fantag_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=fantag_user_id AND actor_name=fantag_user_name)",
			__METHOD__
		);
		$dbw->query(
			"UPDATE {$dbw->tableName( 'user_fantag' )} SET userft_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=userft_user_id AND actor_name=userft_user_name)",
			__METHOD__
		);
		return true;
	}
}

$maintClass = MigrateOldFanBoxesUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
