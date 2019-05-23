#!/bin/bash
qdel bot
sleep 10
jsub -l release=trusty -mem 4g -N bot -once -continuous /matrix/dev/public_html/enslaved-quickstatements/tool-quickstatements/bot.php
