<?php
var_dump(is_numeric("\t123"));
var_dump(is_numeric("\r123"));
var_dump(is_numeric("123\x00"));
var_dump(is_numeric("\u{200B}123"));
