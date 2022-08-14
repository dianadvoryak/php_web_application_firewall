<?php

$start = microtime(true);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'autoload.php';

use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

function getDirContents($dir, &$results = array())
{
    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($value != "." && $value != "..") {
            getDirContents($path, $results);
            $results[] = $path;
        }
    }
    return $results;
}

function printResult($var, $i, $file, $pprinter)
{
    global $flgShl;
    echo "shell:\n";
    echo $pprinter->prettyPrintExpr($var) . "\n";
    echo "find in file: " . $file . "\n";
    echo $i . "-------------------------\n";
    $flgShl = 1;
}


function SearchFn($funVr, $func, $kit, $file, $nodeFinder, $pprinter)
{
    global $stopFn;
    foreach ($funVr as $var) {
        if (in_array($var->name, $kit) || in_array($var->name, $stopFn))
            printResult($func, 1, $file, $pprinter);
    }
}



$files = getDirContents('shells');



include 'find_borrow.php';
global $bd_token_shells;

$files_next = [];
// создание БД token-shells
$database = ['shells' => [], 'tokens' => []];
preg_match_all('/\S+/', $bd_token_shells, $get_way_token_shells);
foreach ($get_way_token_shells[0] as $one_way) {
    $code = file_get_contents($one_way);
    $code = preg_replace('/<\/?\w+.*?>/m', '', $code);
    $code = preg_replace('/\/\*.*?\*\//ms', '', $code);
    $code = preg_replace('/\/\/.*?\n/m', '', $code);
    $code = preg_replace('/<!DOCTYPE html>|<\?php|\?>/m', '', $code);
    preg_match_all('/([a-zA-Z]*)/', $code, $part_shell);
    $all_shells = array_diff($part_shell[0], array('', NULL, false));
    $all_shells = array_values($all_shells);
    $znach = 0;
    foreach ($all_shells as $key => $word) {
        if ((!empty($all_shells[$znach])) && (!empty($all_shells[$znach + 1])) && (!empty($all_shells[$znach + 2]))) {
            $tokens[] = crc32($all_shells[$znach] . "|" . $all_shells[$znach + 1] . "|" . $all_shells[$znach + 2]);
            $znach++;
        }
    }
    if (count($tokens) < 75964)
        $tokens = array_unique($tokens);
    $database['shells'][$one_way] = count($tokens);

    foreach ($tokens as $token) {
        if (!isset($database['tokens'][$token])) {
            $database['tokens'][$token] = [];
        }
        $database['tokens'][$token][] = $one_way;
    }
    $tokens = [];

}

//print_r($database);

// проверка файлов по БД token-shells
foreach ($files as $i => $file_f) {

    if (preg_match('/\.php$/', $file_f)) {
        try {
            $code = file_get_contents($file_f);
        } catch (Exception $e) {
            continue;
        }

        $flgTkn = 0;
        $text_tokens = [];
        $found = [];
        $code = file_get_contents($file_f);
        $code = preg_replace('/<\/?\w+.*?>/m', '', $code);
        $code = preg_replace('/\/\*.*?\*\//ms', '', $code);
        $code = preg_replace('/\/\/.*?\n/m', '', $code);
        $code = preg_replace('/<!DOCTYPE html>|<\?php|\?>/m', '', $code);
        preg_match_all('/([a-zA-Z]*)/', $code, $part_word);
        $all_words = array_diff($part_word[0], array('', NULL, false));
        $all_words = array_values($all_words);
        $znach = 0;
        foreach ($all_words as $key => $word) {
            if ((!empty($all_words[$znach])) && (!empty($all_words[$znach + 1])) && (!empty($all_words[$znach + 2]))) {
                $text_tokens[] = crc32($all_words[$znach] . "|" . $all_words[$znach + 1] . "|" . $all_words[$znach + 2]);
            } else
                break;
            $znach++;
        }
        if (count($text_tokens) < 75964)
            $text_tokens = array_unique($text_tokens);

        // найти количество совпавших токенов в всех файлах files_
        foreach ($text_tokens as $token) {
            if (isset($database['tokens'][$token])) {
                foreach ($database['tokens'][$token] as $shell) {
                    if (!isset($found[$shell])) {
                        $found[$shell] = 0;
                    }
                    $found[$shell] += 1;
                }
            }
        }
//    print_r($found);
        foreach ($found as $k => $v) {
            $prc = round($v / $database['shells'][$k] * 100, 2);
            if ($prc > 50) {
                echo "shell $k, common tokens $v, prc: $prc \t find in file: $file_f \n";
                $flgTkn = 1;
            }
        }
        unset($text_tokens, $found);
        if ($flgTkn == 0){
            $files_next[] = $file_f;
        }
    }

}


$parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7);
$nodeFinder = new NodeFinder;
$errorHandler = new PhpParser\ErrorHandler\Collecting;
$pprinter = new PhpParser\PrettyPrinter\Standard;

$nodeFinder = new NodeFinder;

$colFlShells = [];
$stopFn = ['getenv', 'str_rot13', 'assert', 'create_function', 'exec', 'passthru', 'pcntl_exec', 'popen', 'proc_open', 'set_include_path', 'shell_exec', 'system', 'eval'];
$callbackFn = ['call_user_func' => [0],
    'call_user_func_array' => [0],
    'forward_static_call' => [0],
    'forward_static_call_array' => [0],
    'register_shutdown_function' => [0],
    'register_tick_function' => [0],
    'ob_start' => [0],
    'usort' => [1],
    'uasort' => [1],
    'uksort' => [1],
    'array_walk' => [1],
    'array_walk_recursive' => [1],
    'array_reduce' => [1],
    'array_intersect_ukey' => [2],
    'array_uintersect' => [2],
    'array_uintersect_assoc' => [2],
    'array_intersect_uassoc' => [2],
    'array_uintersect_uassoc' => [2, 3],
    'array_diff_ukey' => [2],
    'array_udiff' => [2],
    'array_udiff_assoc' => [2],
    'array_diff_uassoc' => [2],
    'array_udiff_uassoc' => [2, 3],
    'array_filter' => [1],
    'array_map' => [0],
    'mb_ereg_replace_callback' => [1]
];

foreach ($files_next as $file) { // поиск по сайту
    if (filesize($file) < 75964){
        $flgShl = 0;
    if (preg_match('/\.php$/', $file)) {
        try {
            $code = file_get_contents($file);
            $ast = $parser->parse($code);
        } catch (Exception $e) {
            continue;
        }
//        print_r($ast);

        check_tree($ast, [], $nodeFinder, $pprinter, $file, $flgShl);
        if ($flgShl == 0) {
            $colFlShells[$file] = filesize($file);
        }
    }}
}


