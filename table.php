<?php


@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
date_default_timezone_set('Asia/Tehran');
@putenv('TZ=Asia/Tehran');

require_once 'function.php';
require_once 'config.php';
require_once 'botapi.php';
global $connect;


(function () {

    if (php_sapi_name() === 'cli') {
        return;
    }


    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $localIps = ['127.0.0.1', '::1', $_SERVER['SERVER_ADDR'] ?? ''];
    if ($remote !== '' && in_array($remote, $localIps, true)) {
        return;
    }


    $providedToken = (string) ($_GET['token'] ?? $_REQUEST['token'] ?? '');
    if ($providedToken !== '' && function_exists('hash_equals')) {
        $configToken = '';
        if (isset($GLOBALS['admin_panel_token']) && is_string($GLOBALS['admin_panel_token'])) {
            $configToken = trim($GLOBALS['admin_panel_token']);
        }
        if ($configToken !== '' && hash_equals($configToken, $providedToken)) {
            return;
        }

        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT password FROM admin");
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $stored = (string) ($row['password'] ?? '');
                        if ($stored !== '' && hash_equals($stored, $providedToken)) {
                            return;
                        }
                    }
                }
            }
        } catch (Throwable $e) {  }
    }


    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><title>Not Found</title></head><body><h1>Not Found</h1></body></html>';
    exit;
})();


if (!function_exists('rxTableColumnExists')) {
    function rxTableColumnExists($connection, $tableName, $columnName)
    {
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $columnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }
        try {
            $safeColumn = method_exists($connection, 'real_escape_string') ? $connection->real_escape_string($columnName) : $columnName;
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$safeColumn}'");
            if ($result && isset($result->num_rows)) {
                return $result->num_rows > 0;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }
}

