<?php

$list = <<<TXT
don't
the
is
and
her
his
I'm
she
I'd
target
TXT;

$list = explode(PHP_EOL, $list);

$list = array_map(function($value) {
    return mb_strtolower($value);
}, $list);

return [

    'list' => $list,

];