function Recursion($consist, $nodeFinder, $file, $pprinter)
{
    global $values, $stopFn;

    if ($consist instanceof Node\Expr\Variable) {
        $name = is_string($consist->name) ? $consist->name : null;
        if (isset($values[$name])) {
            return $values[$name];
        } else {
            return $name;
        }
    } elseif ($consist instanceof Node\Scalar\String_) {
        return $consist->value;
    } elseif ($consist instanceof Node\Expr\FuncCall) {
        $function_name = $consist->name;
        $args = [];
        foreach ($consist->args as $el) {
            $args[] = Recursion($el->value, $nodeFinder, $file, $pprinter);
        }
        if ($function_name == 'strtr') {
            if (isset($args[2])) {
                if (in_array(strtr($args[0], $args[1], $args[2]), $stopFn)) {
                    printResult($consist, 14, $file, $pprinter);
                }
            } elseif (is_array($args[1])) {
                if (in_array(strtr($args[0], $args[1]), $stopFn)) {
                    printResult($consist, 14, $file, $pprinter);
                }
            }
        }
        if ($function_name == 'substr_replace') {
            if (isset($args[3])) {
                if (in_array(substr_replace($args[0], $args[1], $args[2], $args[3]), $stopFn)) {
                    printResult($consist, 15, $file, $pprinter);
                }
            } else {
                if (in_array(substr_replace($args[0], $args[1], $args[2]), $stopFn)) {
                    printResult($consist, 15, $file, $pprinter);
                }
            }
        }
        if ($function_name == 'chr') {
            foreach ($consist->args as $number) {
                if (isset($number->value->value))
                    return strtolower(chr($number->value->value));
                else {
                    return (strtolower(chr(Recursion($number->value, $nodeFinder, $file, $pprinter))));
                }
            }
        }
        $all_gets = [];
        if (is_string($args)) {
            $rez_func = $function_name . "(" . implode(", ", $args) . ")";
//        $reg_args = "'/" . implode('|', $stopFn) . "|" . '_POST|_REQUEST|_GET' . "/i'";
            $reg_args = '/getenv|str_rot13|assert|create_function|exec|passthru|pcntl_exec|popen|proc_open|set_include_path|shell_exec|system|eval|_POST|_REQUEST|_GET/i';
            if (preg_match($reg_args, $rez_func, $get)) {
                if (!in_array(current($get), $all_gets)) {
                    $all_gets[] = current($get);
                }
                return implode(", ", $all_gets);
            }
        }

//        print_r($args);
        if (is_string($args))
            return $function_name . "(" . implode(", ", $args) . ")";
    } elseif ($consist instanceof Node\Expr\ArrayDimFetch) {
        return Recursion($consist->var, $nodeFinder, $file, $pprinter);
    } elseif ($consist instanceof Node\Expr\BinaryOp\BitwiseXor) {
        $left = Recursion($consist->left, $nodeFinder, $file, $pprinter);
        $right = Recursion($consist->right, $nodeFinder, $file, $pprinter);
        return (string)($left ^ $right);
    } elseif ($consist instanceof Node\Expr\BinaryOp\Concat) {
        return Recursion($consist->left, $nodeFinder, $file, $pprinter) . Recursion($consist->right, $nodeFinder, $file, $pprinter);
    } elseif ($consist instanceof Node\Expr\Array_) {
        $argAr = [];
        foreach ($consist->items as $el) {
            $argAr[] = Recursion($el->value, $nodeFinder, $file, $pprinter);
        }
        return "[" . implode(", ", $argAr) . "]";
    } elseif ($consist instanceof Node\Expr\ArrayItem) {
        return Recursion($consist->value, $nodeFinder, $file, $pprinter);
    } elseif ($consist instanceof Node\Scalar\LNumber) {
        return (string)$consist->value;
    } elseif ($consist instanceof Node\Expr\Ternary) {
        $if = Recursion($consist->if, $nodeFinder, $file, $pprinter);
        $else = Recursion($consist->else, $nodeFinder, $file, $pprinter);
        return $if . "|" . $else;
    } elseif ($consist instanceof Node\Expr\BinaryOp\Plus) {
        $left = Recursion($consist->left, $nodeFinder, $file, $pprinter);
        $right = Recursion($consist->right, $nodeFinder, $file, $pprinter);
        return (string)((int)$left + (int)$right);
    } elseif ($consist instanceof Node\Expr\BinaryOp\Minus) {
        $left = Recursion($consist->left, $nodeFinder, $file, $pprinter);
        $right = Recursion($consist->right, $nodeFinder, $file, $pprinter);
        return (string)((int)$left - (int)$right);
    } elseif ($consist instanceof Node\Expr\BinaryOp\Mod) {
        $left = Recursion($consist->left, $nodeFinder, $file, $pprinter);
        $right = Recursion($consist->right, $nodeFinder, $file, $pprinter);
        return (string)((int)$left % (int)$right);
    } elseif ($consist instanceof Node\Expr\BinaryOp\Div) {
        $left = Recursion($consist->left, $nodeFinder, $file, $pprinter);
        $right = Recursion($consist->right, $nodeFinder, $file, $pprinter);
        return (string)((int)$left / (int)$right);
    }

    return '';
}


