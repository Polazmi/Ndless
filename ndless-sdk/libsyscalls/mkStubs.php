<?php
// Author: Fabian Vogt
// I'm sorry for using PHP (I hate python)

$list = fopen(__DIR__ . "/../include/syscall-list.h", "r");
if($list === FALSE)
	die("Couldn't open syscall-list.h!\n");
	
$lines = array();
while(($lines[] = fgets($list)) !== FALSE);

fclose($list);

$i = 0;
while(true)
{
	if(strpos($lines[$i++], "START_OF_LIST") !== FALSE)
		break;
}

$stubs = fopen(__DIR__ . "/stubs.cpp", "w");

?>
#ifndef SYSCALL_DECLS
#define SYSCALL_DECLS

//This file has been autogenerated my mkStubs.php

#ifdef __cplusplus
extern "C" {
#endif // __cplusplus

#include <stddef.h>
#include <stdint.h>
#include <string.h>
#include <ngc.h>
#include <nucleus.h>
#include <lualib.h>
#include <lauxlib.h>
#include <bsdcompat.h>
#include <usb.h>

<?php
$header = <<<EOF
#include <syscall-decls.h>
#include <syscall-list.h>
#include <syscall.h>

//This file has been autogenerated my mkStubs.php

extern "C" {
int savedlr_stack[10];
int savedlr_stack_nr = 0;

EOF;

fwrite($stubs, $header);

while(true)
{
	if(strpos($lines[$i], "END_OF_LIST") !== FALSE)
		break;

	$matches = array();
	$found = preg_match("%#define (e_.+) \\d+ // (([^ (]+)|\\((.+)\\)) (.+)\\((.*)\\)%", $lines[$i], $matches);
	$i++;

	/* Shouldn't be false if you didn't change anything */
	if($found === 0)
		continue;

	/*array(6) {
		[0]=>
		string(58) "#define e_keypad_type 59 // (unsigned char*) keypad_type()"
		[1]=>
		string(13) "e_keypad_type"
		[2]=>
		string(16) "(unsigned char*)"
		[3]=>
		string(0) "" <- If return type not in parentheses
		[4]=>
		string(14) "unsigned char*" <- If return type in parentheses
		[5]=>
		string(13) "keypad_type"
		[6]=>
		string(0) "" <- Function arguments
		}*/

	$rettype = $matches[3] == "" ? $matches[4] : $matches[3];
	$param_count = $matches[6] == "" ? 0 : substr_count($matches[6], ",") + 1;

	if($param_count <= 4 && strpos($matches[6], "...") === FALSE)
	{
			$param_list = array();
			for($p = 1; $p <= $param_count; $p++)
				$param_list[] = "p" . $p;

			$param_list = implode(",", $param_list);
			
		$stub = <<<EOF
$rettype $matches[5]($matches[6])
{
	return syscall<$matches[1], $rettype>($param_list);
}

EOF;
	}
	else
	{
			$stub = <<<EOF
__attribute__((naked)) $rettype $matches[5]($matches[6])
{
	asm volatile("push {r4-r6}\\n"
				"ldr r4, =savedlr_stack\\n"
				"ldr r5, =savedlr_stack_nr\\n"
				"ldr r6, [r5]\\n"
				"add r6, r6, #1\\n"
				"str lr, [r4, r6, lsl #2]\\n"
				"str r6, [r5]\\n"
				"pop {r4-r6}\\n"
				"swi %[nr]\\n"
				"push {r4-r6}\\n"
				"ldr r4, =savedlr_stack\\n"
				"ldr r5, =savedlr_stack_nr\\n"
				"ldr r6, [r5]\\n"
				"ldr lr, [r4, r6, lsl #2]\\n"
				"sub r6, r6, #1\\n"
				"str r6, [r5]\\n"
				"pop {r4-r6}\\n"
				"bx lr\\n"
				".ltorg" :: [nr] "i" ($matches[1]));
}

EOF;
	}

	fwrite($stubs, $stub);
	
	echo $rettype . " " . $matches[5] . "(" . $matches[6] . ");\n";
}

$footer = <<<EOF
}
EOF;

fwrite($stubs, $footer);

fclose($stubs);
?>
#ifdef __cplusplus
}
#endif // __cplusplus

#endif // !SYSCALL_DECLS