if (!function_exists('rxSafeAddColumn')) {
    function rxSafeAddColumn($connection, $tableName, $columnName, $definition)
    {
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        $columnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($tableName === '' || $columnName === '' || rxTableColumnExists($connection, $tableName, $columnName)) {
            return;
        }
        try {
            $connection->query("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}");
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false && stripos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }
}

if (!function_exists('ensureMarzbanGuardFieldsMigrated')) {
    function ensureMarzbanGuardFieldsMigrated()
    {
        static $guardFieldsChecked = false;
        if ($guardFieldsChecked === true) {
            return;
        }
        $guardFieldsChecked = true;

        $guardColumns = [
            ['api_key', null, "VARCHAR(500)"],
            ['guard_service_ids', null, "TEXT"],
            ['guard_note', null, "TEXT"],
            ['guard_auto_delete_days', 0, "INT(11)"],
            ['guard_auto_renewals', null, "TEXT"],
            ['hide_user', null, "TEXT"],
        ];

        foreach ($guardColumns as [$field, $default, $datatype]) {
            addFieldToTable("marzban_panel", $field, $default, $datatype);
        }
    }
}

try {

    $tableName = 'user';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id VARCHAR(500) PRIMARY KEY,
            limit_usertest INT(100) NOT NULL,
            roll_Status BOOL NOT NULL,
            username VARCHAR(500) NOT NULL,
            Processing_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            Processing_value_one TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            Processing_value_tow TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            Processing_value_four TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            step VARCHAR(500) NOT NULL,
            description_blocking TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            number VARCHAR(300) NOT NULL,
            Balance INT(255) NOT NULL,
            User_Status VARCHAR(500) NOT NULL,
            pagenumber INT(10) NOT NULL,
            message_count VARCHAR(100) NOT NULL,
            last_message_time VARCHAR(100) NOT NULL,
            agent VARCHAR(100) NOT NULL,
            affiliatescount VARCHAR(100) NOT NULL,
            affiliates VARCHAR(100) NOT NULL,
            namecustom VARCHAR(300) NOT NULL,
            number_username VARCHAR(300) NOT NULL,
            register VARCHAR(100) NOT NULL,
            verify VARCHAR(100) NOT NULL,
            cardpayment VARCHAR(100) NOT NULL,
            codeInvitation VARCHAR(100) NULL,
            pricediscount VARCHAR(100) NULL   DEFAULT '0',
            hide_mini_app_instruction VARCHAR(20) NULL   DEFAULT '0',
            maxbuyagent VARCHAR(100) NULL   DEFAULT '0',
            joinchannel VARCHAR(100) NULL   DEFAULT '0',
            checkstatus VARCHAR(50) NULL   DEFAULT '0',
            bottype TEXT NULL ,
            score INT(255) NULL DEFAULT '0',
            limitchangeloc VARCHAR(50) NULL   DEFAULT '0',
            status_cron VARCHAR(20)  NULL DEFAULT '1',
            expire VARCHAR(100) NULL ,
            token VARCHAR(100) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable($tableName, 'token', null, "VARCHAR(100)");
        addFieldToTable($tableName, 'status_cron', "1", "VARCHAR(20)");
        addFieldToTable($tableName, 'expire', NULL, "VARCHAR(100)");
        addFieldToTable($tableName, 'limitchangeloc', '0', "VARCHAR(50)");
        addFieldToTable($tableName, 'bottype', '0', "TEXT");
        addFieldToTable($tableName, 'score', '0', "INT(255)");
        addFieldToTable($tableName, 'checkstatus', '0', "VARCHAR(50)");
        addFieldToTable($tableName, 'joinchannel', '0', "VARCHAR(100)");
        addFieldToTable($tableName, 'maxbuyagent', '0');
        addFieldToTable($tableName, 'agent', 'f');
        addFieldToTable($tableName, 'verify', '1');
        addFieldToTable($tableName, 'register', 'none');
        addFieldToTable($tableName, 'namecustom', 'none');
        addFieldToTable($tableName, 'number_username', '100');
        addFieldToTable($tableName, 'cardpayment', '1');
        addFieldToTable($tableName, 'affiliatescount', '0');
        addFieldToTable($tableName, 'affiliates', '0');
        addFieldToTable($tableName, 'message_count', '0');
        addFieldToTable($tableName, 'last_message_time', '0');
        addFieldToTable($tableName, 'Processing_value_four', '');
        addFieldToTable($tableName, 'username', 'none');
        addFieldToTable($tableName, 'Processing_value', 'none');
        addFieldToTable($tableName, 'number', 'none');
        addFieldToTable($tableName, 'pagenumber', '');
        addFieldToTable($tableName, 'codeInvitation', null);
        addFieldToTable($tableName, 'pricediscount', "0");
        addFieldToTable($tableName, 'hide_mini_app_instruction', '0', "VARCHAR(20)");


        addFieldToTable($tableName, 'antispam_window_start', '0', "VARCHAR(20)");
        addFieldToTable($tableName, 'antispam_window_count', '0', "VARCHAR(20)");
        addFieldToTable($tableName, 'antispam_muted_until', '0', "VARCHAR(20)");
    }
} catch (PDOException $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {

    $tableName = 'help';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name_os varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Media_os varchar(5000) NOT NULL,
        type_Media_os varchar(500) NOT NULL,
        category TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Description_os TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable("help", "category", null, "TEXT");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $tableName = 'setting';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $DATAAWARD = json_encode(array(
        'one' => "0",
        "tow" => "0",
        "theree" => "0"
    ));
    $limitlist = json_encode(array(
        'free' => 100,
        'all' => 100,
    ));
    $status_cron = json_encode(array(
        'day' => true,
        'volume' => true,
        'remove' => false,
        'remove_volume' => false,
        'test' => false,
        'on_hold' => false,
        'uptime_node' => false,
        'uptime_panel' => false,
    ));
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        Bot_Status varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        roll_Status varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        get_number varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        iran_number varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        NotUser varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        Channel_Report varchar(600)  NULL,
        limit_usertest_all varchar(600)  NULL,
        affiliatesstatus varchar(600)  NULL,
        affiliatespercentage varchar(600)  NULL,
        removedayc varchar(600)  NULL,
        showcard varchar(200)  NULL,
        numbercount varchar(600)  NULL,
        statusnewuser varchar(600)  NULL,
        statusagentrequest varchar(600)  NULL,
        statuscategory varchar(200)  NULL,
        statusterffh varchar(200)  NULL,
        volumewarn varchar(200)  NULL,
        inlinebtnmain varchar(200)  NULL,
        verifystart varchar(200)  NULL,
        id_support varchar(200)  NULL,
        statusnamecustom varchar(100)  NULL,
        statuscategorygenral varchar(100)  NULL,
        statussupportpv varchar(100)  NULL,
        agentreqprice varchar(100)  NULL,
        bulkbuy varchar(100)  NULL,
        on_hold_day varchar(100)  NULL,
        cronvolumere varchar(100)  NULL,
        verifybucodeuser varchar(100)  NULL,
        scorestatus varchar(100)  NULL,
        Lottery_prize TEXT  NULL,
        wheelـluck varchar(45)  NULL,
        wheelـluck_price varchar(45)  NULL,
        btn_status_extned varchar(45)  NULL,
        daywarn varchar(45)  NULL,
        categoryhelp varchar(45)  NULL,
        linkappstatus varchar(45)  NULL,
        iplogin TEXT  NULL,
        wheelagent varchar(45)  NULL,
        Lotteryagent varchar(45)  NULL,
        languageen varchar(45)  NULL,
        languageru varchar(45)  NULL,
        statusfirstwheel varchar(45)  NULL,
        statuslimitchangeloc varchar(45)  NULL,
        Debtsettlement varchar(45)  NULL,
        Dice varchar(45) NULL,
        keyboardmain TEXT NOT NULL,
        statusnoteforf varchar(45) NOT NULL,
        statuscopycart varchar(45) NOT NULL,
        timeauto_not_verify varchar(20) NOT NULL,
        status_keyboard_config varchar(20)  NULL,
        cron_status TEXT NOT NULL,
        limitnumber varchar(200)  NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO setting (Bot_Status,roll_Status,get_number,limit_usertest_all,iran_number,NotUser,affiliatesstatus,affiliatespercentage,removedayc,showcard,statuscategory,numbercount,statusnewuser,statusagentrequest,volumewarn,inlinebtnmain,verifystart,statussupportpv,statusnamecustom,statuscategorygenral,agentreqprice,cronvolumere,bulkbuy,on_hold_day,verifybucodeuser,scorestatus,Lottery_prize,wheelـluck,wheelـluck_price,iplogin,daywarn,categoryhelp,linkappstatus,languageen,languageru,wheelagent,Lotteryagent,statusfirstwheel,statuslimitchangeloc,limitnumber,Debtsettlement,Dice,keyboardmain,statusnoteforf,statuscopycart,timeauto_not_verify,status_keyboard_config,cron_status) VALUES ('botstatuson','rolleon','offAuthenticationphone','1','offAuthenticationiran','offnotuser','offaffiliates','0','0','1','offcategory','0','onnewuser','onrequestagent','2','offinline','offverify','offpvsupport','offnamecustom','offcategorys','0','5','onbulk','4','offverify','0','$DATAAWARD','0','0','0','2','0','0','0','0','1','1','0','0','$limitlist','1','0','$keyboardmain','1','0','4','1','$status_cron')");
        $stmt->execute();
    } else {
        addFieldToTable("setting", "cron_status", $status_cron, "TEXT");
        addFieldToTable("setting", "status_keyboard_config", "1", "varchar(20)");
        addFieldToTable("setting", "statusnoteforf", "1", "varchar(20)");
        addFieldToTable("setting", "timeauto_not_verify", "4", "varchar(20)");
        addFieldToTable("setting", "statuscopycart", "0", "varchar(20)");
        addFieldToTable("setting", "keyboardmain", $keyboardmain, "TEXT");
        addFieldToTable("setting", "Dice", '0', "varchar(45)");
        addFieldToTable("setting", "Debtsettlement", '1', "varchar(45)");
        addFieldToTable("setting", "limitnumber", $limitlist, "varchar(200)");
        addFieldToTable("setting", "statuslimitchangeloc", "0", "varchar(45)");
        addFieldToTable("setting", "statusfirstwheel", "0", "varchar(45)");
        addFieldToTable("setting", "Lotteryagent", "1", "varchar(45)");
        addFieldToTable("setting", "wheelagent", "1", "varchar(45)");
        addFieldToTable("setting", "languageru", "0", "varchar(45)");
        addFieldToTable("setting", "languageen", "0", "varchar(45)");
        addFieldToTable("setting", "linkappstatus", "0", "varchar(45)");
        addFieldToTable("setting", "categoryhelp", "0", "varchar(45)");
        addFieldToTable("setting", "daywarn", "2", "varchar(45)");
        addFieldToTable("setting", "btn_status_extned", "0", "varchar(45)");
        addFieldToTable("setting", "iplogin", "[]", "TEXT");
        $stmt_ip = $pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'setting' AND COLUMN_NAME = 'iplogin'");
        $stmt_ip->execute();
        $ip_col_type = $stmt_ip->fetchColumn();
        if ($ip_col_type && strtolower($ip_col_type) !== 'text' && strtolower($ip_col_type) !== 'longtext' && strtolower($ip_col_type) !== 'mediumtext') {
            $pdo->exec("ALTER TABLE setting MODIFY COLUMN iplogin TEXT NULL");
            $stmt_migrate = $pdo->query("SELECT iplogin FROM setting LIMIT 1");
            $cur_val = $stmt_migrate ? $stmt_migrate->fetchColumn() : null;
            if ($cur_val !== null && $cur_val !== '' && $cur_val !== '0' && $cur_val !== '[]') {
                $decoded_check = json_decode($cur_val, true);
                if (!is_array($decoded_check) && filter_var($cur_val, FILTER_VALIDATE_IP)) {
                    $new_val = json_encode([$cur_val]);
                    $pdo->prepare("UPDATE setting SET iplogin = ?")->execute([$new_val]);
                }
            }
            echo "iplogin column migrated to TEXT ✅<br>";
        }
        addFieldToTable("setting", "wheelـluck_price", "0", "varchar(45)");
        addFieldToTable("setting", "wheelـluck", "0", "varchar(45)");
        addFieldToTable("setting", "Lottery_prize", $DATAAWARD, "TEXT");
        addFieldToTable("setting", "scorestatus", "0", "VARCHAR(100)");
        addFieldToTable("setting", "verifybucodeuser", "offverify", "VARCHAR(100)");
        addFieldToTable("setting", "on_hold_day", "4", "VARCHAR(100)");
        addFieldToTable("setting", "bulkbuy", "onbulk", "VARCHAR(100)");
        addFieldToTable("setting", "statuscategorygenral", "offcategorys", "VARCHAR(100)");
        addFieldToTable("setting", "cronvolumere", "5", "VARCHAR(100)");
        addFieldToTable("setting", "agentreqprice", "0", "VARCHAR(100)");
        addFieldToTable("setting", "statusnamecustom", "offnamecustom", "VARCHAR(100)");
        addFieldToTable("setting", "id_support", "0", "VARCHAR(100)");
        addFieldToTable("setting", "statussupportpv", "offpvsupport", "VARCHAR(100)");
        addFieldToTable("setting", "affiliatespercentage", "0", "VARCHAR(600)");
        addFieldToTable("setting", "inlinebtnmain", "offinline", "VARCHAR(200)");
        addFieldToTable("setting", "volumewarn", "2", "VARCHAR(200)");
        addFieldToTable("setting", "statusagentrequest", "onrequestagent", "VARCHAR(600)");
        addFieldToTable("setting", "statusnewuser", "onnewuser", "VARCHAR(600)");
        addFieldToTable("setting", "numbercount", "0", "VARCHAR(600)");
        addFieldToTable("setting", "statuscategory", "offcategory", "VARCHAR(600)");
        addFieldToTable("setting", "showcard", "1", "VARCHAR(200)");
        addFieldToTable("setting", "removedayc", "1", "VARCHAR(200)");
        addFieldToTable("setting", "affiliatesstatus", "offaffiliates", "VARCHAR(600)");
        addFieldToTable("setting", "NotUser", "offnotuser", "VARCHAR(200)");
        addFieldToTable("setting", "iran_number", "offAuthenticationiran", "VARCHAR(200)");
        addFieldToTable("setting", "get_number", "onAuthenticationphone", "VARCHAR(200)");
        addFieldToTable("setting", "limit_usertest_all", "1", "VARCHAR(200)");
        addFieldToTable("setting", "Channel_Report", "0", "VARCHAR(200)");
        addFieldToTable("setting", "Bot_Status", "botstatuson", "VARCHAR(200)");
        addFieldToTable("setting", "roll_Status", "rolleon", "VARCHAR(200)");
        addFieldToTable("setting", "verifystart", "offverify", "VARCHAR(200)");

        addFieldToTable("setting", "premium_emoji_status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "keyboard_styles_all", "{}", "TEXT");


        addFieldToTable("setting", "antispam_status", "0", "VARCHAR(20)");
        addFieldToTable("setting", "antispam_msg_count", "5", "VARCHAR(20)");
        addFieldToTable("setting", "antispam_seconds", "3", "VARCHAR(20)");
        addFieldToTable("setting", "antispam_mute_seconds", "5", "VARCHAR(20)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {
    $tableName = 'admin';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        id_admin varchar(500) PRIMARY KEY NOT NULL,
        username varchar(1000) NOT NULL,
        password varchar(1000) NOT NULL,
        rule varchar(500) NOT NULL)");
        $stmt->execute();
        $randomString = bin2hex(random_bytes(5));


        $stmt = $pdo->prepare("INSERT INTO admin (id_admin, rule, username, password) VALUES (:id, :rule, :username, :password)");
        $stmt->execute([
            ':id'       => (string) $adminnumber,
            ':rule'     => 'administrator',
            ':username' => 'admin',
            ':password' => $randomString,
        ]);
    } else {
        addFieldToTable("admin", "rule", "administrator", "VARCHAR(200)");
        addFieldToTable("admin", "username", null, "VARCHAR(200)");
        addFieldToTable("admin", "password", null, "VARCHAR(200)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $tableName = 'channels';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            remark varchar(200) NOT NULL,
            linkjoin varchar(200) NOT NULL,
            link varchar(200) NOT NULL)");
        $stmt->execute();
    } else {
        addFieldToTable("channels", "remark", null, "VARCHAR(200)");
        addFieldToTable("channels", "linkjoin", null, "VARCHAR(200)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'marzban_panel'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE marzban_panel (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_panel varchar(200) NULL,
        name_panel varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        status varchar(500) NULL,
        url_panel varchar(2000) NULL,
        username_panel varchar(200) NULL,
        password_panel varchar(200) NULL,
        api_key varchar(500) NULL,
        agent varchar(200) NULL,
        sublink varchar(500) NULL,
        config varchar(500) NULL,
        MethodUsername varchar(700) NULL,
        TestAccount varchar(100) NULL,
        limit_panel varchar(100) NULL,
        namecustom varchar(100) NULL,
        Methodextend varchar(100) NULL,
        conecton varchar(100) NULL,
        linksubx varchar(1000) NULL,
        inboundid varchar(100) NULL,
        type varchar(100) NULL,
        inboundstatus varchar(100) NULL,
        hosts  JSON  NULL,
        inbound_deactive varchar(100) NULL,
        time_usertest varchar(100) NULL,
        val_usertest varchar(100)  NULL,
        secret_code varchar(200) NULL,
        priceChangeloc varchar(200) NULL,
        priceextravolume varchar(500) NULL,
        pricecustomvolume varchar(500) NULL,
        pricecustomtime varchar(500) NULL,
        priceextratime varchar(500) NULL,
        mainvolume varchar(500) NULL,
        maxvolume varchar(500) NULL,
        maintime varchar(500) NULL,
        maxtime varchar(500) NULL,
        status_extend varchar(100) NULL,
        datelogin TEXT NULL,
        proxies TEXT NULL,
        inbounds TEXT NULL,
        subvip varchar(60) NULL,
        changeloc varchar(60) NULL,
        on_hold_test varchar(60) NOT NULL,
        version_panel varchar(60) NOT NULL DEFAULT '0',
        customvolume TEXT NULL,
        hide_user TEXT NULL,
        guard_service_ids TEXT NULL,
        guard_note TEXT NULL,
        guard_auto_delete_days INT(11) NULL,
        guard_auto_renewals TEXT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table marzban_panel: " . mysqli_error($connect));
        }
    } else {
        $VALUE = json_encode(array(
            'f' => '0',
            'n' => '0',
            'n2' => '0'
        ));
        $valueprice = json_encode(array(
            'f' => "4000",
            'n' => "4000",
            'n2' => "4000"
        ));
        $valuemain = json_encode(array(
            'f' => "1",
            'n' => "1",
            'n2' => "1"
        ));
        $valuemax = json_encode(array(
            'f' => "1000",
            'n' => "1000",
            'n2' => "1000"
        ));
        $valuemax_time = json_encode(array(
            'f' => "365",
            'n' => "365",
            'n2' => "365"
        ));
        addFieldToTable("marzban_panel", "on_hold_test", "1", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "version_panel", "0", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "proxies", null, "TEXT");
        addFieldToTable("marzban_panel", "inbounds", null, "TEXT");
        addFieldToTable("marzban_panel", "api_key", null, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "customvolume", $VALUE, "TEXT");
        addFieldToTable("marzban_panel", "subvip", "offsubvip", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "changeloc", "offchangeloc", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "hide_user", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_service_ids", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_note", null, "TEXT");
        addFieldToTable("marzban_panel", "guard_auto_delete_days", 0, "INT(11)");
        addFieldToTable("marzban_panel", "guard_auto_renewals", null, "TEXT");
        addFieldToTable("marzban_panel", "status_extend", "on_extend", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "code_panel", null, "VARCHAR(50)");
        addFieldToTable("marzban_panel", "priceextravolume", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "pricecustomvolume", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "pricecustomtime", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "priceextratime", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "priceChangeloc", "0", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "mainvolume", $valuemain, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maxvolume", $valuemax, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maintime", $valuemain, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maxtime", $valuemax_time, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "MethodUsername", "آیدی عددی + حروف و عدد رندوم", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "datelogin", null, "TEXT");
        addFieldToTable("marzban_panel", "val_usertest", "100", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "time_usertest", "1", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "secret_code", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "inboundstatus", "offinbounddisable", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "inbound_deactive", "0", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "agent", "all", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "inboundid", "1", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "linksubx", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "conecton", "offconecton", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "type", "marzban", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "Methodextend", "ریست حجم و زمان", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "namecustom", "vpn", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "limit_panel", "unlimted", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "TestAccount", "ONTestAccount", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "status", "active", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "sublink", "onsublink", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "config", "offconfig", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "emergency_panel_status", "off_emergency_panel", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "national_net_status", "off_national_net", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "emergency_source_panel", null, "VARCHAR(191)");
        addFieldToTable("marzban_panel", "stock_source_panel", null, "VARCHAR(191)");
    }
    ensureMarzbanGuardFieldsMigrated();
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'product'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE product (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_product varchar(200)  NULL,
        name_product varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price_product varchar(2000) NULL,
        Volume_constraint varchar(2000) NULL,
        Location varchar(200) NULL,
        Service_time varchar(200) NULL,
        agent varchar(100) NULL,
        note TEXT NULL,
        data_limit_reset varchar(200) NULL,
        one_buy_status varchar(20) NOT NULL,
        inbounds TEXT NULL,
        proxies TEXT NULL,
        category varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        hide_panel TEXT  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table product: " . mysqli_error($connect));
        }
    } else {
        addFieldToTable("product", "one_buy_status", "0", "VARCHAR(20)");
        addFieldToTable("product", "Location", null, "VARCHAR(200)");
        addFieldToTable("product", "inbounds", null, "TEXT");
        addFieldToTable("product", "proxies", null, "TEXT");
        addFieldToTable("product", "category", null, "varchar(200)");
        addFieldToTable("product", "note", '', "TEXT");
        addFieldToTable("product", "hide_panel", '{}', "TEXT");
        addFieldToTable("product", "data_limit_reset", "no_reset", "varchar(100)");
        addFieldToTable("product", "agent", "f", "varchar(50)");
        addFieldToTable("product", "code_product", null, "varchar(50)");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'invoice'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE invoice (
        id_invoice varchar(200) PRIMARY KEY,
        id_user varchar(200) NULL,
        username varchar(300) NULL,
        Service_location varchar(300) NULL,
        time_sell VARCHAR(200) NULL,
        name_product varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price_product varchar(200) NULL,
        Volume varchar(200) NULL,
        Service_time varchar(200) NULL,
        uuid TEXT NULL,
        note varchar(500) NULL,
        user_info TEXT NULL,
        bottype varchar(200) NULL,
        refral varchar(100) NULL,
        time_cron varchar(100) NULL,
        notifctions TEXT NOT NULL,
        Status varchar(200) NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table invoice: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'note'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD note VARCHAR(700)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'notifctions'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $data = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            $result = $connect->query("ALTER TABLE invoice ADD notifctions TEXT NOT NULL");
            $connect->query("UPDATE invoice SET notifctions = '$data'");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'time_cron'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD time_cron VARCHAR(100)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'refral'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD refral VARCHAR(100)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'bottype'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD bottype VARCHAR(200)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'user_info'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD user_info TEXT");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'time_sell'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD time_sell VARCHAR(200)");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'uuid'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD uuid TEXT");
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM invoice LIKE 'Status'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $result = $connect->query("ALTER TABLE invoice ADD Status VARCHAR(100)");
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'Payment_report'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE Payment_report (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        id_order varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        time varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        at_updated varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        dec_not_confirmed TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        Payment_Method varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        payment_Status varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        bottype varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        message_id INT NULL,
        id_invoice varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table Payment_report: " . mysqli_error($connect));
        }
    } else {
        ensureTableUtf8mb4('Payment_report');
        addFieldToTable("Payment_report", "message_id", null, "INT");
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'Payment_Method'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD Payment_Method VARCHAR(200)");
            echo "The Payment_Method field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'bottype'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD bottype VARCHAR(300)");
            echo "The bottype field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'at_updated'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD at_updated VARCHAR(200)");
            echo "The at_updated field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'id_invoice'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD id_invoice VARCHAR(400)");
            $connect->query("UPDATE Payment_report SET id_invoice = 'none'");
            echo "The id_invoice field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Payment_report LIKE 'source'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Payment_report ADD source VARCHAR(20) NULL");
            $connect->query("ALTER TABLE Payment_report ADD INDEX idx_paymentreport_source (source)");
            echo "The source field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'Discount'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE Discount (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code varchar(2000) NULL,
        price varchar(200) NULL,
        limituse varchar(200) NULL,
        limitused varchar(200) NULL)
        ");
        if (!$result) {
            error_log("[table.php] table Discount: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM Discount LIKE 'limituse'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Discount ADD limituse VARCHAR(200)");
            echo "The limituse field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM Discount LIKE 'limitused'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE Discount ADD limitused VARCHAR(200)");
            echo "The limitused field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $result = $connect->query("SHOW TABLES LIKE 'Giftcodeconsumed'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE  Giftcodeconsumed (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code varchar(2000) NULL,
        id_user varchar(200) NULL)");
        if (!$result) {
            error_log("[table.php] table Giftcodeconsumed: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'textbot'");
    $table_exists = ($result->num_rows > 0);
    $text_roll = "
♨️ قوانین استفاده از خدمات ما

1- به اطلاعیه هایی که داخل کانال گذاشته می شود حتما توجه کنید.
2- در صورتی که اطلاعیه ای در مورد قطعی در کانال گذاشته نشده به اکانت پشتیبانی پیام دهید
3- سرویس ها را از طریق پیامک ارسال نکنید برای ارسال پیامک می توانید از طریق ایمیل ارسال کنید.
    ";
    $text_dec_fq = "
 💡 سوالات متداول ⁉️

1️⃣ فیلترشکن شما آیپی ثابته؟ میتونم برای صرافی های ارز دیجیتال استفاده کنم؟

✅ به دلیل وضعیت نت و محدودیت های کشور سرویس ما مناسب ترید نیست و فقط لوکیشن‌ ثابته.

2️⃣ اگه قبل از منقضی شدن اکانت، تمدیدش کنم روزهای باقی مانده می سوزد؟

✅ خیر، روزهای باقیمونده اکانت موقع تمدید حساب میشن و اگه مثلا 5 روز قبل از منقضی شدن اکانت 1 ماهه خودتون اون رو تمدید کنید 5 روز باقیمونده + 30 روز تمدید میشه.

3️⃣ اگه به یک اکانت بیشتر از حد مجاز متصل شیم چه اتفاقی میافته؟

✅ در این صورت حجم سرویس شما زود تمام خواهد شد.

4️⃣ فیلترشکن شما از چه نوعیه؟

✅ فیلترشکن های ما v2ray است و پروتکل‌های مختلفی رو ساپورت میکنیم تا حتی تو دورانی که اینترنت اختلال داره بدون مشکل و افت سرعت بتونید از سرویستون استفاده کنید.

5️⃣ فیلترشکن از کدوم کشور است؟

✅ سرور فیلترشکن ما از کشور  آلمان است

6️⃣ چطور باید از این فیلترشکن استفاده کنم؟

✅ برای آموزش استفاده از برنامه، روی دکمه «📚 آموزش» بزنید.

7️⃣ فیلترشکن وصل نمیشه، چیکار کنم؟

✅ به همراه یک عکس از پیغام خطایی که میگیرید به پشتیبانی مراجعه کنید.

8️⃣ فیلترشکن شما تضمینی هست که همیشه مواقع متصل بشه؟

✅ به دلیل قابل پیش‌بینی نبودن وضعیت نت کشور، امکان دادن تضمین نیست فقط می‌تونیم تضمین کنیم که تمام تلاشمون رو برای ارائه سرویس هر چه بهتر انجام بدیم.

9️⃣ امکان بازگشت وجه دارید؟

✅ امکان بازگشت وجه در صورت حل نشدن مشکل از سمت ما وجود دارد.

💡 در صورتی که جواب سوالتون رو نگرفتید میتونید به «پشتیبانی» مراجعه کنید.";
    $text_channel = "
        ⚠️ کاربر گرامی؛ شما عضو چنل ما نیستید
از طریق دکمه زیر وارد کانال شده و عضو شوید
پس از عضویت دکمه بررسی عضویت را کلیک کنید";
    $text_invoice = "📇 پیش فاکتور شما:
👤 نام کاربری:  {username}
🔐 نام سرویس: {name_product}
📆 مدت اعتبار: {Service_time} روز
💶 قیمت:  {price} تومان
👥 حجم اکانت: {Volume} گیگ
🗒 یادداشت محصول : {note}
💵 موجودی کیف پول شما : {userBalance}

💰 سفارش شما آماده پرداخت است";
    $textafterpay = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

{connection_links}
🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $text_wgdashboard = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textafterpayibsng = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🔑 رمز عبور سرویس :  <code>{password}</code>
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textmanual = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}

 اطلاعات سرویس :
{connection_links}
🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textaftertext = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

