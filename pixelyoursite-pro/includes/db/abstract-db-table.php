<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class DataBaseTable {

    /**
     * @var \wpdb $wpdb
     */
    protected $wpdb;

    public function __construct($wpdb){
        $this->wpdb = $wpdb;
    }

    /**
     * Return table name
     * @return String
     */
    abstract function getName();

    /**
     * Return table name
     * @return String
     */
    abstract function getColName();

    /**
     * Get SQL statement to create table.
     * @return String
     */
    abstract function getCreateSql();

    /**
     * Create table
     * @return bool True on success or if the table already exists. False on failure.
     */
    function create() {
        $main_sql_create = $this->getCreateSql();
        $status = maybe_create_table( $this->getName(), $main_sql_create );

        if(!$status) {
            error_log("Error create {$this->getName()} {$this->wpdb->last_error}");
        }
        return $status;
    }

    /**
     * Get row count
     * @return int
     */
    function getRowCount() {
        return intval($this->wpdb->get_var("SELECT count(*) FROM {$this->getName()}"));
    }

    function clearAll() {
        $this->wpdb->query("DELETE FROM {$this->getName()}");
    }

    function delete() {
        $this->wpdb->query( "DROP TABLE IF EXISTS {$this->getName()}" );
    }

    function filterCharValue($value,$max) {
        if(strlen($value)>$max) {
            return substr($value,0,$max);
        }
        return $value;
    }


	function deleteDataBasedOnTime($request) {

		$deleteTime = $request['delete_time'];
		$startDate = $request['delete_time_start'] ?? null;
		$endDate = $request['delete_time_end'] ?? null;
		$type = isset($request['type']) ? ($request['type'] === 'woo' ? 0 : 1) : null; // Ensure 'type' is handled correctly

		// Your provided switch-case logic here
		switch ($deleteTime) {
			case 'yesterday':
				$startDate = date('Y-m-d', strtotime('-1 day'));
				$endDate = $startDate;
				break;
			case 'today':
				$startDate = date('Y-m-d');
				$endDate = $startDate;
				break;
			case '7':
				$startDate = date('Y-m-d', strtotime('-7 days'));
				$endDate = date('Y-m-d');
				break;
			case '30':
				$startDate = date('Y-m-d', strtotime('-30 days'));
				$endDate = date('Y-m-d');
				break;
			case 'current_month':
				$startDate = date('Y-m-01');
				$endDate = date('Y-m-t');
				break;
			case 'last_month':
				$startDate = date('Y-m-d', strtotime('first day of last month'));
				$endDate = date('Y-m-d', strtotime('last day of last month'));
				break;
			case 'year_to_date':
				$startDate = date('Y-01-01');
				$endDate = date('Y-m-d');
				break;
			case 'last_year':
				$startDate = date('Y-01-01', strtotime('-1 year'));
				$endDate = date('Y-12-31', strtotime('-1 year'));
				break;
			case 'custom':
				if($startDate == null || $endDate == null) {
					return;
				}
				$startDate = date('Y-m-d', strtotime($startDate));
				$endDate = date('Y-m-d', strtotime($endDate));
				break;
			case 'all':
				// For 'all', no date range is needed. Delete all records of the specified type.
				$tableName = $this->getName();
				$sql = $this->wpdb->prepare("DELETE FROM $tableName WHERE type = %d", $type);
				$this->wpdb->query($sql);
				return; // Exit after handling 'all'
		}

		if ($startDate && $endDate) {
			$tableName = $this->getName();
			$sql = $this->wpdb->prepare("DELETE FROM $tableName WHERE date BETWEEN %s AND %s AND type = %d", $startDate, $endDate, $type);
			$this->wpdb->query($sql);
		}
	}
}
