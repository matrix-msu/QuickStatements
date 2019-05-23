#!/bin/bash
./phpunit-4.8.phar --bootstrap public_html/php/ToolforgeCommon.php tests/ToolforgeCommonTest.php
./phpunit-4.8.phar --bootstrap public_html/php/wikidata.php tests/wikidataTest.php
