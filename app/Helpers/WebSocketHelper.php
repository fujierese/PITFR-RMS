<?php

if (!function_exists('send_ws_message')) {
    /**
     * Send a message to the WebSocket server via TCP bridge
     */
    function send_ws_message(array $payload): void
    {
        $fp = @fsockopen('127.0.0.1', 9001, $errno, $errstr, 0.5);
        if (!$fp) {
            \Log::warning('WebSocket push failed: Could not connect to TCP bridge');
            return;
        }
        fwrite($fp, json_encode($payload) . "\n");
        fclose($fp);
        \Log::info('WebSocket message sent', $payload);
    }
}