function check_tree($ast, $params, $nodeFinder, $pprinter, $file, &$flgShl)
{
    global $stopFn, $values;
    global $callbackFn;

    $nodes = [];

    $nodeFinder->find($ast, function (Node $node) use (&$nodes, $nodeFinder, $pprinter, $file, &$flgShl) {
        if ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Expr\Closure) {
            $nodes[] = $node;
            check_tree($node->stmts, $node->params, $nodeFinder, $pprinter, $file, $flgShl);
        }
    });


    foreach ($nodes as $node) {
        $node->stmts = [];
    }


    $kit = ['_POST', '_REQUEST', '_GET'];

    $nodes = ['assigns' => [], 'calls' => [], 'evals' => [], 'quotes' => [], 'functions' => []];
    $nodeFinder->find($ast, function (Node $node) use (&$nodes) {
        if ($node instanceof Node\Expr\Assign) {
            $nodes['assigns'][] = $node;
        }
        if ($node instanceof Node\Expr\FuncCall) {
            $nodes['calls'][] = $node;
        }
        if ($node instanceof Node\Expr\Eval_) {
            $nodes['evals'][] = $node;
        }
        if ($node instanceof Node\Expr\ShellExec) {
            $nodes['quotes'][] = $node;
        }
        if ($node instanceof Node\Stmt\Function_) {
            $nodes['functions'][] = $node;
        }
    });


    $values = [];
    // поиск присвоений
    foreach ($nodes['assigns'] as $var) {

        if (isset($var->var->name) && is_string($var->var->name)) { // поиск нижних подчеркиваний
            $non_word_char = $var->var->name;
            $cnt_ltr = iconv_strlen($non_word_char);
            preg_match_all('/_/', $non_word_char, $underline);
            if ($cnt_ltr == count($underline[0])) {
                printResult($var, 23, $file, $pprinter);
            }
        }

        if ($var->var instanceof Node\Expr\Variable) {
            $name = is_string($var->var->name) ? $var->var->name : null;
            $rezRec = Recursion($var->expr, $nodeFinder, $file, $pprinter);
            if (isset($values[$name])) {
                $values[$name] = $values[$name] . ", " . $rezRec;
            } else $values[$name] = $rezRec;
        }


        if ($var->var instanceof Node\Expr\Variable && $var->expr instanceof Node\Expr\Variable) {
            if (in_array($var->expr->name, $kit)) { // если значение переменной есть в kit, то добавляем название переменной в kit
                if (!in_array($var->var->name, $kit)) { // проверка на такую же переменную в массиве kit
                    $kit[] = $var->var->name;
                }
            }
        }
        // поиск первых перменных которые равны другим переменным
        foreach ($nodes['assigns'] as $vr) {
//                print_r($vr);
            $flag = 0;
            $vrExpr = $nodeFinder->findInstanceOf($vr->expr, Node\Expr\Variable::class);
            foreach ($vrExpr as $v) {
                if (in_array($v->name, $kit)) {
                    $flag = 1;
                }
            }
            if ($flag == 1) {
                if ($vr->var instanceof Node\Expr\Variable) {
                    if (!in_array($vr->var->name, $kit)) {
                        $kit[] = $vr->var->name;
                    }
                }
            }
        }
        $xor = $nodeFinder->findInstanceOf($var->expr, Node\Expr\BinaryOp\BitwiseXor::class);
        foreach ($xor as $st) {
            $l = (isset($st->left->value)) ? $st->left->value : null;
            $r = (isset($st->right->value)) ? $st->right->value : null;
            if (in_array(($l ^ $r), $kit)) {
                printResult($var, 2, $file, $pprinter);
            }
        }

        $varStr = $nodeFinder->findInstanceOf($var->expr, Node\Scalar\String_::class);
        foreach ($varStr as $str) {
            if (in_array(strrev($str->value), $stopFn)) {
                printResult($var, 3, $file, $pprinter);
            }
        }

    }

    $reg_exp = '/' . implode('|', $stopFn) . '/i';

    foreach ($values as $key => $value) {
        if (is_string($value)) {
            if (preg_match($reg_exp, $value)) {
                $kit[] = $key;
            }
        }
    }


    // поиск обратных кавычек
    foreach ($nodes['quotes'] as $quot) {
        $quotVr = $nodeFinder->findInstanceOf($quot, Node\Expr\Variable::class);
        SearchFn($quotVr, $quot, $kit, $file, $nodeFinder, $pprinter);
    }


    // поиск eval
    foreach ($nodes['evals'] as $evals) {
        $evlVr = $nodeFinder->findInstanceOf($evals->expr, Node\Expr\Variable::class);
        SearchFn($evlVr, $evals, $kit, $file, $nodeFinder, $pprinter);
        $evlFn = $nodeFinder->findInstanceOf($evals->expr, Node\Expr\FuncCall::class);
        SearchFn($evlFn, $evals, $kit, $file, $nodeFinder, $pprinter);
    }


