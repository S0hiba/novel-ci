<?php
# -----------------------------
# 小説CIスクリプト 文法チェック処理
# 2020.09.29 s0hiba 初版作成
# -----------------------------


//処理開始
print "文法チェック処理開始\n";

//slack通知用のcurl設定
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://hooks.slack.com/services/XXXXXX/XXXXXX/xxxxxx');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);

//全ての小説本文のファイルに対して、文法チェックを実行
$errorStr = '';
foreach ($novelMainFilePathArray as $novelMainFilePath) {
    //チェック対象のファイル名を出力
    print "文法チェック開始 : {$novelMainFilePath}\n";

    //ファイルの内容を取得
    $novelTextStr = file_get_contents($novelMainFilePath);

    //ファイルの内容を改行で分割
    $novelTextRowArray = explode("\n", $novelTextStr);

    //ファイルの内容を改行区切りで取得できない場合、文法チェックは実行せずに次のファイルへ
    if (!Validator::validateArray($novelTextRowArray)) {
        print "ファイルが空 : {$novelMainFilePath}\n";
        continue;
    }

    //全ての行に対して、文法チェックを実行
    $rowNumber = 1;
    $errorArray = array();
    foreach ($novelTextRowArray as $novelTextRowStr) {
        print "{$rowNumber} : {$novelTextRowStr}\n";

        //先頭行(タイトル)と空白行は無視し、次の行へ
        if ($rowNumber == 1 || $novelTextRowStr === '') {
            $rowNumber++;
            continue;
        }

        //行頭が全角スペースもしくは始め鉤括弧であるかチェック
        if (mb_substr($novelTextRowStr, 0, 1, 'UTF-8') !== '　'
         && mb_substr($novelTextRowStr, 0, 1, 'UTF-8') !== '「') {
            $isError = true;
            $errorArray[] = "{$rowNumber}行目 : 行頭下げが無い";
        }

        //鉤括弧を字下げしていないかチェック
        if (mb_substr($novelTextRowStr, 0, 1, 'UTF-8') === '　'
         && mb_substr($novelTextRowStr, 1, 1, 'UTF-8') === '「') {
            $isError = true;
            $errorArray[] = "{$rowNumber}行目 : 括弧を下げている";
        }

        //三点リーダーが2連続になっているかチェック
        $validateReaderResult = validateTwoConsectiveStr($novelTextRowStr, '…');
        switch ($validateReaderResult) {
            case 'short':
                $isError = true;
                $errorArray[] = "{$rowNumber}行目 : 三点リーダーが短い";
                break;
            case 'long':
                $isError = true;
                $errorArray[] = "{$rowNumber}行目 : 三点リーダーが長い";
                break;
        }

        //ダッシュが2連続になっているかチェック
        $validateDashResult = validateTwoConsectiveStr($novelTextRowStr, '―');
        switch ($validateDashResult) {
            case 'short':
                $isError = true;
                $errorArray[] = "{$rowNumber}行目 : ダッシュが短い";
                break;
            case 'long':
                $isError = true;
                $errorArray[] = "{$rowNumber}行目 : ダッシュが長い";
                break;
        }

        //鉤括弧末尾に句点がないかチェック
        if (mb_strpos($novelTextRowStr, '。」') !== false) {
            $isError = true;
            $errorArray[] = "{$rowNumber}行目 : 括弧末尾に句点";
        }

        //「！」「？」の後ろに空白があるかチェック
        $validateExclamationQuestionResult = validateExclamationQuestion($novelTextRowStr);
        if (!$validateExclamationQuestionResult) {
            $isError = true;
            $errorArray[] = "{$rowNumber}行目 : 「！」「？」の後に空白が無い";
        }

        //半角文字が使われていないかチェック
        if ($novelTextRowStr !== mb_convert_kana($novelTextRowStr, 'ASKH')) {
            $isError = true;
            $errorArray[] = "{$rowNumber}行目：半角文字を使用";
        }

        //英数字が使われていないかチェック
        if (mb_ereg_match('.*[ａ-ｚＡ-Ｚ０-９].*', $novelTextRowStr)) {
            $isError = true;
            $errorArray[] = "{$rowNumber}行目：英数字を使用";
        }

        $rowNumber++;
    }

    //ファイルの文法チェックでエラーがあった場合、エラー文字列を追加
    if (Validator::validateArray($errorArray)) {
        $errorStr .= "文法エラー：{$novelMainFilePath}\n";
        $errorStr .= implode("\n", $errorArray);
        $errorStr .= "\n";
    }
}

//全体を通して文法チェックでエラーがあった場合、エラー文字列を出力しCIを失敗とする
if ($errorStr !== '') {
    print $errorStr;

    //Slackに文法エラーを通知
    $slackPostParam = array(
        'payload' => json_encode(array(
            'channel'   => '#novel_ci_syntax',
            'text'      => $errorStr,
        ))
    );
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($slackPostParam));
    $slackPostResult = curl_exec($curl);

    trigger_error("文法エラーを発見\n", E_USER_ERROR);
}

