<?php 

namespace Socket;

use Socket\SocketException;

class UDP
{
    private $connection = null;
    private $sent;
    private $received;

    private $payload_size = 0;
    private $response_size = 0;

    public $response = "";

    public function __construct($ip = "127.0.0.1", $port = 27015, string $method = "fsock") {
        // TODO: Validate IP, PORT, METHOD. Try to resolve domain IP
        $this->ip = $ip;
        $this->port = $port;
        $this->method = $method;

        if (
            $this->method !== "fsock"
            && $this->method !== "socket"
        ) {
            throw new SocketException("Method is not implemented");
        }

        $this->{"open_$this->method"}();
    }

    public function __destruct() {
        if ($this->method === "fsock") {
            fclose($this->connection);
        }
    }

    /**
     * ***************************************************************
     * ** Create a connection ****************************************
     * ***************************************************************
     */

    private function open_socket() {
        $this->connection = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if (!$this->connection) {
            $this->errorCode = socket_last_error();
            $this->error = socket_strerror($this->errorCode);
            
            throw new SocketException("[$this->errorCode] Gniazdo nie zostało utworzone: \"$this->error\"");
        }

        socket_set_option(
            $this->connection,
            SOL_SOCKET,
            SO_RCVTIMEO,
            [
                "sec" => 0,
                "usec" => 500,
            ],
        );
    }

    private function open_fsock() {
        $this->connection = fsockopen("udp://$this->ip", $this->port, $this->errorCode, $this->error, 1);

        if (!$this->connection) {
            throw new SocketException("Opening a socket connection couldn't  \"$this->error\"");
        }

        stream_set_timeout($this->connection, 0, 50000);
    }

    /**
     * ***************************************************************
     * * Send data ***************************************************
     * ***************************************************************
     */

    public function send(string $server = "127.0.0.1", int $port = 27015, string $payload = "") {
        $this->{"send_$this->method"}($server, $port, $payload);
    }

    private function send_socket(string $server = "127.0.0.1", int $port = 27015, string $payload = "") {
        $this->sent = socket_sendto($this->connection, $payload, strlen($payload), 0, $server, $port);
        
        if ($this->sent === false) {
            $this->errorCode = socket_last_error();
            $this->error = socket_strerror($this->errorCode);
            
            throw new SocketException("Wysłanie danych nie było możliwe. $this->error");
        }

        $this->payload_size = $this->sent;
    }

    private function send_fsock(string $server = "127.0.0.1", int $port = 27015, string $payload = "") {
        $this->sent = fwrite($this->connection, $payload);
        
        if ($this->sent === null) {
            throw new SocketException("Wysłanie danych nie było możliwe. $this->error");
        }

        $this->payload_size = $this->sent;
    }

    /**
     * ***************************************************************
     * * Receive data ************************************************
     * ***************************************************************
     */

    public function receive(string $ip = "127.0.0.1", int $port = 27015, $length = 2045) {
        $this->{"receive_$this->method"}($ip, $port, $length);
    }

    public function receive_socket(string $ip = "127.0.0.1", int $port = 27015, $length = 2045) {
        $this->received = @socket_recvfrom($this->connection, $this->response, $length, 0, $ip, $port);

        if (!$this->received) {
            $this->errorCode = socket_last_error();
            $this->error = socket_strerror($this->errorCode);
            
            throw new SocketException("Odebranie danych nie było możliwe. $this->error");
        }

        $this->response_size = $this->received;
    }

    public function receive_fsock(string $ip = "127.0.0.1", int $port = 27015, $length = 2045) {
        $this->response = fread($this->connection, $length);

        $metadata = stream_get_meta_data($this->connection);

        if ($metadata['timed_out']) {
            throw new SocketException("Przekroczono czas oczekiwania na pakiet");
        }

        if ($this->response === false) {
            throw new SocketException("Odebranie danych nie było możliwe. $this->error");
        }

        $this->response_size = $this->received;
    }
}