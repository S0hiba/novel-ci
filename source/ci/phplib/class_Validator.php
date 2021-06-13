<?php
# -----------------------------
# 小説CIスクリプト 共通バリデーション処理の関数群クラス
# 2020.07.09 s0hiba 初版作成
# -----------------------------


class Validator
{
    /**
     * 配列のバリデーション
     * @param   array   $array  バリデーション対象の変数
     * @return  boolean         バリデーション結果(要素を1つ以上持つ配列の場合はtrue、それ以外の場合はfalse)
     */
    public static function validateArray($array)
    {
        //結果を初期化
        $result = false;

        //バリデーション
        //要素を1つ以上持つ配列の場合、結果をtrueに上書き
        if (isset($array) && is_array($array) && count($array) > 0) {
            $result = true;
        }

        //結果を返す
        return $result;
    }
}
