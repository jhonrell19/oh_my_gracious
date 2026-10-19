<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
echo 'APP_KEY='.base64_encode(random_bytes(32)).PHP_EOL;
