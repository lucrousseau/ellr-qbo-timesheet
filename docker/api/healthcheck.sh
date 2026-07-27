#!/bin/sh
set -e

php -r '$body=@file_get_contents("http://127.0.0.1:8000/api/health"); if(!$body){exit(1);} $data=json_decode($body,true); exit(is_array($data)&&($data["status"]??null)==="ok"?0:1);'
