<?php
// Database compatibility functions for SQLite vs MySQL
require_once 'config.php';

class DatabaseHelper {
    
    /**
     * Get today's date in SQL format compatible with current database
     */
    public static function getCurrentDate() {
        if (DB_TYPE === 'sqlite') {
            return "date('now')";
        } else {
            return "CURDATE()";
        }
    }
    
    /**
     * Get current datetime in SQL format compatible with current database
     */
    public static function getCurrentDateTime() {
        if (DB_TYPE === 'sqlite') {
            return "datetime('now')";
        } else {
            return "NOW()";
        }
    }
    
    /**
     * Format date comparison for database compatibility
     */
    public static function dateEquals($column, $date = 'today') {
        if (DB_TYPE === 'sqlite') {
            if ($date === 'today') {
                return "DATE($column) = date('now')";
            } else {
                return "DATE($column) = '$date'";
            }
        } else {
            if ($date === 'today') {
                return "DATE($column) = CURDATE()";
            } else {
                return "DATE($column) = '$date'";
            }
        }
    }
    
    /**
     * Get date function for extracting date from datetime
     */
    public static function dateFunction($column) {
        return "DATE($column)";
    }
    
    /**
     * Format LIMIT clause
     */
    public static function limit($count, $offset = 0) {
        if ($offset > 0) {
            return "LIMIT $count OFFSET $offset";
        } else {
            return "LIMIT $count";
        }
    }
    
    /**
     * Auto increment syntax
     */
    public static function autoIncrement() {
        if (DB_TYPE === 'sqlite') {
            return "INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            return "INT AUTO_INCREMENT PRIMARY KEY";
        }
    }
    
    /**
     * Boolean field syntax
     */
    public static function booleanField($default = false) {
        if (DB_TYPE === 'sqlite') {
            $defaultValue = $default ? 1 : 0;
            return "INTEGER DEFAULT $defaultValue";
        } else {
            $defaultValue = $default ? 'TRUE' : 'FALSE';
            return "BOOLEAN DEFAULT $defaultValue";
        }
    }
    
    /**
     * Text field with length
     */
    public static function textField($length = null) {
        if (DB_TYPE === 'sqlite') {
            return $length ? "VARCHAR($length)" : "TEXT";
        } else {
            return $length ? "VARCHAR($length)" : "TEXT";
        }
    }
    
    /**
     * Timestamp field
     */
    public static function timestampField($default = 'CURRENT_TIMESTAMP') {
        if (DB_TYPE === 'sqlite') {
            return "DATETIME DEFAULT CURRENT_TIMESTAMP";
        } else {
            return "TIMESTAMP DEFAULT $default";
        }
    }
    
    /**
     * ENUM field (SQLite uses CHECK constraint)
     */
    public static function enumField($values, $default = null) {
        if (DB_TYPE === 'sqlite') {
            $checkValues = implode("', '", $values);
            $check = "CHECK (\$column IN ('$checkValues'))";
            $defaultClause = $default ? "DEFAULT '$default'" : "";
            return "VARCHAR(50) $defaultClause $check";
        } else {
            $enumValues = implode("', '", $values);
            $defaultClause = $default ? "DEFAULT '$default'" : "";
            return "ENUM('$enumValues') $defaultClause";
        }
    }
    
    /**
     * Get database-specific NOW() function
     */
    public static function now() {
        if (DB_TYPE === 'sqlite') {
            return "datetime('now')";
        } else {
            return "NOW()";
        }
    }
    
    /**
     * Get database-specific date formatting
     */
    public static function formatDate($column, $format = '%Y-%m-%d') {
        if (DB_TYPE === 'sqlite') {
            // SQLite uses strftime
            return "strftime('$format', $column)";
        } else {
            // MySQL uses DATE_FORMAT
            return "DATE_FORMAT($column, '$format')";
        }
    }
    
    /**
     * Get hour from time/datetime column
     */
    public static function hourFunction($column) {
        if (DB_TYPE === 'sqlite') {
            return "CAST(strftime('%H', $column) AS INTEGER)";
        } else {
            return "HOUR($column)";
        }
    }
    
    /**
     * Get day of week (0=Sunday, 1=Monday, etc.)
     */
    public static function dayOfWeekFunction($column) {
        if (DB_TYPE === 'sqlite') {
            return "CAST(strftime('%w', $column) AS INTEGER)";
        } else {
            return "DAYOFWEEK($column)";
        }
    }
    
    /**
     * Get week number
     */
    public static function weekFunction($column) {
        if (DB_TYPE === 'sqlite') {
            return "strftime('%W', $column)";
        } else {
            return "WEEK($column)";
        }
    }
    
    /**
     * Get month from date
     */
    public static function monthFunction($column) {
        if (DB_TYPE === 'sqlite') {
            return "strftime('%Y-%m', $column)";
        } else {
            return "DATE_FORMAT($column, '%Y-%m')";
        }
    }
    
    /**
     * Date arithmetic - subtract interval
     */
    public static function dateSubtract($column, $interval, $unit) {
        if (DB_TYPE === 'sqlite') {
            switch ($unit) {
                case 'DAY':
                case 'DAYS':
                    return "date($column, '-$interval days')";
                case 'WEEK':
                case 'WEEKS':
                    $days = $interval * 7;
                    return "date($column, '-$days days')";
                case 'MONTH':
                case 'MONTHS':
                    return "date($column, '-$interval months')";
                case 'YEAR':
                case 'YEARS':
                    return "date($column, '-$interval years')";
                default:
                    return $column;
            }
        } else {
            return "DATE_SUB($column, INTERVAL $interval $unit)";
        }
    }
    
    /**
     * CONCAT function
     */
    public static function concat($columns) {
        if (DB_TYPE === 'sqlite') {
            return implode(' || ', $columns);
        } else {
            return "CONCAT(" . implode(', ', $columns) . ")";
        }
    }
    
    /**
     * ROUND function (SQLite and MySQL compatible)
     */
    public static function roundFunction($expression, $decimals = 0) {
        return "ROUND($expression, $decimals)";
    }
}

// Quick helper functions
function db_current_date() {
    return DatabaseHelper::getCurrentDate();
}

function db_current_datetime() {
    return DatabaseHelper::getCurrentDateTime();
}

function db_date_equals($column, $date = 'today') {
    return DatabaseHelper::dateEquals($column, $date);
}

function db_now() {
    return DatabaseHelper::now();
}

function db_hour($column) {
    return DatabaseHelper::hourFunction($column);
}

function db_day_of_week($column) {
    return DatabaseHelper::dayOfWeekFunction($column);
}

function db_week($column) {
    return DatabaseHelper::weekFunction($column);
}

function db_month($column) {
    return DatabaseHelper::monthFunction($column);
}

function db_date_sub($column, $interval, $unit) {
    return DatabaseHelper::dateSubtract($column, $interval, $unit);
}

function db_concat($columns) {
    return DatabaseHelper::concat($columns);
}

function db_round($expression, $decimals = 0) {
    return DatabaseHelper::roundFunction($expression, $decimals);
}

function db_format_date($column, $format = '%Y-%m-%d') {
    return DatabaseHelper::formatDate($column, $format);
}
?>