//Slackに文法チェック結果を通知
$slackPostParam = array(
    'payload' => json_encode(array(
        'channel'   => '#novel_ci_syntax',
        'text'      => '文法チェックに成功しました',
    ))
);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($slackPostParam));
$slackPostResult = curl_exec($curl);

//処理終了
print "文法チェック処理終了\n";

/**
 * 特定の文字列が2連続になっているかのバリデーション
 * @param  string $haystack バリデーション対象の文字列
 * @param  string $needle   2連続であるべき文字列
 * @return string           バリデーション結果(2連続になっていればok、短い場合はshort、長い場合はlong)
 */
function validateTwoConsectiveStr($haystack, $needle) {
    //検索対象の文字列の最初の出現箇所を検索
    $needleStart = mb_strpos($haystack, $needle, 0, 'UTF-8');

    //検索対象の文字列が存在しない場合、成功を結果として返して処理終了
    if ($needleStart === false) {
        return 'ok';
    }

    //検索対象の文字列の連続が短い、もしくは長い場合、失敗を結果として返して処理終了
    if (mb_substr($haystack, $needleStart + 1, 1, 'UTF-8') !== $needle) {
        return 'short';
    } elseif (mb_substr($haystack, $needleStart + 2, 1, 'UTF-8') === $needle) {
        return 'long';
    }

    //検索対象の文字列が存在し失敗しなかった場合、成功した箇所より先の文字列を再帰的にチェック
    $afterNeedleHaystack = mb_substr($haystack, $needleStart + 2, null, 'UTF-8');
    $result = validateTwoConsectiveStr($afterNeedleHaystack, $needle);

    //再帰的にチェックした結果を返す
    return $result;
}

/**
 * ！と？の後ろに空白があるかのバリデーション
 * @param  string  $haystack バリデーション対象の文字列
 * @return boolean           バリデーション結果(正しく空白があればtrue、空白がなく修正が必要な場合はfalse)
 */
function validateExclamationQuestion($haystack) {
    //！と？の最初の出現箇所を検索
    $exclamationStart = mb_strpos($haystack, '！', 0, 'UTF-8');
    $questionStart = mb_strpos($haystack, '？', 0, 'UTF-8');

    //どちらの記号も存在しない場合、成功を結果として返して処理終了
    if ($exclamationStart === false && $questionStart === false) {
        return true;
    }

    //！と？の先に出現する方の出現箇所を、！と？の塊の先頭位置として取得
    $groupStart = $exclamationStart;
    if ($exclamationStart === false || ($questionStart !== false && $questionStart < $exclamationStart)) {
        //！が存在しないか、！より？が先に出現する場合、？の位置を先頭位置とする
        $groupStart = $questionStart;
    }

    //！と？の塊の末尾にある全角スペースの位置を取得
    $groupEndPosition = getExclamationQuestionGroupEndPosition($haystack, $groupStart);

    //取得結果が-1だった(末尾が全角スペースでない)場合、失敗を結果として返して処理終了
    if ($groupEndPosition == -1) {
        return false;
    }

    //！と？の塊の末尾がスペースだった場合、スペースの位置より先の文字列を再帰的にチェック
    $afterGroupHaystack = mb_substr($haystack, $groupEndPosition, null, 'UTF-8');
    $result = validateExclamationQuestion($afterGroupHaystack);

    //再帰的にチェックした結果を返す
    return $result;
}

/**
 * ！と？の塊の末尾にあたる全角スペースの位置を取得
 * @param  string $haystack     バリデーション対象の文字列
 * @param  int    $targetStrpos ！と？の塊の先頭文字の位置
 * @return int                  ！と？の塊の末尾にあたる全角スペースの位置(末尾が全角スペースでない場合は-1)
 */
function getExclamationQuestionGroupEndPosition($haystack, $targetStrpos) {
    //判別対象文字の次の文字を取得
    $targetStrpos++;
    $targetStr = mb_substr($haystack, $targetStrpos, 1);

    //文字の内容に応じた処理を行う
    switch ($targetStr) {
        case '！':
        case '？':
            //！か？だった場合、もう一つ次の文字を再帰的にチェック
            $result = getExclamationQuestionGroupEndPosition($haystack, $targetStrpos);
            break;
        case '　':
        case '」':
        case '':
            //全角スペースもしくは、閉じ鉤括弧か空文字列だった場合、その位置を数値として返す
            $result = $targetStrpos;
            break;
        default:
            //それ以外だった場合、失敗を示す-1を返す
            $result = -1;
    }

    //再帰的にチェックした結果を返す
    return $result;
}
