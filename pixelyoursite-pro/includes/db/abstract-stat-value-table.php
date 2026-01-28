<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class StatValueTable extends DataBaseTable {

    function getCreateSql()
    {
        $collate = '';
        $tableName = $this->getName();
        if ( $this->wpdb->has_cap( 'collation' ) && $this->wpdb->get_charset_collate() != false) {
            $collate = $this->wpdb->get_charset_collate();
        }
        return "CREATE TABLE $tableName (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              item_value char(200) NOT NULL,
              PRIMARY KEY  (id)
            ) $collate;";
    }

    /**
     * Add new row
     * @param \wpdb $wpdb
     * @param string $value
     * @return int id item or null if value is empty
     */
    function insert($value) {
        if(empty($value)) return null;
        $table = $this->getName();

        $id = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM $table WHERE item_value = '%s' ",$value)
        );
        if($id != null) {
            return $id;
        }
        $this->wpdb->insert($this->getName(),
            ["item_value" => $value],
            ['%s']
        );
        return $this->wpdb->insert_id;
    }
}