{connection_links}
🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textconfigtest = "با سلام خدمت شما کاربر گرامی
سرویس تست شما با نام کاربری {username} به پایان رسیده است
امیدواریم تجربه‌ی خوبی از آسودگی و سرعت سرویستون داشته باشین. در صورتی که از سرویس‌ تست خودتون راضی بودین، میتونید سرویس اختصاصی خودتون رو تهیه کنید و از داشتن اینترنت آزاد با نهایت کیفیت لذت ببرید😉🔥
🛍 برای تهیه سرویس با کیفیت می توانید از دکمه زیر استفاده نمایید";
    $textcart = "برای افزایش موجودی، مبلغ <code>{price}</code>  تومان  را به شماره‌ی حساب زیر واریز کنید 👇🏻

        ====================
        <code>{card_number}</code>
        {name_card}
        ====================

❌ این تراکنش به مدت ۳۰ دقیقه (نیم ساعت) اعتبار دارد و پس از آن امکان پرداخت این تراکنش وجود نخواهد داشت.
‼مبلغ باید همان مبلغی که در بالا ذکر شده واریز نمایید.
‼️امکان برداشت وجه از کیف پول نیست.
‼️مسئولیت واریز اشتباهی با شماست.
🔝بعد از پرداخت  دکمه پرداخت کردم را زده سپس تصویر رسید را ارسال نمایید
💵بعد از تایید پرداختتون توسط ادمین کیف پول شما شارژ خواهد شد و در صورتی که سفارشی داشته باشین انجام خواهد شد";
    $textcartauto = "برای تایید فوری لطفا دقیقاً مبلغ زیر واریز شود. در غیر این صورت تایید پرداخت شما ممکن است با تاخیر مواجه شود.⚠️
            برای افزایش موجودی، مبلغ <code>{price}</code>  ریال  را به شماره‌ی حساب زیر واریز کنید 👇🏻

        ====================
        <code>{card_number}</code>
        {name_card}
        ====================

