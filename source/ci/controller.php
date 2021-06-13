<?php
# -----------------------------
# 小説CIスクリプト マスターコントローラ
# 2020.09.29 s0hiba 初版作成
# -----------------------------


//環境変数の値を定数として取得
define('CI_PROJECT_PATH', getenv('CI_PROJECT_PATH'));
define('CI_PROJECT_NAME', getenv('CI_PROJECT_NAME'));

//各種ディレクトリパスを定数に定義
define('PROJECT_DIR', '/builds/' . CI_PROJECT_PATH . '/' . CI_PROJECT_NAME . '/');
define('NOVEL_MAIN_DIR', PROJECT_DIR . 'main/');
define('CI_SCRIPT_DIR', PROJECT_DIR . 'ci/');
define('CI_PHPLIB_DIR', CI_SCRIPT_DIR . 'phplib/');

//ライブラリ読み込み
include_once(CI_PHPLIB_DIR . 'class_Validator.php');

//小説本文のファイルパス一覧を取得
$novelMainFilePathArray = glob(NOVEL_MAIN_DIR . '*');

//小説本文のファイルパス一覧が取得できなかった場合、
if (!Validator::ValidateArray($novelMainFilePathArray)) {
    trigger_error('小説本文のファイルパスが取得できませんでした', E_USER_ERROR);
    exit;
}

//コマンドの第一引数で処理を切り分け
switch ($argv[1]) {
    case 'validateSyntax':
        include_once(CI_SCRIPT_DIR . "{$argv[1]}.php");
        break;
    default:
        trigger_error('CIスクリプトの指定が間違っています', E_USER_ERROR);
        exit;
}

print "CI is done.\n";

exit;
