<?php

declare(strict_types=1);

$output = '/feasibility/employee-holiday-email-0.8.12-smtp.jsonl';
$server = stream_socket_server('tcp://0.0.0.0:1025', $errorNumber, $errorMessage);
if (!$server) {
    throw new RuntimeException($errorMessage, $errorNumber);
}

while ($connection = @stream_socket_accept($server)) {
    fwrite($connection, "220 employee-management-test ESMTP\r\n");
    $recipients = [];

    while (($line = fgets($connection)) !== false) {
        $command = rtrim($line, "\r\n");
        $upper = strtoupper($command);

        if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
            fwrite($connection, "250-employee-management-test\r\n250 8BITMIME\r\n");
        } elseif (str_starts_with($upper, 'MAIL FROM:')) {
            fwrite($connection, "250 2.1.0 OK\r\n");
        } elseif (str_starts_with($upper, 'RCPT TO:')) {
            $recipients[] = trim(substr($command, 8), " <>\t");
            fwrite($connection, "250 2.1.5 OK\r\n");
        } elseif ($upper === 'DATA') {
            fwrite($connection, "354 End data with <CR><LF>.<CR><LF>\r\n");
            $data = '';
            while (($dataLine = fgets($connection)) !== false) {
                if ($dataLine === ".\r\n" || $dataLine === ".\n") {
                    break;
                }
                if (str_starts_with($dataLine, '..')) {
                    $dataLine = substr($dataLine, 1);
                }
                $data .= $dataLine;
            }
            file_put_contents($output, json_encode([
                'recipients' => $recipients,
                'data' => $data,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
            fwrite($connection, "250 2.0.0 queued\r\n");
        } elseif ($upper === 'RSET') {
            $recipients = [];
            fwrite($connection, "250 2.0.0 reset\r\n");
        } elseif ($upper === 'QUIT') {
            fwrite($connection, "221 2.0.0 bye\r\n");
            break;
        } else {
            fwrite($connection, "250 2.0.0 OK\r\n");
        }
    }

    fclose($connection);
}
