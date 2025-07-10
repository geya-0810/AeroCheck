<?php
// databasecreate.php

function createDatabase($connect, $dbname)
{
    $sql_file = __DIR__ . '/aero_check_db.sql'; 
    $sql_commands = file_get_contents($sql_file);

    // 分割并执行所有 SQL 语句
    $queries = explode(';', $sql_commands);
    foreach ($queries as $query) {
        try {
            $query = trim($query);
            if ($query == "")
                continue;
            if(!$connect->query($query)) {
                // echo "query failed to run<br>";
                return 0;
            }
        } catch (Exception $e) {
            error_log("Database creation failed: " . $e->getMessage());
            echo "error while running query: $query <br>";
            return 0;
        }
    }
    return 1;
}
?>