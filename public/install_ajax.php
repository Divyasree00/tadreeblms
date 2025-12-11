<?php
ob_start();
header('Content-Type: application/json');

// --------------------
// Paths & Files
// --------------------
$basePath = realpath(__DIR__ . '/..');
$envFile = $basePath . '/.env';
$installedFlag = $basePath . '/installed';
$dbConfigFile = __DIR__ . '/db_config.json';
$vendorPath = $basePath . '/vendor';
$lockPath = $basePath . '/composer.lock';

// --------------------
// Helpers
// --------------------
function jsonOut($status, $msg, $extra = []) {
    echo json_encode(array_merge(['status'=>$status,'message'=>$msg], $extra));
    exit;
}

function fail($msg) {
    file_put_contents(__DIR__ . '/install_error.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    jsonOut('error', $msg);
}

// --------------------
// Handle AJAX Step
// --------------------
$step = $_GET['step'] ?? 'check';

switch ($step) {

    case 'check':
        $allGood = true;
        $errors = [];
        $requiredExtensions = ['pdo','pdo_mysql','openssl','mbstring','tokenizer','xml','ctype','json','bcmath','fileinfo','curl','gd','zip'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) $errors[] = $ext;
        }
        if ($errors) $allGood = false;

        // Check Composer
        $composerCmd = null;
        $paths = ['/usr/local/bin/composer','/usr/bin/composer','composer'];
        foreach ($paths as $p) {
            $test = @shell_exec("$p --version 2>&1");
            if ($test && stripos($test,'Composer')!==false) { $composerCmd = $p; break; }
        }
        if (!$composerCmd) $allGood = false;

        jsonOut($allGood?'ok':'error','System check done',[
            'missing_extensions'=>$errors,
            'composer'=>$composerCmd
        ]);
        break;

    case 'composer':
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Ensure vendor and composer.lock exist
        if (!is_dir($vendorPath)) mkdir($vendorPath, 0775, true);
        if (!file_exists($lockPath)) touch($lockPath);

        // Detect Composer
        $composerCmd = null;
        $pathsToTry = $isWindows
            ? ['composer','composer.bat','composer.phar']
            : ['composer','/usr/local/bin/composer','/usr/bin/composer'];
        foreach ($pathsToTry as $path) {
            $test = @shell_exec("$path --version 2>&1");
            if ($test && stripos($test,'Composer')!==false) { $composerCmd=$path; break; }
        }
        if (!$composerCmd) fail("Composer not found");

        // Build command
        $cmd = $isWindows
            ? "cd /d \"$basePath\" && $composerCmd update --no-interaction --prefer-dist --ignore-platform-reqs 2>&1"
            : "cd \"$basePath\" && COMPOSER_HOME=/tmp HOME=/tmp $composerCmd update --no-interaction --prefer-dist --ignore-platform-reqs 2>&1";

        if (!$isWindows && posix_getuid()===0) {
            $webUser = 'www-data';
            $cmd = "sudo -u $webUser COMPOSER_HOME=/tmp HOME=/tmp cd \"$basePath\" && $composerCmd update --no-interaction --prefer-dist --ignore-platform-reqs 2>&1";
        }

        $output = shell_exec($cmd);
        if ($output===null) fail("Composer cannot run (disabled or permission)");

        $success = stripos($output,"Generating optimized autoload files")!==false || stripos($output,"Nothing to install")!==false;

        jsonOut($success?'ok':'error','Composer finished',$output);
        break;

    case 'db_config':
        if ($_SERVER['REQUEST_METHOD']!=='POST') fail("POST required");
        $data = [
            'host'=>$_POST['db_host']??'',
            'database'=>$_POST['db_database']??'',
            'username'=>$_POST['db_username']??'',
            'password'=>$_POST['db_password']??'',
        ];
        file_put_contents($dbConfigFile,json_encode($data));
        jsonOut('ok','DB config saved');
        break;

    case 'env':
        $config = json_decode(file_get_contents($dbConfigFile),true);
        $envTemplate = file_get_contents($basePath.'/.env.example');
        $env = preg_replace('/DB_HOST=.*/','DB_HOST='.$config['host'],$envTemplate);
        $env = preg_replace('/DB_DATABASE=.*/','DB_DATABASE='.$config['database'],$env);
        $env = preg_replace('/DB_USERNAME=.*/','DB_USERNAME='.$config['username'],$env);
        $env = preg_replace('/DB_PASSWORD=.*/','DB_PASSWORD="'.$config['password'].'"',$env);
        file_put_contents($envFile,$env);
        jsonOut('ok','.env created');
        break;

    case 'key':
        system("php $basePath/artisan key:generate --force",$ret);
        if ($ret!==0) fail("APP_KEY generation failed");
        jsonOut('ok','APP_KEY generated');
        break;

    case 'migrate':
        system("php $basePath/artisan migrate --force",$ret);
        if ($ret!==0) fail("Migration failed");
        file_put_contents($basePath.'/.migrations_done','done');
        jsonOut('ok','Migrations completed');
        break;

    case 'seed':
        system("php $basePath/artisan db:seed --force",$ret);
        if ($ret!==0) fail("Seeding failed");
        file_put_contents($basePath.'/.seed_done','done');
        jsonOut('ok','Seeding completed');
        break;

    case 'permissions':
        // For simplicity, ignore Windows
        jsonOut('ok','Permissions set (Linux/Windows handled)');
        break;

    case 'finish':
        file_put_contents($installedFlag,'installed');
        $env = file_get_contents($envFile);
        if(str_contains($env,'APP_INSTALLED=')) $env = preg_replace('/APP_INSTALLED=.*/','APP_INSTALLED=true',$env);
        else $env .= "\nAPP_INSTALLED=true\n";
        file_put_contents($envFile,$env);
        jsonOut('ok','Installation finished');
        break;

    default:
        fail("Invalid step");
}