💰دقیقا مبلغی را که در بالا ذکر شده واریز نمایید تا بصورت آنی تایید شود.
‼️امکان برداشت وجه از کیف پول نیست.
🔝لزومی به ارسال رسید نیست، اما در صورتی که بعد از گذشت مدتی واریز شما تایید نشد، عکس رسید خود را ارسال کنید.";
    $insertQueries = [
        ['text_start', 'سلام خوش آمدید'],
        ['text_usertest', '🔑 اکانت تست'],
        ['text_Purchased_services', '🛍 سرویس های من'],
        ['text_support', '☎️ پشتیبانی'],
        ['text_help', '📚 آموزش'],
        ['text_bot_off', '❌ ربات خاموش است، لطفا دقایقی دیگر مراجعه کنید'],
        ['text_roll', $text_roll],
        ['text_fq', '❓ سوالات متداول'],
        ['text_dec_fq', $text_dec_fq],
        ['text_sell', '🔐 خرید اشتراک'],
        ['text_Add_Balance', '💰 افزایش موجودی'],
        ['text_channel', $text_channel],
        ['text_Discount', '🎁 کد هدیه'],
        ['text_Tariff_list', '💵 تعرفه اشتراک ها'],
        ['text_dec_Tariff_list', 'تنظیم نشده است'],
        ['text_Account_op', '🎛 حساب کاربری'],
        ['text_affiliates', '👥 زیر مجموعه گیری'],
        ['text_pishinvoice', $text_invoice],
        ['accountwallet', '🏦 کیف پول + شارژ'],
        ['carttocart', '💳 کارت به کارت'],
        ['textnowpayment', '💵 پرداخت ارزی 1'],
        ['textnowpaymenttron', '💵 واریز رمزارز ترون'],
        ['textsnowpayment', '💸 پرداخت با ارز دیجیتال'],
        ['iranpay1', '💸 درگاه  پرداخت ریالی'],
        ['iranpay2', '💸 درگاه  پرداخت ریالی دوم'],
        ['iranpay3', '💸 درگاه  پرداخت ریالی سوم'],
        ['aqayepardakht', '🔵 درگاه آقای پرداخت'],
        ['zarinpey', '🟠 زرین پی'],
        ['mowpayment', '💸 پرداخت با ارز دیجیتال'],
        ['zarinpal', '🟡 زرین پال'],
        ['textafterpay', $textafterpay],
        ['textafterpayibsng', $textafterpayibsng],
        ['textaftertext', $textaftertext],
        ['textmanual', $textmanual],
        ['textselectlocation', '📌 موقعیت سرویس را انتخاب نمایید.'],
        ['crontest', $textconfigtest],
        ['textpaymentnotverify', 'درگاه ریالی'],
        ['textrequestagent', '👨‍💻 درخواست نمایندگی'],
        ['textpanelagent', '👨‍💻 پنل نمایندگی'],
        ['text_wheel_luck', '🎲 گردونه شانس'],
        ['text_cart', $textcart],
        ['text_cart_auto', $textcartauto],
        ['text_star_telegram', "💫 Star Telegram"],
        ['text_request_agent_dec', '📌 توضیحات خود را برای ثبت درخواست نمایندگی ارسال نمایید.'],
        ['text_extend', '♻️ تمدید سرویس'],
        ['text_wgdashboard', $text_wgdashboard]
    ];
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE textbot (
        id_text varchar(600) PRIMARY KEY NOT NULL,
        text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table textbot: " . mysqli_error($connect));
        }

        foreach ($insertQueries as $query) {
            $connect->query("INSERT INTO textbot (id_text, text) VALUES ('$query[0]', '$query[1]')");
        }
    } else {
        foreach ($insertQueries as $query) {
            $connect->query("INSERT IGNORE INTO textbot (id_text, text) VALUES ('$query[0]', '$query[1]')");
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'PaySetting'");
    $table_exists = ($result->num_rows > 0);
    $main = 20000;
    $max = 1000000;
    $settings = [
        ['Cartstatus', 'oncard'],
        ['CartDirect', '@cart'],
        ['cardnumber', '603700000000'],
        ['namecard', 'تنظیم نشده'],
        ['Cartstatuspv', 'offcardpv'],
        ['apinowpayment', '0'],
        ['api_plisio', '0'],
        ['api_nowpayment', '0'],
        ['nowpaymentstatus', 'offnowpayment'],
        ['digistatus', 'offdigi'],
        ['statusSwapWallet', 'offnSolutions'],
        ['statusaqayepardakht', 'offaqayepardakht'],
        ['merchant_id_aqayepardakht', '0'],
        ['minbalance', '20000'],
        ['maxbalance', '1000000'],
        ['marchent_tronseller', '0'],
        ['walletaddress', ''],
        ['statuscardautoconfirm', 'offautoconfirm'],
        ['urlpaymenttron', 'https://bot.tronado.cloud/api/v1/Order/GetOrderToken'],
        ['statustarnado', 'offternado'],
        ['apiternado', '0'],
        ['chashbackcart', '0'],
        ['chashbackstar', '0'],
        ['chashbackperfect', '0'],
        ['chashbackaqaypardokht', '0'],
        ['chashbackiranpay1', '0'],
        ['chashbackiranpay2', '0'],
        ['chashbackplisio', '0'],
        ['chashbackzarinpal', '0'],
        ['chashbackzarinpey', '0'],
        ['checkpaycartfirst', 'offpayverify'],
        ['zarinpalstatus', 'offzarinpal'],
        ['merchant_zarinpal', '0'],
        ['zarinpeystatus', 'offzarinpey'],
        ['token_zarinpey', '0'],
        ['minbalancecart', $main],
        ['maxbalancecart', $max],
        ['minbalancestar', $main],
        ['maxbalancestar', $max],
        ['minbalanceplisio', $main],
        ['maxbalanceplisio', $max],
        ['minbalancedigitaltron', $main],
        ['maxbalancedigitaltron', $max],
        ['minbalanceiranpay1', $main],
        ['maxbalanceiranpay1', $max],
        ['minbalanceiranpay2', $main],
        ['maxbalanceiranpay2', $max],
        ['minbalanceaqayepardakht', $main],
        ['maxbalanceaqayepardakht', $max],
        ['minbalancepaynotverify', $main],
        ['maxbalancepaynotverify', $max],
        ['minbalanceperfect', $main],
        ['maxbalanceperfect', $max],
        ['minbalancezarinpal', $main],
        ['maxbalancezarinpal', $max],
        ['minbalancezarinpey', $main],
        ['maxbalancezarinpey', $max],
        ['minbalanceiranpay', $main],
        ['maxbalanceiranpay', $max],
        ['minbalancenowpayment', $main],
        ['maxbalancenowpayment', $max],
        ['statusiranpay3', 'oniranpay3'],
        ['apiiranpay', '0'],
        ['chashbackiranpay3', '0'],
        ['helpcart', '2'],
        ['helpaqayepardakht', '2'],
        ['helpstar', '2'],
        ['helpplisio', '2'],
        ['helpiranpay1', '2'],
        ['helpiranpay2', '2'],
        ['helpiranpay3', '2'],
        ['helpperfectmony', '2'],
        ['helpzarinpal', '2'],
        ['helpzarinpey', '2'],
        ['helpnowpayment', '2'],
        ['helpofflinearze', '2'],
        ['autoconfirmcart', 'offauto'],
        ['cashbacknowpayment', '0'],
        ['statusstar', '0'],
        ['statusnowpayment', '0'],
        ['Exception_auto_cart', '{}'],
        ['marchent_floypay', '0'],
        ['nowpayment_ipn_secret', ''],
    ];
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE PaySetting (
        NamePay varchar(500) PRIMARY KEY NOT NULL,
        ValuePay TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table PaySetting: " . mysqli_error($connect));
        }

        foreach ($settings as $setting) {
            $connect->query("INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$setting[0]}', '{$setting[1]}')");
        }
    } else {
        foreach ($settings as $setting) {
            $connect->query("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES ('{$setting[0]}', '{$setting[1]}')");
        }


    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'DiscountSell'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE DiscountSell (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codeDiscount varchar(1000)  NOT NULL,
        price varchar(200)  NOT NULL,
        limitDiscount varchar(500)  NOT NULL,
        agent varchar(500)  NOT NULL,
        usefirst varchar(100)  NOT NULL,
        useuser varchar(100)  NOT NULL,
        code_product varchar(100)  NOT NULL,
        code_panel varchar(100)  NOT NULL,
        time varchar(100)  NOT NULL,
        type varchar(100)  NOT NULL,
        usedDiscount varchar(500) NOT NULL)");
        if (!$result) {
            error_log("[table.php] table DiscountSell: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'agent'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD agent VARCHAR(100)");
            echo "The agent discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'type'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD type VARCHAR(100)");
            echo "The agent type field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'time'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD time VARCHAR(100)");
            echo "The agent time field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'code_panel'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD code_panel VARCHAR(100)");
            echo "The code_panel discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'code_product'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD code_product VARCHAR(100)");
            echo "The code_product discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'useuser'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD useuser VARCHAR(100)");
            echo "The useuser discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM DiscountSell LIKE 'usefirst'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE DiscountSell ADD usefirst VARCHAR(100)");
            echo "The usefirst discount field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'affiliates'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE affiliates (
        description TEXT  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        status_commission varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        Discount varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        price_Discount varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        porsant_one_buy varchar(100),
        id_media varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table affiliates: " . mysqli_error($connect));
        }
        $connect->query("INSERT INTO affiliates (description,id_media,status_commission,Discount,porsant_one_buy) VALUES ('none','none','oncommission','onDiscountaffiliates','off_buy_porsant')");
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'porsant_one_buy'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD porsant_one_buy VARCHAR(100)");
            $connect->query("UPDATE affiliates SET porsant_one_buy = 'off_buy_porsant'");
            echo "The Discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'Discount'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD Discount VARCHAR(100)");
            $connect->query("UPDATE affiliates SET Discount = 'onDiscountaffiliates'");
            echo "The Discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'price_Discount'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD price_Discount VARCHAR(100)");
            echo "The price_Discount field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM affiliates LIKE 'status_commission'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE affiliates ADD status_commission VARCHAR(100)");
            $connect->query("UPDATE affiliates SET status_commission = 'oncommission'");
            echo "The commission field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'shopSetting'");
    $table_exists = ($result->num_rows > 0);
    $agent_cashback = json_encode(array(
        'n' => 0,
        'n2' => 0
    ));
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE shopSetting (
        Namevalue varchar(500) PRIMARY KEY NOT NULL,
        value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table shopSetting: " . mysqli_error($connect));
        }
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmef','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmen','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmen2','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusextra','offextra')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricef','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricen','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricen2','4000')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusdirectpabuy','ondirectbuy')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('minbalancebuybulk','0')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statustimeextra','ontimeextraa')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusdisorder','offdisorder')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice','onstatus')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusshowprice','offshowprice')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('configshow','onconfig')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('backserviecstatus','on')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend','0')");
        $connect->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_agent','$agent_cashback')");
    } else {
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmef','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmen','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmen2','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusextra','offextra')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusdirectpabuy','ondirectbuy')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('minbalancebuybulk','0')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statustimeextra','ontimeextraa')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricef','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricen','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricen2','4000')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusdisorder','offdisorder')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice','onstatus')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusshowprice','offshowprice')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('configshow','onconfig')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('backserviecstatus','on')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend','0')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_agent','$agent_cashback')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('miniapp_short_name','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_name','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_mark','')");
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('brand_logo','')");


    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'cancel_service'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE cancel_service (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500)  NOT NULL,
        username varchar(1000)  NOT NULL,
        description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        status varchar(1000)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table cancel_service: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'service_other'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE service_other (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500)  NOT NULL,
        username varchar(1000)  NOT NULL,
        value varchar(1000)  NOT NULL,
        time varchar(200)  NOT NULL,
        price varchar(200)  NOT NULL,
        type varchar(1000)  NOT NULL,
        status varchar(200)  NOT NULL,
        output TEXT  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table service_other: " . mysqli_error($connect));
        }
    } else {
        $Check_filde = $connect->query("SHOW COLUMNS FROM service_other LIKE 'price'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE service_other ADD price VARCHAR(200)");
            echo "The price field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM service_other LIKE 'status'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE service_other ADD status VARCHAR(200)");
            echo "The status field was added ✅";
        }
        $Check_filde = $connect->query("SHOW COLUMNS FROM service_other LIKE 'output'");
        if (mysqli_num_rows($Check_filde) != 1) {
            $connect->query("ALTER TABLE service_other ADD output TEXT");
            echo "The output field was added ✅";
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'card_number'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE card_number (
        cardnumber varchar(500) PRIMARY KEY,
        namecard  varchar(1000)  NOT NULL)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table x_ui: " . mysqli_error($connect));
        }
    }
    $columnInfo = $connect->query("SHOW FULL COLUMNS FROM card_number LIKE 'namecard'");
    if ($columnInfo) {
        $column = $columnInfo->fetch_assoc();
        $currentCollation = $column['Collation'] ?? '';
        if (empty($currentCollation) || stripos($currentCollation, 'utf8mb4') === false) {
            $connect->query("ALTER TABLE card_number CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $connect->query("ALTER TABLE card_number MODIFY cardnumber varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY");
            $connect->query("ALTER TABLE card_number MODIFY namecard varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        }
        $columnInfo->free();
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'Requestagent'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE Requestagent (
        id varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY,
        username  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        time  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Description  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        status  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        type  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table Requestagent: " . mysqli_error($connect));
        }
    } else {
        ensureTableUtf8mb4('Requestagent');
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'topicid'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE topicid (
        report varchar(500) PRIMARY KEY NOT NULL,
        idreport TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table Requestagent: " . mysqli_error($connect));
        }
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','buyreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','otherservice')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','paymentreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','otherreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','reporttest')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','errorreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','porsantreport')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','reportnight')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','reportcron')");
        $connect->query("INSERT INTO topicid (idreport,report) VALUES ('0','backupfile')");
    } else {
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','buyreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','otherservice')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','paymentreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','otherreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reporttest')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','errorreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','porsantreport')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reportnight')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reportcron')");
        $connect->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','backupfile')");


    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'manualsell'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE manualsell (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codepanel  varchar(100)  NOT NULL,
        codeproduct  varchar(100)  NOT NULL,
        namerecord  varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        username  varchar(500)  NULL,
        contentrecord  TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        status  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table manualsell: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {

    $tableName = 'departman';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idsupport VARCHAR(200) NOT NULL,
            name_departman VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $connect->query("INSERT INTO departman (idsupport,name_departman) VALUES ('$adminnumber','☎️ بخش عمومی')");
    }
} catch (PDOException $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {

    $tableName = 'support_message';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Tracking VARCHAR(100) NOT NULL,
            idsupport VARCHAR(100) NOT NULL,
            iduser VARCHAR(100) NOT NULL,
            name_departman VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            result TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            time VARCHAR(200) NOT NULL,
            status ENUM('Answered','Pending','Unseen','Customerresponse','close') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable("support_message", "result", "0", "TEXT");
    }
} catch (PDOException $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'wheel_list'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE wheel_list (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user  varchar(200)  NOT NULL,
        time  varchar(200)  NOT NULL,
        first_name  varchar(200)  NOT NULL,
        wheel_code  varchar(200)  NOT NULL,
        price  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table wheel_list: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'botsaz'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE botsaz (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user  varchar(200)  NOT NULL,
        bot_token  varchar(200)  NOT NULL,
        admin_ids  TEXT  NOT NULL,
        username  varchar(200)  NOT NULL,
        setting  TEXT  NULL,
        hide_panel  JSON  NOT NULL,
        time  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table botsaz: " . mysqli_error($connect));
        }
    } else {
        addFieldToTable("botsaz", "hide_panel", "{}", "JSON");
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'app'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE app (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name  varchar(200)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        link  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table app: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}


try {
    $result = $connect->query("SHOW TABLES LIKE 'logs_api'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE logs_api (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        header JSON  NULL,
        data JSON  NULL,
        ip  varchar(200)  NOT NULL,
        time  varchar(200)  NOT NULL,
        actions  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            error_log("[table.php] table logs_api: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'category'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE category (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        remark varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin");
        if (!$result) {
            error_log("[table.php] table category: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}
try {
    $result = $connect->query("SHOW TABLES LIKE 'reagent_report'");
    $table_exists = ($result->num_rows > 0);

    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE reagent_report (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNIQUE  NOT NULL,
        get_gift BOOL   NOT NULL,
        time varchar(50)  NOT NULL,
        reagent varchar(30)  NOT NULL
        )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin");
        if (!$result) {
            error_log("[table.php] table affiliates: " . mysqli_error($connect));
        }
    }
} catch (Exception $e) {
    error_log('[panels] ' . $e->getMessage());
}


$balancemain = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
if (!isset($balancemain['f'])) {
    $value = json_encode(array(
        "f" => "1000000",
        "n" => "1000000",
        "n2" => "1000000",
    ));
    $valuemain = json_encode(array(
        "f" => "20000",
        "n" => "20000",
        "n2" => "20000",
    ));
    update("PaySetting", "ValuePay", $value, "NamePay", "maxbalance");
    update("PaySetting", "ValuePay", $valuemain, "NamePay", "minbalance");
}
$connect->query("ALTER TABLE `invoice` CHANGE `Volume` `Volume` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `price_product` `price_product` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `name_product` `name_product` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `username` `username` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `Service_location` `Service_location` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `time_sell` `time_sell` VARCHAR(200)");
$connect->query("ALTER TABLE marzban_panel MODIFY name_panel VARCHAR(255) COLLATE utf8mb4_bin");
$connect->query("ALTER TABLE product MODIFY name_product VARCHAR(255) COLLATE utf8mb4_bin");
$connect->query("ALTER TABLE help MODIFY name_os VARCHAR(500) COLLATE utf8mb4_bin");


try {
    addFieldToTable("setting", "webhook_secret_token", "", "VARCHAR(64)");
    $rxForceRegen = isset($_GET['regen_secret']) && $_GET['regen_secret'] === '1';
    $rxExistingRow = $pdo->query("SELECT webhook_secret_token FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $rxBotWebhookSecret = is_array($rxExistingRow) ? (string)($rxExistingRow['webhook_secret_token'] ?? '') : '';
    if ($rxForceRegen || $rxBotWebhookSecret === '') {
        $rxBotWebhookSecret = bin2hex(random_bytes(16));
        $stmtSecret = $pdo->prepare("UPDATE setting SET webhook_secret_token = :tok");
        $stmtSecret->bindValue(':tok', $rxBotWebhookSecret, PDO::PARAM_STR);
        $stmtSecret->execute();
    }
} catch (\Throwable $rxSecretErr) {
    error_log('Failed to read/persist webhook_secret_token: ' . $rxSecretErr->getMessage());
    $rxBotWebhookSecret = '';
}
if ($rxBotWebhookSecret !== '') {
    $rxSetHookResp = telegram('setwebhook', [
        'url' => "https://$domainhosts/index.php",
        'secret_token' => $rxBotWebhookSecret,
    ]);
    if (!is_array($rxSetHookResp) || empty($rxSetHookResp['ok'])) {
        error_log('setwebhook FAILED: ' . json_encode($rxSetHookResp));
    }
}


try {
    $connect->query("CREATE TABLE IF NOT EXISTS nm_config_stock (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        shelf_id BIGINT UNSIGNED NULL,
        codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto',
        tier VARCHAR(32) NOT NULL DEFAULT 'auto',
        format VARCHAR(32) NOT NULL DEFAULT 'link',
        content MEDIUMTEXT NOT NULL,
        sub_link MEDIUMTEXT NULL,
        status ENUM('active','reserved','delivered','disabled') NOT NULL DEFAULT 'active',
        assigned_user VARCHAR(64) NULL,
        assigned_invoice VARCHAR(64) NULL,
        assigned_mode VARCHAR(64) NULL,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        reserved_at INT UNSIGNED NULL,
        delivered_at INT UNSIGNED NULL,
        UNIQUE KEY uq_nm_config_stock_content (content(191)),
        KEY idx_nm_stock_lookup (status, codepanel, codeproduct, tier),
        KEY idx_nm_stock_shelf (status, shelf_id),
        KEY idx_nm_stock_invoice (assigned_invoice)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connect->query("CREATE TABLE IF NOT EXISTS nm_stock_shelves (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        source_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        stock_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        category_id VARCHAR(64) NULL,
        category_name VARCHAR(191) NULL,
        codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto',
        product_name VARCHAR(191) NULL,
        volume_gb DECIMAL(10,2) NOT NULL DEFAULT 0,
        service_days INT NOT NULL DEFAULT 0,
        price BIGINT NOT NULL DEFAULT 0,
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at INT UNSIGNED NULL,
        UNIQUE KEY uq_nm_stock_shelf_name_panel (name, source_codepanel),
        KEY idx_nm_stock_shelf_lookup (status, source_codepanel, codeproduct)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connect->query("CREATE TABLE IF NOT EXISTS nm_config_stock_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        stock_id BIGINT UNSIGNED NULL,
        id_user VARCHAR(64) NULL,
        id_invoice VARCHAR(64) NULL,
        action VARCHAR(64) NOT NULL,
        payload MEDIUMTEXT NULL,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        KEY idx_nm_stock_log_invoice (id_invoice),
        KEY idx_nm_stock_log_user (id_user)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connect->query("CREATE TABLE IF NOT EXISTS nm_stock_product_map (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        source_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        stock_codepanel VARCHAR(191) NOT NULL DEFAULT 'auto',
        codeproduct VARCHAR(191) NOT NULL DEFAULT 'auto',
        category VARCHAR(191) NULL,
        category_id VARCHAR(64) NULL,
        category_name VARCHAR(191) NULL,
        volume_gb DECIMAL(10,2) NOT NULL DEFAULT 0,
        service_days INT NOT NULL DEFAULT 0,
        price BIGINT NOT NULL DEFAULT 0,
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at INT UNSIGNED NULL,
        UNIQUE KEY uq_nm_stock_product_map (source_codepanel, stock_codepanel, codeproduct),
        KEY idx_nm_stock_product_lookup (status, source_codepanel, codeproduct),
        KEY idx_nm_stock_product_category (status, source_codepanel, category)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    rxSafeAddColumn($connect, "nm_config_stock", "shelf_id", "BIGINT UNSIGNED NULL");
    rxSafeAddColumn($connect, "nm_config_stock", "sub_link", "MEDIUMTEXT NULL");
    rxSafeAddColumn($connect, "nm_stock_product_map", "category_id", "VARCHAR(64) NULL");
    rxSafeAddColumn($connect, "nm_stock_product_map", "category_name", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "invoice", "source_panel_code", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "marzban_panel", "emergency_panel_status", "VARCHAR(50) NOT NULL DEFAULT 'off_emergency_panel'");
    rxSafeAddColumn($connect, "marzban_panel", "national_net_status", "VARCHAR(50) NOT NULL DEFAULT 'off_national_net'");
    rxSafeAddColumn($connect, "marzban_panel", "emergency_source_panel", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "marzban_panel", "stock_source_panel", "VARCHAR(191) NULL");
    rxSafeAddColumn($connect, "marzban_panel", "version_panel", "VARCHAR(60) NOT NULL DEFAULT '0'");
    echo "national inventory tables OK ✅";
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage(), FILE_APPEND);
}


try {

    $result = $connect->query("SHOW TABLES LIKE 'crypto_wallets'");
    $exists = ($result && $result->num_rows > 0);
    if (!$exists) {
        $created = $connect->query("CREATE TABLE crypto_wallets (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            currency VARCHAR(20) NOT NULL,
            network VARCHAR(20) NOT NULL,
            wallet_address VARCHAR(255) NOT NULL,
            label VARCHAR(255) DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            min_irt BIGINT NOT NULL DEFAULT 20000,
            max_irt BIGINT NOT NULL DEFAULT 100000000,
            cashback_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
            rate_irt_override DECIMAL(20,4) DEFAULT NULL,
            wallet_memo VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_currency (currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$created) {
            error_log("[table.php] crypto_wallets: " . mysqli_error($connect));
        }
        $seedCurrencies = [
            ['TRX',         'TRON', '', 'ترون (TRX)'],
            ['TON',         'TON',  '', 'تون (TON)'],
            ['USDT_TRC20',  'TRON', '', 'تتر روی شبکه ترون (USDT-TRC20)'],
            ['USDT_TON',    'TON',  '', 'تتر روی شبکه تون (USDT-TON)'],
        ];
        $seedStmt = $connect->prepare(
            "INSERT IGNORE INTO crypto_wallets (currency, network, wallet_address, label, enabled) VALUES (?,?,?,?,0)"
        );
        if ($seedStmt) {
            foreach ($seedCurrencies as $row) {
                $seedStmt->bind_param('ssss', $row[0], $row[1], $row[2], $row[3]);
                $seedStmt->execute();
            }
            $seedStmt->close();
        }
        echo "crypto_wallets table created ✅";
    } else {
        rxSafeAddColumn($connect, "crypto_wallets", "network",            "VARCHAR(20) NOT NULL DEFAULT 'TRON'");
        rxSafeAddColumn($connect, "crypto_wallets", "label",              "VARCHAR(255) DEFAULT NULL");
        rxSafeAddColumn($connect, "crypto_wallets", "enabled",            "TINYINT(1) NOT NULL DEFAULT 1");
        rxSafeAddColumn($connect, "crypto_wallets", "min_irt",            "BIGINT NOT NULL DEFAULT 20000");
        rxSafeAddColumn($connect, "crypto_wallets", "max_irt",            "BIGINT NOT NULL DEFAULT 100000000");
        rxSafeAddColumn($connect, "crypto_wallets", "cashback_percent",   "DECIMAL(6,2) NOT NULL DEFAULT 0");
        rxSafeAddColumn($connect, "crypto_wallets", "rate_irt_override",  "DECIMAL(20,4) DEFAULT NULL");
        rxSafeAddColumn($connect, "crypto_wallets", "created_at",         "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        rxSafeAddColumn($connect, "crypto_wallets", "updated_at",         "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        rxSafeAddColumn($connect, "crypto_wallets", "wallet_memo",        "VARCHAR(255) NULL");
    }


    rxSafeAddColumn($connect, "Payment_report", "crypto_currency",        "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_network",         "VARCHAR(20) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_amount",          "DECIMAL(30,9) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_wallet_to",       "VARCHAR(255) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_tx_hash",         "VARCHAR(255) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_hash_at",         "BIGINT NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_check_count",     "INT NOT NULL DEFAULT 0");
    rxSafeAddColumn($connect, "Payment_report", "crypto_last_error",      "TEXT NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_sender_address",  "VARCHAR(255) NULL");
    rxSafeAddColumn($connect, "Payment_report", "crypto_iranian_mode",    "TINYINT(1) NOT NULL DEFAULT 0");
    rxSafeAddColumn($connect, "Payment_report", "source",                 "VARCHAR(20) NULL");

    try {
        $haveSrcIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_source_user'");
        if ($haveSrcIdx && $haveSrcIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_source_user (source, id_user)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_crypto_pending'");
        if ($haveIdx && $haveIdx->num_rows === 0) {
            $connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_crypto_pending (payment_Status, crypto_currency)");
        }
    } catch (Throwable $e) {  }

    try {
        $haveHashIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'uniq_pr_crypto_hash'");
        if ($haveHashIdx && $haveHashIdx->num_rows === 0) {
            @$connect->query("ALTER TABLE Payment_report ADD UNIQUE KEY uniq_pr_crypto_hash (crypto_tx_hash)");
        }
    } catch (Throwable $e) {
        error_log('[table.php] could not add unique key on crypto_tx_hash: ' . $e->getMessage());
    }

    try {
        $haveSenderIdx = $connect->query("SHOW INDEX FROM Payment_report WHERE Key_name = 'idx_pr_crypto_sender'");
        if ($haveSenderIdx && $haveSenderIdx->num_rows === 0) {
            $connect->query("ALTER TABLE Payment_report ADD INDEX idx_pr_crypto_sender (crypto_currency, crypto_sender_address)");
        }
    } catch (Throwable $e) {  }


    $cryptoSettings = [
        ['cryptocheck_cashback_TRX',      '0'],
        ['cryptocheck_cashback_TON',      '0'],
        ['cryptocheck_cashback_USDT_TRC20','0'],
        ['cryptocheck_cashback_USDT_TON', '0'],
        ['cryptocheck_tonapi_key',        ''],
        ['cryptocheck_trongrid_key',      ''],


        ['cryptocheck_amount_tolerance',  '0'],
        ['cryptocheck_overpay_tolerance', '0'],
        ['cryptocheck_max_retries',       '10'],
        ['cryptocheck_invoice_ttl',       '86400'],
        ['cryptocheck_invoice_ttl_iranian','7200'],
        ['cryptocheck_iranian_tolerance', '2'],
        ['cryptocheck_iranian_threshold', '1'],
    ];
    foreach ($cryptoSettings as $cs) {
        $ins = $connect->prepare("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)");
        if ($ins) {
            $ins->bind_param('ss', $cs[0], $cs[1]);
            $ins->execute();
            $ins->close();
        }
    }
    echo "crypto hash-checker schema OK ✅";
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php cryptocheck] ' . $e->getMessage() . "\n", FILE_APPEND);
}


try {
    $result = $connect->query("SHOW TABLES LIKE 'premium_emojis'");
    $exists = ($result && $result->num_rows > 0);
    if (!$exists) {
        $created = $connect->query("CREATE TABLE premium_emojis (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            emoji VARCHAR(50) NOT NULL,
            custom_emoji_id VARCHAR(100) NOT NULL,
            label VARCHAR(255) NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_emoji_cid (emoji, custom_emoji_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$created) {
            error_log("[table.php] premium_emojis: " . mysqli_error($connect));
        }
        echo "premium_emojis table created ✅";
    } else {
        rxSafeAddColumn($connect, "premium_emojis", "emoji",           "VARCHAR(50) NOT NULL");
        rxSafeAddColumn($connect, "premium_emojis", "custom_emoji_id", "VARCHAR(100) NOT NULL");
        rxSafeAddColumn($connect, "premium_emojis", "label",           "VARCHAR(255) NULL");
        rxSafeAddColumn($connect, "premium_emojis", "created_at",      "INT UNSIGNED NOT NULL DEFAULT 0");
        rxSafeAddColumn($connect, "premium_emojis", "updated_at",      "INT UNSIGNED NOT NULL DEFAULT 0");
    }


    try {
        $oldKey = $connect->query("SHOW INDEX FROM premium_emojis WHERE Key_name='uniq_emoji'");
        if ($oldKey && $oldKey->num_rows > 0) {
            $connect->query("ALTER TABLE premium_emojis DROP INDEX uniq_emoji");
        }
        $newKey = $connect->query("SHOW INDEX FROM premium_emojis WHERE Key_name='uniq_emoji_cid'");
        if (!$newKey || $newKey->num_rows === 0) {
            $connect->query("ALTER TABLE premium_emojis ADD UNIQUE KEY uniq_emoji_cid (emoji, custom_emoji_id)");
        }
    } catch (Exception $idxErr) {
        error_log("[table.php] premium_emojis index migration: " . $idxErr->getMessage());
    }


    echo "premium emoji schema OK ✅";
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php premium_emojis] ' . $e->getMessage() . "\n", FILE_APPEND);
}


try {
    addFieldToTable("setting", "premium_emoji_status", "0", "VARCHAR(20)");
    addFieldToTable("setting", "keyboard_styles_all", "{}", "TEXT");
} catch (Exception $e) {
    file_put_contents('error_log', '[table.php setting kb cols] ' . $e->getMessage() . "\n", FILE_APPEND);
}







try {
    if (function_exists('rx_getKeyboardDefaultStyles')) {
        $rxSeedDefaults = rx_getKeyboardDefaultStyles();
        if (is_array($rxSeedDefaults) && !empty($rxSeedDefaults)) {
            $rxSeedRes = $connect->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
            $rxSeedRow = ($rxSeedRes && $rxSeedRes->num_rows > 0) ? $rxSeedRes->fetch_assoc() : null;
            $rxSeedRaw = is_array($rxSeedRow) ? (string)($rxSeedRow['keyboard_styles_all'] ?? '') : '';
            $rxSeedExisting = ($rxSeedRaw !== '') ? json_decode($rxSeedRaw, true) : [];
            if (!is_array($rxSeedExisting)) { $rxSeedExisting = []; }

            $rxSeedChanged = false;
            foreach ($rxSeedDefaults as $rxSeedSection => $rxSeedPairs) {
                if (!is_array($rxSeedPairs)) { continue; }
                if (!isset($rxSeedExisting[$rxSeedSection]) || !is_array($rxSeedExisting[$rxSeedSection])) {
                    $rxSeedExisting[$rxSeedSection] = [];
                }
                foreach ($rxSeedPairs as $rxSeedKey => $rxSeedVal) {

                    if (!array_key_exists($rxSeedKey, $rxSeedExisting[$rxSeedSection])) {
                        $rxSeedExisting[$rxSeedSection][$rxSeedKey] = $rxSeedVal;
                        $rxSeedChanged = true;
                    }
                }
            }

            if ($rxSeedChanged) {
                $rxSeedJson = json_encode($rxSeedExisting, JSON_UNESCAPED_UNICODE);
                if ($rxSeedJson !== false) {
                    $rxSeedUpd = $connect->prepare("UPDATE setting SET keyboard_styles_all = ?");
                    if ($rxSeedUpd) {
                        $rxSeedUpd->bind_param('s', $rxSeedJson);
                        $rxSeedUpd->execute();
                        $rxSeedUpd->close();
                    }
                }
            }
            echo "admin keyboard defaults seeded ✅";
        }
    }
} catch (Exception $rxSeedErr) {
    file_put_contents('error_log', '[table.php kb defaults seed] ' . $rxSeedErr->getMessage() . "\n", FILE_APPEND);
}