//    print_r($params);
    foreach ($params as $one) {
        $param_func = $one->var->name;
    }


    // поиск system, exec
    foreach ($nodes['calls'] as $func) {
        if (isset($func->name->name) && isset($param_func)) {
            if ($func->name->name = $param_func) {
                printResult($func, 20, $file, $pprinter);
            }
        }


        foreach ($func->args as $value) { // поиск параметров в функции и в теле функции
            if (isset($value->value->name) && isset($param_func)) {
                if ($value->value->name = $param_func) {
//                    printResult($func, 19, $file, $pprinter);
                }
            }

        }

        foreach ($callbackFn as $key => $value) {
            foreach ($value as $num) {
                if ($func->name == $key) {
                    $funcVar = $nodeFinder->findInstanceOf($func->args, Node\Expr\Variable::class);
                    if (isset($funcVar[$num])) {
                        if ($funcVar[$num] instanceof Node\Expr\Variable) {
                            if (in_array($funcVar[$num]->name, $kit)) {
                                printResult($func, 4, $file, $pprinter);
                            }
                        }
                    }
                    $funcStr = $nodeFinder->findInstanceOf($func->args, Node\Scalar\String_::class);
                    foreach ($funcStr as $str) {
                        if (preg_match('/POST|GET|REQUEST/', $str->value)) {
                            printResult($func, 12, $file, $pprinter);
                        }
                    }

                }
            }
        }


        // for trim
        $compFn = $nodeFinder->findInstanceOf($func->args, Node\Scalar\String_::class);
        foreach ($compFn as $value) {
            if (in_array(trim($value->value), $stopFn)) {
                printResult($func, 5, $file, $pprinter);
            }
        }


        if ($func->name == 'passthru') {
            $pasFn = $nodeFinder->findInstanceOf($func->args, Node\Expr\FuncCall::class);
            foreach ($pasFn as $fn) {
                if (in_array($fn->name, $stopFn)) {
                    printResult($func, 6, $file, $pprinter);
                }
            }
        }


        if (in_array($func->name, $stopFn)) {
            $funVr = $nodeFinder->findInstanceOf($func->args, Node\Expr\Variable::class);
            SearchFn($funVr, $func, $kit, $file, $nodeFinder, $pprinter);
        }
        $tmp = $func->name;
        while ($tmp instanceof Node\Expr\ArrayDimFetch) {
            $tmp = $tmp->var;
        }
        if ($tmp instanceof Node\Expr\Variable) {
            if (in_array($tmp->name, $kit)) {
//                SearchFn($tmp, $func, $kit, $file, $nodeFinder, $pprinter);
                printResult($func, 9, $file, $pprinter);
            }
        }

        if ($func->name == 'base64_decode') {
            $argBase = $nodeFinder->findInstanceOf($func->args, Node\Scalar\String_::class);
            foreach ($argBase as $cp) {
                $cipher = $cp->value;
                if (in_array(base64_decode($cipher), $stopFn) || in_array(base64_decode($cipher), $kit) || preg_match('/POST|GET|REQUEST/', base64_decode($cipher))) {
                    printResult($func, 10, $file, $pprinter);
                }
            }
        }
        if ($func->name == 'str_replace') {
            $argBase = $nodeFinder->findInstanceOf($func->args, Node\Scalar\String_::class);
            foreach ($argBase as $cp) {
                $cipher = $cp->value;
                if (in_array(base64_decode($cipher), $stopFn) || in_array(base64_decode($cipher), $kit) || preg_match('/POST|GET|REQUEST/', base64_decode($cipher))) {
                    printResult($func, 10, $file, $pprinter);
                }
            }
        }
        if ($func->name == 'preg_replace') {
            $check_arg = $nodeFinder->findInstanceOf($func->args, Node\Expr\FuncCall::class);
//            print_r($check_arg);
        }

    }


}


asort($colFlShells);
echo "\nФайлы в которых не найдены шеллы: \n";
var_dump($colFlShells);


echo microtime(true) - $start;
