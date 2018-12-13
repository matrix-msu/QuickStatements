#!/bin/bash
qdel bot
sleep 10
jsub -l release=trusty -mem 4g -N bot -once -continuous /matrix/home/josh.christ/public_html/tool-quickstatements/bot.php
