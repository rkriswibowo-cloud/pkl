<?php

function impersonation_token(): string
{
    return $_SESSION['impersonation_token'] ??= bin2hex(random_bytes(32));
}

function require_writable_session(bool $mutates_on_get = false): void
{
    if (isset($_SESSION['impersonator_id'])
        && ($_SERVER['REQUEST_METHOD'] === 'POST' || $mutates_on_get)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Mode impersonasi hanya untuk melihat. Kembali ke akun admin untuk melakukan perubahan.');
    }
}
