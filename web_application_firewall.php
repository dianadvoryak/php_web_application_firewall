<?php

function exchange($one_el, $bracket){
    $length = strlen($one_el);
    $bracket++;
    $f_part = substr($one_el, 0, $bracket);
    $s_part = substr($one_el, $bracket, $length);
    return $f_part . "\u{2063}" . $s_part;
}

function make_it_safe($one_el){

    if (preg_match('/</', $one_el)) {
        $bracket = strpos($one_el, '<');
        return exchange($one_el, $bracket);
    }
    elseif (preg_match('/>/', $one_el)) {
        $bracket = strpos($one_el, '>');
        $bracket--;
        return exchange($one_el, $bracket);
    }
    elseif (preg_match('/order/', mb_strtolower($one_el))) {
        $sql_word = strpos($one_el, 'order');
        return exchange($one_el, $sql_word);
    }
    elseif (preg_match('/union/', mb_strtolower($one_el))) {
        $sql_word = strpos($one_el, 'union');
        return exchange($one_el, $sql_word);
    }
    elseif (preg_match('/select/', mb_strtolower($one_el))) {
        $sql_word = strpos($one_el, 'select');
        return exchange($one_el, $sql_word);
    }
    elseif (preg_match('/from/', mb_strtolower($one_el))) {
        $sql_word = strpos($one_el, 'from');
        return exchange($one_el, $sql_word);
    }
    elseif (preg_match('/where/', mb_strtolower($one_el))) {
        $sql_word = strpos($one_el, 'where');
        return exchange($one_el, $sql_word);
    }
    elseif (preg_match('/delete/', mb_strtolower($one_el))) {
        $sql_word = strpos($one_el, 'delete');
        return exchange($one_el, $sql_word);
    }
    elseif (preg_match('/javascript/', mb_strtolower($one_el))) {
        return "\u{2063}" . $one_el;
    }
    elseif (preg_match('/\.\.\/\w/', mb_strtolower($one_el))){
        $bracket = strrpos($one_el, '../');
        return exchange($one_el, $bracket);
    }
    else{
        return $one_el;
    }

}

function searchMas($arg){
    if (is_numeric($arg)) {
        return $arg;
    } elseif (is_string($arg)) {
        return make_it_safe($arg);
    } elseif (is_array($arg)) {
        foreach ($arg as $key => $value) { // для всех ключей
            $value = searchMas($arg[$key]); // получаем безопасное значение
            unset($arg[$key]); // удаляем оригинальное
            $key = searchMas($key); // делаем ключ безопасным
            $arg[$key] = $value;
        }
        return $arg;
    }
}

$_REQUEST = searchMas($_REQUEST);
print_r($_REQUEST);