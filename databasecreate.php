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
                return ;
            }
        }
		return 1;
	}

// function createDatabase($server, $username, $password, $dbname) {
//     try {
//         // 创建无数据库的临时连接
//         // $temp_pdo = new PDO("mysql:host=$server", $username, $password);
//         // $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//         // 执行 schema.sql 中的 SQL 命令
//         $sql_file = __DIR__ . '/../../database/schema.sql'; // 指向 SQL 文件
//         $sql_commands = file_get_contents($sql_file);
        
//         // $temp_pdo->exec("CREATE DATABASE $dbname");
//         // $temp_pdo->exec("USE $dbname");
        
//         // 分割并执行所有 SQL 语句
//         $queries = explode(';', $sql_commands);
//         foreach ($queries as $query) {
//             $query = trim($query);
//             if (!empty($query)) {
//                 $temp_pdo->exec($query);
//             }
//         }
//         return true;
//     } catch (PDOException $e) {
//         error_log("Database creation failed: " . $e->getMessage());
//         return false;
//     }
// }
?>