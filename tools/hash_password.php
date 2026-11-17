<?php
declare(strict_types=1);
require __DIR__.'/password_input.php';
try { echo password_hash(read_password(),PASSWORD_BCRYPT),PHP_EOL; }
catch(Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
