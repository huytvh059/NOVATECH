<?php
declare(strict_types=1);
// CLI uniquement : saisie secrète sans argument visible dans la liste des processus.
function read_password(): string {
    if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
    if(!stream_isatty(STDIN)) { $p=rtrim((string)fgets(STDIN),"\r\n"); }
    else {
        if(PHP_OS_FAMILY==='Windows') throw new RuntimeException('Utiliser un pipe stdin ou exécuter sur Ubuntu.');
        fwrite(STDERR,'Mot de passe temporaire (12–72 octets) : ');
        $mode=trim((string)shell_exec('stty -g'));
        if($mode==='') throw new RuntimeException('Terminal sécurisé indisponible.');
        shell_exec('stty -echo');
        try { $p=rtrim((string)fgets(STDIN),"\r\n"); }
        finally { shell_exec('stty '.escapeshellarg($mode)); fwrite(STDERR,"\n"); }
    }
    if(strlen($p)<12 || strlen($p)>72) throw new InvalidArgumentException('Mật khẩu phải dài 12–72 byte.');
    return $p;
}
