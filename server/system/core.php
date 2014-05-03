<?php
/**
 The MIT License (MIT)

 Copyright (c) 2014 DZCP-Community
 DZCP - deV!L`z ClanPortal Steam Proxy Server
 http://www.dzcp.de

 Permission is hereby granted, free of charge, to any person obtaining a copy of
 this software and associated documentation files (the "Software"), to deal in
 the Software without restriction, including without limitation the rights to
 use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 the Software, and to permit persons to whom the Software is furnished to do so,
 subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

function objectToArray($d) {
    return json_decode(json_encode($d, JSON_FORCE_OBJECT), true);
}

function binary_multiples($size, $praefix=true, $short= true) {
    if($praefix === true) {
        if($short === true) {
            $norm = array('B', 'kB', 'MB', 'GB', 'TB', 'PB','EB', 'ZB', 'YB');
        } else {
            $norm = array('Byte',
                    'Kilobyte',
                    'Megabyte',
                    'Gigabyte',
                    'Terabyte',
                    'Petabyte',
                    'Exabyte',
                    'Zettabyte',
                    'Yottabyte'
            );
        }

        $factor = 1000;
    } else {
        if($short === true) {
            $norm = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB','EiB', 'ZiB', 'YiB');
        } else {
            $norm = array('Byte',
                    'Kibibyte',
                    'Mebibyte',
                    'Gibibyte',
                    'Tebibyte',
                    'Pebibyte',
                    'Exbibyte',
                    'Zebibyte',
                    'Yobibyte'
            );
        }

        $factor = 1024;

    }

    $count = count($norm) -1;
    $x = 0;
    while ($size >= $factor && $x < $count) {
        $size /= $factor;
        $x++;
    }

    $size = sprintf("%01.2f", $size) . ' ' . $norm[$x];
    return $size;
}