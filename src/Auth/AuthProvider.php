<?php
declare(strict_types=1);
// Giao diện dành cho DB hiện tại, LDAP ở giai đoạn 2.
interface AuthProvider { public function authenticate(string $username,string $password): ?array; }
