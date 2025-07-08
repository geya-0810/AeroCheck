<?php
// databaseconnect.php
$server = 'localhost';       // 使用环境变量，并提供默认值
$username = 'root';
$password = '';
$dbname = 'aero_check_db';
$charset = "utf8mb4";

try{
	$pdo = new PDO("mysql:host=$server;charset=$charset", $username, $password);
} catch (Exception $e){
	die ("Unable to connect to localhost");
}

try {
	$dbconnect = new PDO("mysql:host=$server;dbname=$dbname;charset=$charset", $username, $password);
	$dbconnect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	error_log("Connected to database: $dbname");
	//echo "Database successfully connected";
	return ($dbconnect);
	exit();
} catch (PDOException $e) {
	if (strpos($e, "Unknown database")){
		include (__DIR__ . '/databasecreate.php');
		if (createDatabase($pdo, $dbname))
		{
			//echo "Database successfully created<br><br>";
			$dbconnect = new PDO("mysql:host=$server;dbname=$dbname;charset=$charset", $username, $password);
			return($dbconnect);
			exit();
			// if (!$dbconnect)
			// 	echo "Database $dbname failed to connect<br>";
			// else
			// 	echo "Database $dbname successfully connected<br>";
		}
		else
		{
			try {
				$dbconnect = new PDO("mysql:host=$server;dbname=$dbname;charset=$charset", $username, $password);
				if ($dbconnect)
					$dbconnect->query("DROP DATABASE $dbname");
			} catch(Exception $e){
				// echo "Database has not been created<br>";
				die("Database failed to create:" . $connect->error);
			}
		}
	}
	else {
        //die("Database connection failed.");
    	die("Database connection failed: " . $e->getMessage());
    }
}
// try {
//     // 尝试直接连接目标数据库
//     $dbconnect = new PDO("mysql:host=$server;dbname=$dbname;charset=$charset", $username, $password);
//     $dbconnect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     return ($dbconnect);
// } catch (PDOException $e) {
//     // 如果数据库不存在，则创建
//     if (strpos($e->getMessage(), "Unknown database") !== false) {
//         include (__DIR__ . '/databasecreate.php');  // 使用绝对路径
//         if (createDatabase($server, $username, $password, $dbname)) {
//             // 创建成功后重新连接
//             $dbconnect = new PDO("mysql:host=$server;dbname=$dbname;charset=$charset", $username, $password);
//             return ($dbconnect);
//         } else {
//             die("Failed to create database.");
//         }
//     } else {
//         logError("Database connection failed: " . $e->getMessage()); // 新增日志记录
//         //die("Database connection failed.");
//         die("Database connection failed: " . $e->getMessage());
//     }
// }
?>